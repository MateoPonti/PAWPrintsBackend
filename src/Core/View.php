<?php

namespace Paw\Core;

// Renderiza las plantillas PHP de views/. Cada página se dibuja dentro de views/layout.php,
// que tiene el encabezado, el menú y el pie comunes a todo el sitio.
class View
{
    // Datos disponibles en todas las plantillas (por ejemplo, el usuario actual para el menú)
    private array $shared = [];

    public function __construct(
        private string $viewsDir,
        private string $basePath = '',
    ) {
    }

    public function share(string $name, mixed $value): void
    {
        $this->shared[$name] = $value;
    }

    // $data se convierte en variables dentro de la plantilla.
    // Variables que usa el layout: $title, $description y $current (ítem activo del menú).
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $data += $this->shared;
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    // URL absoluta dentro del sitio, respetando si la app está en una subcarpeta
    public function url(string $path = '/', array $query = []): string
    {
        // Las portadas de los libros nuevos pueden ser una URL completa
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }
        $url = $this->basePath . '/' . ltrim($path, '/');
        return $query ? $url . '?' . http_build_query($query) : $url;
    }

    private function renderFile(string $template, array $data): string
    {
        $file = $this->viewsDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("No existe la vista $template");
        }
        $view = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }
}
