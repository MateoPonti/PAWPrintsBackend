<?php

namespace Paw\Models;

// Capa de persistencia de los libros. Por ahora lee data/libros.json; en el próximo TP
// se reemplaza por una base de datos sin tocar los controladores, que solo usan estos métodos.
class LibroRepository
{
    private ?array $libros = null;

    public function __construct(private string $dataFile)
    {
    }

    /** @return Libro[] */
    public function all(): array
    {
        if ($this->libros === null) {
            $data = json_decode(file_get_contents($this->dataFile), true, flags: JSON_THROW_ON_ERROR);
            $this->libros = array_map([Libro::class, 'fromArray'], $data);
        }
        return $this->libros;
    }

    /**
     * Filtra y ordena el catálogo completo, sin paginar, para HTML y CSV.
     * Relevancia conserva el orden de carga del catálogo.
     *
     * @param array{genero?: string, autor?: string, precio_max?: int|null, orden?: string} $filtros
     * @return Libro[]
     */
    public function search(array $filtros): array
    {
        $genero = $filtros['genero'] ?? '';
        $autor = self::normalizarTexto(trim($filtros['autor'] ?? ''));
        $precioMax = $filtros['precio_max'] ?? null;

        $libros = array_values(array_filter(
            $this->all(),
            static fn (Libro $libro): bool =>
                ($genero === '' || $libro->genero === $genero)
                && ($autor === '' || str_contains(self::normalizarTexto($libro->autor), $autor))
                && ($precioMax === null || $libro->precio <= $precioMax),
        ));

        $comparador = match ($filtros['orden'] ?? 'relevancia') {
            'precio-asc' => static fn (Libro $a, Libro $b): int => $a->precio <=> $b->precio,
            'precio-desc' => static fn (Libro $a, Libro $b): int => $b->precio <=> $a->precio,
            'titulo' => static fn (Libro $a, Libro $b): int =>
                strcmp(self::normalizarTexto($a->titulo), self::normalizarTexto($b->titulo)),
            default => null,
        };

        if ($comparador !== null) {
            usort($libros, $comparador);
        }

        return $libros;
    }

    private static function normalizarTexto(string $texto): string
    {
        // También admite acentos Unicode descompuestos, sin exigir extensiones de PHP.
        $texto = preg_replace('/\p{Mn}+/u', '', $texto) ?? $texto;

        return strtolower(strtr($texto, [
            'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Å' => 'a',
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o', 'Õ' => 'o',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u', 'Û' => 'u',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'Ñ' => 'n', 'ñ' => 'n', 'Ç' => 'c', 'ç' => 'c',
        ]));
    }

    public function find(string $id): ?Libro
    {
        foreach ($this->all() as $libro) {
            if ($libro->id === $id) {
                return $libro;
            }
        }
        return null;
    }
}
