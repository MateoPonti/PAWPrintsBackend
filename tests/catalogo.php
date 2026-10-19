<?php

// Pruebas del contrato compartido por el catálogo HTML y la exportación CSV.
// Ejecutar desde la raíz: php tests/catalogo.php

use Paw\Models\Libro;
use Paw\Models\LibroRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

function verificarIds(array $esperados, array $libros, string $caso): void
{
    $actuales = array_map(static fn (Libro $libro): string => $libro->id, $libros);
    if ($actuales !== $esperados) {
        throw new RuntimeException($caso . ': se obtuvo ' . json_encode($actuales));
    }
    echo "OK: $caso\n";
}

$archivo = tempnam(sys_get_temp_dir(), 'pawprints-catalogo-');
try {
    file_put_contents($archivo, json_encode([
        ['id' => 'z', 'titulo' => 'Zeta', 'autor' => 'Gabriel García Márquez', 'genero' => 'novela', 'precio' => 200],
        ['id' => 'a', 'titulo' => 'Árbol', 'autor' => 'María Elena Walsh', 'genero' => 'infantil', 'precio' => 100],
        ['id' => 'e', 'titulo' => 'Época', 'autor' => 'Julio Cortázar', 'genero' => 'cuento', 'precio' => 100],
        ['id' => 'l', 'titulo' => 'Luna', 'autor' => 'César Vallejo', 'genero' => 'poesia', 'precio' => 0],
    ], JSON_THROW_ON_ERROR));
    $repo = new LibroRepository($archivo);

    verificarIds(['z', 'a', 'e', 'l'], $repo->search([]), 'Sin filtros, sin paginación');
    verificarIds(['z', 'a', 'e', 'l'], $repo->search(['per_page' => 1, 'page' => 2]), 'Ignora paginación del consumidor');
    verificarIds(['z'], $repo->search(['genero' => 'novela']), 'Género exacto');
    verificarIds([], $repo->search(['genero' => 'nov']), 'Género no usa coincidencia parcial');
    verificarIds([], $repo->search(['genero' => 'Novela']), 'Género distingue mayúsculas');
    verificarIds(['z'], $repo->search(['autor' => 'GARCIA MARQUEZ']), 'Autor sin acentos ni distinción de mayúsculas');
    verificarIds(['e'], $repo->search(['autor' => 'TÁZ']), 'Autor con acento y coincidencia parcial');
    verificarIds(['a'], $repo->search(['autor' => "MARI\u{0301}A"]), 'Autor con acento Unicode descompuesto');
    verificarIds(['l'], $repo->search(['autor' => '  césar  ']), 'Espacios alrededor del autor');
    verificarIds(['a', 'e', 'l'], $repo->search(['precio_max' => 100]), 'Precio máximo inclusivo');
    verificarIds(['l'], $repo->search(['precio_max' => 0]), 'Cero es un límite válido');
    verificarIds(['e'], $repo->search([
        'genero' => 'cuento', 'autor' => 'cortazar', 'precio_max' => 100,
    ]), 'Filtros combinados');
    verificarIds([], $repo->search(['autor' => 'inexistente']), 'Búsqueda vacía');
    verificarIds(['l', 'a', 'e', 'z'], $repo->search(['orden' => 'precio-asc']), 'Precio ascendente, empates estables');
    verificarIds(['z', 'a', 'e', 'l'], $repo->search(['orden' => 'precio-desc']), 'Precio descendente');
    verificarIds(['a', 'e', 'l', 'z'], $repo->search(['orden' => 'titulo']), 'Título alfabético con acentos');
    verificarIds(['z', 'a', 'e', 'l'], $repo->search(['orden' => 'relevancia']), 'Orden original después de ordenar');
    verificarIds(['z', 'a', 'e', 'l'], $repo->search(['orden' => 'desconocido']), 'Orden desconocido conserva relevancia');
    verificarIds(['z', 'a', 'e', 'l'], $repo->all(), 'Buscar no altera all()');
} finally {
    unlink($archivo);
}
