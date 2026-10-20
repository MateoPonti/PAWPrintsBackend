<?php

// Pruebas del alta de libros (LibroValidator) y de las reglas de las cuentas.
// Ejecutar desde la raíz: php tests/libro.php

use Paw\Models\LibroValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

function verificarCampos(array $esperados, array $errores, string $caso): void
{
    $actuales = array_keys($errores);
    sort($actuales);
    sort($esperados);
    if ($actuales !== $esperados) {
        throw new RuntimeException($caso . ': se obtuvo ' . json_encode($errores, JSON_UNESCAPED_UNICODE));
    }
    echo "OK: $caso\n";
}

$valido = [
    'titulo' => 'El túnel', 'autor' => 'Ernesto Sabato', 'genero' => 'novela', 'precio' => '9000',
    'resumen' => '', 'descripcion' => '', 'autor_bio' => '', 'imagen_url' => '',
];
$validar = static fn (array $cambios): array =>
    LibroValidator::validar(LibroValidator::valores(array_merge($valido, $cambios)));

verificarCampos([], $validar([]), 'Libro válido con solo lo obligatorio');
verificarCampos([], $validar(['imagen_url' => 'https://ejemplo.com/a.jpg']), 'Portada con URL https');
verificarCampos(['titulo'], $validar(['titulo' => '   ']), 'Título solo con espacios');
verificarCampos(['titulo'], $validar(['titulo' => str_repeat('a', LibroValidator::TITULO_MAX + 1)]), 'Título demasiado largo');
verificarCampos(['autor'], $validar(['autor' => '']), 'Autor vacío');
verificarCampos(['genero'], $validar(['genero' => 'terror']), 'Género que no existe');
verificarCampos(['precio'], $validar(['precio' => '0']), 'Precio 0');
verificarCampos(['precio'], $validar(['precio' => '-5']), 'Precio negativo');
verificarCampos(['precio'], $validar(['precio' => '12,50']), 'Precio con decimales');
verificarCampos(['precio'], $validar(['precio' => 'abc']), 'Precio no numérico');
verificarCampos(['imagen_url'], $validar(['imagen_url' => 'javascript:alert(1)']), 'Portada con javascript:');
verificarCampos(['imagen_url'], $validar(['imagen_url' => 'no es una url']), 'Portada que no es URL');
verificarCampos(['resumen'], $validar(['resumen' => str_repeat('a', LibroValidator::RESUMEN_MAX + 1)]), 'Resumen demasiado largo');
verificarCampos(['titulo', 'autor', 'genero', 'precio'], LibroValidator::validar(LibroValidator::valores([])), 'Formulario vacío');
verificarCampos(['titulo'], $validar(['titulo' => ['x']]), 'Título como array no se acepta');
