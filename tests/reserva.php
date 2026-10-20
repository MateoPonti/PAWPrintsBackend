<?php

// Pruebas de las validaciones del formulario de reserva (las mismas que el HTML).
// Ejecutar desde la raíz: php tests/reserva.php

use Paw\Models\LibroRepository;
use Paw\Models\ReservaValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

function verificarErrores(array $esperados, array $errores, string $caso): void
{
    $actuales = array_keys($errores);
    sort($actuales);
    sort($esperados);
    if ($actuales !== $esperados) {
        throw new RuntimeException($caso . ': se obtuvo ' . json_encode($errores, JSON_UNESCAPED_UNICODE));
    }
    echo "OK: $caso\n";
}

$archivo = tempnam(sys_get_temp_dir(), 'pawprints-reserva-');
try {
    file_put_contents($archivo, json_encode([
        ['id' => 'rayuela', 'titulo' => 'Rayuela', 'autor' => 'Julio Cortázar', 'genero' => 'novela', 'precio' => 100],
    ], JSON_THROW_ON_ERROR));
    $validator = new ReservaValidator(new LibroRepository($archivo));
    $valido = [
        'nombre' => 'Ana Pérez',
        'email' => 'ana@mail.com',
        'telefono' => '(011) 4444-5555',
        'libro' => ['rayuela'],
        'cantidad' => ['2'],
    ];
    $validar = static fn (array $cambios): array =>
        $validator->validar(ReservaValidator::valores(array_merge($valido, $cambios)));

    verificarErrores([], $validar([]), 'Reserva válida');
    verificarErrores([], $validar(['telefono' => '']), 'Teléfono opcional');
    verificarErrores(['nombre'], $validar(['nombre' => '   ']), 'Nombre solo con espacios');
    verificarErrores([], $validar(['nombre' => str_repeat('ñ', 100)]), 'Nombre de 100 caracteres multibyte');
    verificarErrores(['nombre'], $validar(['nombre' => str_repeat('a', 101)]), 'Nombre de 101 caracteres');
    verificarErrores(['nombre'], $validar(['nombre' => "Ana\r\nBcc: x@x.com"]), 'Nombre con salto de línea');
    verificarErrores(['email'], $validar(['email' => '']), 'Email vacío');
    verificarErrores(['email'], $validar(['email' => 'ana@mail']), 'Email sin dominio con punto');
    verificarErrores(['telefono'], $validar(['telefono' => '12345']), 'Teléfono de 5 caracteres');
    verificarErrores(['telefono'], $validar(['telefono' => 'abc1234']), 'Teléfono con letras');
    verificarErrores(['libros'], $validar(['libro' => null, 'cantidad' => null]), 'Sin libros');
    verificarErrores(['libro'], $validar(['libro' => ['']]), 'Libro sin elegir');
    verificarErrores(['libro'], $validar(['libro' => ['no-existe']]), 'Libro inexistente');
    verificarErrores(['libro'], $validar(['libro' => [['rayuela']]]), 'Libro que no es texto');
    verificarErrores(['cantidad'], $validar(['cantidad' => ['0']]), 'Cantidad 0');
    verificarErrores([], $validar(['cantidad' => ['10']]), 'Cantidad 10');
    verificarErrores(['cantidad'], $validar(['cantidad' => ['11']]), 'Cantidad 11');
    verificarErrores(['cantidad'], $validar(['cantidad' => ['1.5']]), 'Cantidad no entera');
    verificarErrores(['cantidad'], $validar(['cantidad' => []]), 'Libro sin cantidad');

    $reserva = $validator->crear(ReservaValidator::valores(array_merge($valido, [
        'libro' => ['rayuela', 'rayuela'],
        'cantidad' => ['2', '3'],
    ])));
    if ($reserva->total() !== 500 || $reserva->totalFormateado() !== '$500') {
        throw new RuntimeException('Total de la reserva: se obtuvo ' . $reserva->total());
    }
    echo "OK: Total de la reserva\n";
} finally {
    unlink($archivo);
}
