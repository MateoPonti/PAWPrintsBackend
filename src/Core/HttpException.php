<?php

namespace Paw\Core;

use RuntimeException;

// Error con un código HTTP asociado. El ErrorHandler lo convierte en la respuesta JSON de error:
// {"error": "codigo", "mensaje": "...", "errores": {"campo": "mensaje"}}.
class HttpException extends RuntimeException
{
    private const CODIGOS = [
        400 => 'solicitud_invalida',
        401 => 'no_autenticado',
        403 => 'prohibido',
        404 => 'no_encontrado',
        405 => 'metodo_no_permitido',
        422 => 'datos_invalidos',
        500 => 'error_interno',
        503 => 'no_disponible',
    ];

    /**
     * @param array<string, string> $errores Mensaje por campo (solo en los 422)
     * @param array<string, string> $headers Cabeceras extra de la respuesta
     */
    public function __construct(
        private int $status,
        string $mensaje = '',
        private string $codigo = '',
        private array $errores = [],
        private array $headers = [],
    ) {
        parent::__construct($mensaje, $status);
    }

    public static function notFound(string $mensaje = 'El recurso que buscás no existe.'): self
    {
        return new self(404, $mensaje);
    }

    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'Método no permitido.', '', [], ['Allow' => implode(', ', $allowed)]);
    }

    public static function badRequest(string $mensaje): self
    {
        return new self(400, $mensaje);
    }

    public static function unauthorized(string $mensaje = 'Tenés que iniciar sesión para hacer esto.'): self
    {
        return new self(401, $mensaje, '', [], ['WWW-Authenticate' => 'Bearer']);
    }

    public static function forbidden(string $mensaje = 'No tenés permisos para hacer esto.'): self
    {
        return new self(403, $mensaje);
    }

    public static function unavailable(string $mensaje, string $codigo = ''): self
    {
        return new self(503, $mensaje, $codigo);
    }

    /** @param array<string, string> $errores */
    public static function invalid(array $errores, string $mensaje = 'Revisá los datos ingresados.'): self
    {
        return new self(422, $mensaje, '', $errores);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function codigo(): string
    {
        return $this->codigo !== '' ? $this->codigo : (self::CODIGOS[$this->status] ?? 'error');
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
