<?php

namespace Paw\Models;

use Psr\Log\LoggerInterface;

// Avisa al personal de cada reserva por correo, a la dirección configurada en MAIL_TO.
// Envía por la API HTTP de Brevo (HTTPS, puerto 443): Render bloquea los puertos SMTP en el plan
// gratuito, así que no se usa SMTP. Si MAIL_API_KEY está vacía (desarrollo) no envía nada y deja
// el correo en el log.
class ReservaMailer
{
    public const API_URL = 'https://api.brevo.com/v3/smtp/email';
    public const TIMEOUT = 10;

    /** @var callable(string $url, array $headers, string $cuerpo): array{0: int, 1: string} */
    private $http;

    /** @param null|callable $http Reemplaza el POST real (lo usan los tests) */
    public function __construct(
        private array $config,
        private LoggerInterface $logger,
        ?callable $http = null,
    ) {
        $this->http = $http ?? [self::class, 'post'];
    }

    /** @throws CorreoException si el servicio de correo rechaza el envío o no responde */
    public function enviar(Reserva $reserva): void
    {
        $asunto = 'Nueva reserva de ' . $reserva->nombre;
        $cuerpo = self::cuerpo($reserva);

        if (($this->config['api_key'] ?? '') === '') {
            $this->logger->info('Correo de reserva no enviado porque MAIL_API_KEY está vacía', [
                'to' => $this->config['to'],
                'asunto' => $asunto,
                'cuerpo' => $cuerpo,
            ]);
            return;
        }

        $mensaje = json_encode([
            'sender' => ['name' => $this->config['from_name'], 'email' => $this->config['from']],
            'to' => [['email' => $this->config['to']]],
            // Así el personal le puede responder directamente al cliente
            'replyTo' => ['email' => $reserva->email, 'name' => $reserva->nombre],
            'subject' => $asunto,
            'textContent' => $cuerpo,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        [$status, $respuesta] = ($this->http)($this->config['api_url'] ?? self::API_URL, [
            'api-key: ' . $this->config['api_key'],
            'Content-Type: application/json',
            'Accept: application/json',
        ], $mensaje);

        if ($status < 200 || $status >= 300) {
            // Brevo responde {"code": "...", "message": "..."}; nunca incluye la API key
            throw new CorreoException("El servicio de correo respondió HTTP $status: " . mb_substr($respuesta, 0, 300));
        }
        $this->logger->info('Correo de reserva enviado a {to}', ['to' => $this->config['to']]);
    }

    /**
     * POST HTTPS con las funciones nativas de PHP (sin cURL ni librerías).
     *
     * @return array{0: int, 1: string} [código HTTP, cuerpo de la respuesta]
     * @throws CorreoException si no hay conexión o se vence el tiempo de espera
     */
    public static function post(string $url, array $headers, string $cuerpo): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $cuerpo,
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true, // devuelve el cuerpo también en las respuestas 4xx/5xx
            ],
        ]);
        $respuesta = @file_get_contents($url, false, $contexto);
        if ($respuesta === false) {
            throw new CorreoException('No se pudo conectar con el servicio de correo: ' . (error_get_last()['message'] ?? 'sin detalle'));
        }
        // Cabeceras de la respuesta: función nueva desde PHP 8.4; antes, la variable $http_response_header
        $cabeceras = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        $status = isset($cabeceras[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $cabeceras[0], $m) ? (int) $m[1] : 0;
        return [$status, $respuesta];
    }

    // Cuerpo en texto plano del correo al personal
    public static function cuerpo(Reserva $reserva): string
    {
        $lineas = [
            'Se recibió una nueva reserva desde el sitio.',
            '',
            'Nombre: ' . $reserva->nombre,
            'Correo: ' . $reserva->email,
            'Teléfono: ' . ($reserva->telefono !== '' ? $reserva->telefono : '(no informado)'),
            '',
            'Libros:',
        ];
        foreach ($reserva->items as $item) {
            $libro = $item['libro'];
            $lineas[] = '- ' . $item['cantidad'] . ' x ' . $libro->titulo . ' (' . $libro->autor . ') - '
                . $libro->precioFormateado() . ' c/u';
        }
        $lineas[] = '';
        $lineas[] = 'Total estimado: ' . $reserva->totalFormateado();

        return implode("\n", $lineas) . "\n";
    }
}
