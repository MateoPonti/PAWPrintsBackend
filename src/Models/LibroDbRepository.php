<?php

namespace Paw\Models;

use Paw\Core\Database;
use PDO;

// Libros guardados en PostgreSQL (Neon). Tiene los mismos métodos que LibroRepository (el del JSON),
// pero la búsqueda y el detalle se resuelven en la base con SQL (WHERE y ORDER BY), no en PHP.
// Además agrega el alta de libros.
//
// Inyección SQL: los valores que manda el usuario van siempre como parámetros de la sentencia preparada
// (:genero, :autor, :precio_max), nunca pegados al texto del SQL. El orden no se puede mandar como
// parámetro, así que se elige de una lista fija (ORDENES): un valor que no está en la lista se ignora.
class LibroDbRepository extends LibroRepository
{
    private const COLUMNAS = 'slug, titulo, autor, genero, precio, resumen, descripcion, autor_bio, imagen_url';

    // Orden pedido => ORDER BY. "relevancia" es el orden de carga, igual que con el JSON.
    private const ORDENES = [
        'relevancia' => 'id',
        'precio-asc' => 'precio, id',
        'precio-desc' => 'precio DESC, id',
        'titulo' => 'lower(translate(titulo, :con_acento, :sin_acento)), id',
    ];

    // Letras con acento y, en la misma posición, su versión sin acento. Las usa translate() de Postgres
    // para que buscar "garcia" encuentre "García".
    private const CON_ACENTO = 'ÁÀÄÂÃáàäâãÉÈËÊéèëêÍÌÏÎíìïîÓÒÖÔÕóòöôõÚÙÜÛúùüûÑñÇç';
    private const SIN_ACENTO = 'AAAAAaaaaaEEEEeeeeIIIIiiiiOOOOOoooooUUUUuuuuNnCc';

    private ?array $cache = null;

    public function __construct(private PDO $pdo)
    {
        parent::__construct('');
    }

    /** @return Libro[] */
    public function all(): array
    {
        if ($this->cache === null) {
            $filas = $this->pdo->query('SELECT ' . self::COLUMNAS . ' FROM libros ORDER BY id')->fetchAll();
            $this->cache = array_map([self::class, 'libroDeFila'], $filas);
        }
        return $this->cache;
    }

    /**
     * Filtra y ordena en la base. Recibe los mismos filtros que LibroRepository::search().
     *
     * @param array{genero?: string, autor?: string, precio_max?: int|null, orden?: string} $filtros
     * @return Libro[]
     */
    public function search(array $filtros): array
    {
        $condiciones = [];
        $parametros = [];

        $genero = $filtros['genero'] ?? '';
        if ($genero !== '') {
            $condiciones[] = 'genero = :genero';
            $parametros['genero'] = $genero;
        }

        $autor = self::normalizarTexto(trim($filtros['autor'] ?? ''));
        if ($autor !== '') {
            // El autor de la base se pasa a minúsculas y sin acentos, igual que el texto buscado
            $condiciones[] = 'lower(translate(autor, :con_acento, :sin_acento)) LIKE :autor';
            $parametros['autor'] = '%' . Database::escaparLike($autor) . '%';
        }

        $precioMax = $filtros['precio_max'] ?? null;
        if ($precioMax !== null) {
            $condiciones[] = 'precio <= :precio_max';
            $parametros['precio_max'] = $precioMax;
        }

        $sql = 'SELECT ' . self::COLUMNAS . ' FROM libros';
        if ($condiciones !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }
        $orden = $filtros['orden'] ?? 'relevancia';
        if (!array_key_exists($orden, self::ORDENES)) {
            $orden = 'relevancia';
        }
        $sql .= ' ORDER BY ' . self::ORDENES[$orden];

        // Las letras para translate() también van como parámetros (solo si el SQL las usa)
        if (str_contains($sql, ':con_acento')) {
            $parametros['con_acento'] = self::CON_ACENTO;
            $parametros['sin_acento'] = self::SIN_ACENTO;
        }

        $st = $this->pdo->prepare($sql);
        $st->execute($parametros);
        return array_map([self::class, 'libroDeFila'], $st->fetchAll());
    }

    public function find(string $id): ?Libro
    {
        $st = $this->pdo->prepare('SELECT ' . self::COLUMNAS . ' FROM libros WHERE slug = :slug');
        $st->execute(['slug' => $id]);
        $fila = $st->fetch();
        if ($fila === false) {
            return null;
        }
        return self::libroDeFila($fila);
    }

    private static function libroDeFila(array $f): Libro
    {
        return Libro::fromArray([
            'id' => $f['slug'],
            'titulo' => $f['titulo'],
            'autor' => $f['autor'],
            'genero' => $f['genero'],
            'precio' => $f['precio'],
            'resumen' => $f['resumen'] ?? '',
            'descripcion' => $f['descripcion'] ?? '',
            'autor_bio' => $f['autor_bio'] ?? '',
            'portada' => $f['imagen_url'] ?? '',
        ]);
    }

    /**
     * Guarda un libro (datos ya validados por LibroValidator) y devuelve su id público (el slug).
     * El slug sale del título: "El túnel" -> "el-tunel"; si ya existe agrega -2, -3...
     */
    public function crear(array $d): string
    {
        $slug = $this->slugUnico($d['titulo']);
        $st = $this->pdo->prepare(
            'INSERT INTO libros (slug, titulo, autor, genero, precio, resumen, descripcion, autor_bio, imagen_url)
             VALUES (:slug, :titulo, :autor, :genero, :precio, :resumen, :descripcion, :autor_bio, :imagen_url)'
        );
        $st->execute([
            'slug' => $slug,
            'titulo' => $d['titulo'],
            'autor' => $d['autor'],
            'genero' => $d['genero'],
            'precio' => $d['precio'],
            'resumen' => $d['resumen'] !== '' ? $d['resumen'] : null,
            'descripcion' => $d['descripcion'] !== '' ? $d['descripcion'] : null,
            'autor_bio' => $d['autor_bio'] !== '' ? $d['autor_bio'] : null,
            'imagen_url' => $d['imagen_url'] !== '' ? $d['imagen_url'] : null,
        ]);
        $this->cache = null;
        return $slug;
    }

    private function slugUnico(string $titulo): string
    {
        $base = strtr(mb_strtolower($titulo), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        $base = substr($base !== '' ? $base : 'libro', 0, 140);

        $st = $this->pdo->prepare('SELECT 1 FROM libros WHERE slug = :slug');
        $slug = $base;
        for ($i = 2; $i < 1000; $i++) {
            $st->execute(['slug' => $slug]);
            if ($st->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $i;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }
}
