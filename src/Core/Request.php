<?php

namespace Paw\Core;

// Encapsula la petición HTTP para que los controladores no lean $_GET, php://input ni $_SERVER directamente.
// El cuerpo de los POST es JSON (Content-Type: application/json), salvo la subida de portadas, que manda la imagen tal cual.
class Request
{
    private ?array $body = null;

    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private string $rawBody = '',
        private array $server = [],
        private array $cookies = [],
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
            $_COOKIE,
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

    // Cuerpo sin procesar (lo usa la subida de portadas)
    public function body(): string
    {
        return $this->rawBody;
    }

    // Tipo del cuerpo sin parámetros: "image/png; charset=x" -> "image/png".
    // PHP lo deja en CONTENT_TYPE y no en HTTP_CONTENT_TYPE como al resto de las cabeceras.
    public function contentType(): string
    {
        $tipo = $this->server['CONTENT_TYPE'] ?? $this->header('Content-Type') ?? '';
        return strtolower(trim(explode(';', $tipo)[0]));
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

    // Valor de una cookie del pedido, o null
    public function cookie(string $nombre): ?string
    {
        $valor = $this->cookies[$nombre] ?? null;
        return is_string($valor) ? $valor : null;
    }

    // Detrás de Render (y del proxy de Vercel) PHP recibe http; el protocolo real viene en X-Forwarded-Proto
    public function esHttps(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on' || $this->header('X-Forwarded-Proto') === 'https';
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '';
    }
}
