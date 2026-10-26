<?php

namespace Paw\Models;

use PDO;

// Libros guardados en PostgreSQL (Neon). Extiende LibroRepository y solo cambia de dónde salen
// los libros (all()): search() y find() se heredan, así el catálogo filtra y ordena exactamente igual
// que con el JSON. Además agrega el alta de libros.
class LibroDbRepository extends LibroRepository
{
    private ?array $cache = null;

    public function __construct(private PDO $pdo)
    {
        parent::__construct('');
    }

    /** @return Libro[] */
    public function all(): array
    {
        if ($this->cache === null) {
            $filas = $this->pdo->query(
                'SELECT slug, titulo, autor, genero, precio, resumen, descripcion, autor_bio, imagen_url
                 FROM libros ORDER BY id'
            )->fetchAll();
            $this->cache = array_map(static fn (array $f): Libro => Libro::fromArray([
                'id' => $f['slug'],
                'titulo' => $f['titulo'],
                'autor' => $f['autor'],
                'genero' => $f['genero'],
                'precio' => $f['precio'],
                'resumen' => $f['resumen'] ?? '',
                'descripcion' => $f['descripcion'] ?? '',
                'autor_bio' => $f['autor_bio'] ?? '',
                'portada' => $f['imagen_url'] ?? '',
            ]), $filas);
        }
        return $this->cache;
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
