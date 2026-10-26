<?php

namespace Paw\Models;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

// Avisa al personal de cada reserva por correo, a la dirección configurada en MAIL_TO.
// Si SMTP_HOST está vacío (desarrollo) no envía nada y deja el correo en el log.
class ReservaMailer
{
    public function __construct(
        private array $config,
        private LoggerInterface $logger,
    ) {
    }

    /** @throws \PHPMailer\PHPMailer\Exception si el servidor SMTP rechaza o no responde */
    public function enviar(Reserva $reserva): void
    {
        $asunto = 'Nueva reserva de ' . $reserva->nombre;
        $cuerpo = self::cuerpo($reserva);
        $smtp = $this->config['smtp'];

        if ($smtp['host'] === '') {
            $this->logger->info('Correo de reserva no enviado porque SMTP_HOST está vacío', [
                'to' => $this->config['to'],
                'asunto' => $asunto,
                'cuerpo' => $cuerpo,
            ]);
            return;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];
        $mail->SMTPAuth = $smtp['user'] !== '';
        $mail->Username = $smtp['user'];
        $mail->Password = $smtp['pass'];
        $mail->SMTPSecure = match ($smtp['secure']) {
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            default => '',
        };
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $mail->setFrom($this->config['from'], $this->config['from_name']);
        $mail->addAddress($this->config['to']);
        // Así el personal le puede responder directamente al cliente
        $mail->addReplyTo($reserva->email, $reserva->nombre);
        $mail->Subject = $asunto;
        $mail->isHTML(false);
        $mail->Body = $cuerpo;

        $mail->send();
        $this->logger->info('Correo de reserva enviado a {to}', ['to' => $this->config['to']]);
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
