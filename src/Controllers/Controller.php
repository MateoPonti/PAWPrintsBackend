<?php

namespace Paw\Controllers;

use Paw\Core\App;
use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;

// Base de todos los controladores: da acceso a la configuración, el logger y las vistas.
abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->app->view->render($template, $data), $status);
    }

    // 503 si no hay base de datos configurada (las cuentas y el alta de libros la necesitan)
    protected function exigirBase(): void
    {
        if (!$this->app->dbConfigurada()) {
            throw new HttpException(503, 'Esta función no está disponible: falta configurar la base de datos.');
        }
    }

    // Todo formulario POST que modifica datos tiene que traer el token que generó la sesión
    protected function exigirCsrf(Request $request): void
    {
        if (!$this->app->auth->csrfValido($request->input('_csrf'))) {
            throw new HttpException(403, 'El formulario venció. Volvé a cargar la página e intentá de nuevo.');
        }
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect($this->app->view->url($path), $status);
    }
}
