<?php

namespace Paw\Core;

// Encapsula la petición HTTP para que los controladores no lean $_GET, php://input ni $_SERVER directamente.
// El cuerpo de los POST es JSON (Content-Type: application/json).
class Request
{
    private ?array $body = null;

    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private string $rawBody = '',
        private array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            (string) file_get_contents('php://input'),
            $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    // Parámetro de la query string (?clave=valor)
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    // Campo del cuerpo JSON
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->allInput()[$key] ?? $default;
    }

    /**
     * Cuerpo JSON como array asociativo. Vacío si el pedido no trae cuerpo.
     *
     * @throws HttpException 400 si el cuerpo no es un objeto JSON válido
     */
    public function allInput(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }
        if (trim($this->rawBody) === '') {
            return $this->body = [];
        }

        $datos = json_decode($this->rawBody, true);
        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            throw HttpException::badRequest('El cuerpo del pedido tiene que ser un objeto JSON válido.');
        }
        return $this->body = $datos;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? $default;
    }

    public function origin(): ?string
    {
        return $this->header('Origin');
    }

    // Token del encabezado "Authorization: Bearer <token>", o null
    public function bearerToken(): ?string
    {
        $cabecera = $this->header('Authorization') ?? ($this->server['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
        if ($cabecera === null && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $nombre => $valor) {
                if (strtolower($nombre) === 'authorization') {
                    $cabecera = $valor;
                    break;
                }
            }
        }
        if (is_string($cabecera) && preg_match('/^Bearer\s+(\S+)$/i', trim($cabecera), $m) === 1) {
            return $m[1];
        }
        return null;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '';
    }
}
