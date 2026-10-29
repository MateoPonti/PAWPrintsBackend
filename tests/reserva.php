<?php

// Pruebas de las validaciones de la reserva (cuerpo JSON de POST /api/reservas) y del aviso por correo.
// El correo se prueba con un HTTP falso: nunca se manda un correo real.
// Ejecutar desde la raíz: php tests/reserva.php

use Paw\Models\CorreoException;
use Paw\Models\LibroRepository;
use Paw\Models\ReservaMailer;
use Paw\Models\ReservaValidator;
use Psr\Log\NullLogger;

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
        'items' => [['libro' => 'rayuela', 'cantidad' => 2]],
    ];
    $validar = static fn (array $cambios): array =>
        $validator->validar(ReservaValidator::valores(array_merge($valido, $cambios)));

    verificarErrores([], $validar([]), 'Reserva válida');
    verificarErrores([], $validar(['items' => [['libro' => 'rayuela', 'cantidad' => '2']]]), 'Cantidad como texto');
    verificarErrores([], $validar(['telefono' => '']), 'Teléfono opcional');
    verificarErrores([], $validar(['telefono' => null]), 'Teléfono ausente');
    verificarErrores(['nombre'], $validar(['nombre' => '   ']), 'Nombre solo con espacios');
    verificarErrores(['nombre'], $validar(['nombre' => ['Ana']]), 'Nombre que no es texto');
    verificarErrores([], $validar(['nombre' => str_repeat('ñ', 100)]), 'Nombre de 100 caracteres multibyte');
    verificarErrores(['nombre'], $validar(['nombre' => str_repeat('a', 101)]), 'Nombre de 101 caracteres');
    verificarErrores(['nombre'], $validar(['nombre' => "Ana\r\nBcc: x@x.com"]), 'Nombre con salto de línea');
    verificarErrores(['email'], $validar(['email' => '']), 'Email vacío');
    verificarErrores(['email'], $validar(['email' => 'ana@mail']), 'Email sin dominio con punto');
    verificarErrores(['telefono'], $validar(['telefono' => '12345']), 'Teléfono de 5 caracteres');
    verificarErrores(['telefono'], $validar(['telefono' => 'abc1234']), 'Teléfono con letras');
    verificarErrores(['items'], $validar(['items' => []]), 'Sin libros');
    verificarErrores(['items'], $validar(['items' => null]), 'Items ausente');
    verificarErrores(['items'], $validar(['items' => 'rayuela']), 'Items que no es una lista');
    verificarErrores(['items.0.libro'], $validar(['items' => [['libro' => '', 'cantidad' => 1]]]), 'Libro sin elegir');
    verificarErrores(['items.0.libro'], $validar(['items' => [['libro' => 'no-existe', 'cantidad' => 1]]]), 'Libro inexistente');
    verificarErrores(['items.0.libro'], $validar(['items' => [['libro' => ['rayuela'], 'cantidad' => 1]]]), 'Libro que no es texto');
    verificarErrores(['items.0.libro', 'items.0.cantidad'], $validar(['items' => ['rayuela']]), 'Item que no es un objeto');
    verificarErrores(['items.0.cantidad'], $validar(['items' => [['libro' => 'rayuela', 'cantidad' => 0]]]), 'Cantidad 0');
    verificarErrores([], $validar(['items' => [['libro' => 'rayuela', 'cantidad' => 10]]]), 'Cantidad 10');
    verificarErrores(['items.0.cantidad'], $validar(['items' => [['libro' => 'rayuela', 'cantidad' => 11]]]), 'Cantidad 11');
    verificarErrores(['items.0.cantidad'], $validar(['items' => [['libro' => 'rayuela', 'cantidad' => 1.5]]]), 'Cantidad decimal');
    verificarErrores(['items.0.cantidad'], $validar(['items' => [['libro' => 'rayuela', 'cantidad' => '1.5']]]), 'Cantidad decimal como texto');
    verificarErrores(['items.0.cantidad'], $validar(['items' => [['libro' => 'rayuela']]]), 'Libro sin cantidad');
    verificarErrores(
        ['items.1.libro', 'items.1.cantidad'],
        $validar(['items' => [['libro' => 'rayuela', 'cantidad' => 1], ['libro' => '', 'cantidad' => 'x']]]),
        'El error indica la fila',
    );
    verificarErrores(
        ['items'],
        $validar(['items' => array_fill(0, ReservaValidator::ITEMS_MAX + 1, ['libro' => 'rayuela', 'cantidad' => 1])]),
        'Demasiados libros',
    );

    $reserva = $validator->crear(ReservaValidator::valores(array_merge($valido, [
        'items' => [['libro' => 'rayuela', 'cantidad' => 2], ['libro' => 'rayuela', 'cantidad' => '3']],
    ])));
    if ($reserva->total() !== 500 || $reserva->totalFormateado() !== '$500') {
        throw new RuntimeException('Total de la reserva: se obtuvo ' . $reserva->total());
    }
    echo "OK: Total de la reserva\n";

    $datos = $reserva->toArray();
    if ($datos['total'] !== 500 || $datos['items'][0] !== ['libro' => 'rayuela', 'titulo' => 'Rayuela', 'cantidad' => 2, 'precio' => 100]) {
        throw new RuntimeException('Forma de la reserva en la API: ' . json_encode($datos, JSON_UNESCAPED_UNICODE));
    }
    echo "OK: Forma de la reserva en la API\n";

    // ---------- Aviso por correo (API de Brevo) ----------
    $config = ['to' => 'personal@pawprints.test', 'from' => 'remitente@pawprints.test', 'from_name' => 'PawPrints',
        'api_key' => 'clave-de-prueba', 'api_url' => 'https://api.brevo.test/v3/smtp/email'];
    $pedido = null;
    $http = static function (int $status, string $cuerpo) use (&$pedido): callable {
        return static function (string $url, array $headers, string $json) use ($status, $cuerpo, &$pedido): array {
            $pedido = ['url' => $url, 'headers' => $headers, 'json' => json_decode($json, true)];
            return [$status, $cuerpo];
        };
    };

    (new ReservaMailer($config, new NullLogger(), $http(201, '{"messageId":"<1@brevo>"}')))->enviar($reserva);
    $j = $pedido['json'];
    if ($pedido['url'] !== $config['api_url'] || !in_array('api-key: clave-de-prueba', $pedido['headers'], true)
        || $j['to'][0]['email'] !== 'personal@pawprints.test' || $j['sender']['email'] !== 'remitente@pawprints.test'
        || $j['replyTo']['email'] !== 'ana@mail.com' || !str_contains($j['textContent'], '2 x Rayuela')
        || !str_contains($j['subject'], 'Ana Pérez (ana@mail.com)') || !str_contains($j['textContent'], 'Nombre: Ana Pérez')) {
        throw new RuntimeException('Pedido a Brevo mal armado: ' . json_encode($pedido, JSON_UNESCAPED_UNICODE));
    }
    echo "OK: Correo enviado por la API con remitente, destinatario, respuesta al cliente y detalle\n";

    $texto = ReservaMailer::cuerpo($reserva, new DateTimeImmutable('2026-10-29 15:23', new DateTimeZone('America/Argentina/Buenos_Aires')));
    if (!str_contains($texto, 'el 29/10/2026 a las 15:23') || !str_contains($texto, 'QUIÉN RESERVÓ') || !str_contains($texto, 'le llega a ana@mail.com')) {
        throw new RuntimeException("Cuerpo del correo:\n$texto");
    }
    echo "OK: El correo dice quién reservó, cuándo y cómo responderle\n";

    try {
        (new ReservaMailer($config, new NullLogger(), $http(401, '{"code":"unauthorized","message":"Key not found"}')))->enviar($reserva);
        throw new RuntimeException('Un 401 de la API tenía que lanzar CorreoException');
    } catch (CorreoException $e) {
        if (!str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'clave-de-prueba')) {
            throw new RuntimeException('Mensaje de error del correo: ' . $e->getMessage());
        }
    }
    echo "OK: Un rechazo de la API lanza CorreoException (sin exponer la clave)\n";

    $pedido = null;
    (new ReservaMailer(['api_key' => ''] + $config, new NullLogger(), $http(201, '')))->enviar($reserva);
    if ($pedido !== null) {
        throw new RuntimeException('Sin MAIL_API_KEY no tenía que llamar a la API');
    }
    echo "OK: Sin MAIL_API_KEY no se llama a la API (el correo queda en el log)\n";
} finally {
    unlink($archivo);
}
