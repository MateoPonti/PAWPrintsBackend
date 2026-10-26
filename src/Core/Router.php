<?php

namespace Paw\Core;

// Mapea método + URL en un controlador. Las rutas se registran en config/routes.php.
// Admite parámetros en la URL: '/catalogo/{id}' le pasa $id al método del controlador.
class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [$method, $path, $handler];
    }

    // Devuelve [handler, parámetros] o lanza 404 / 405
    public function match(Request $request): array
    {
        $allowed = [];
        foreach ($this->routes as [$method, $path, $handler]) {
            $params = $this->matchPath($path, $request->path());
            if ($params === null) {
                continue;
            }
            if ($method === $request->method() || ($method === 'GET' && $request->method() === 'HEAD')) {
                return [$handler, $params];
            }
            $allowed[] = $method;
        }

        if ($allowed) {
            throw HttpException::methodNotAllowed(array_unique($allowed));
        }
        throw HttpException::notFound();
    }

    private function matchPath(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }
        return array_map('rawurldecode', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
    }
}
