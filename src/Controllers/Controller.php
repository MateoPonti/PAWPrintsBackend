<?php

namespace Paw\Controllers;

use Paw\Core\App;
use Paw\Core\HttpException;
use Paw\Core\Response;
use Paw\Models\Usuario;

// Base de todos los controladores: da acceso a la configuración, el logger y la autenticación.
abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    protected function json(mixed $datos, int $status = 200): Response
    {
        return Response::json($datos, $status);
    }

    // 503 si no hay base de datos configurada (las cuentas y el alta de libros la necesitan)
    protected function exigirBase(): void
    {
        if (!$this->app->dbConfigurada()) {
            throw HttpException::unavailable(
                'Esta función no está disponible: falta configurar la base de datos.',
                'base_no_configurada',
            );
        }
    }

    // El usuario de la sesión, o 401 si no inició sesión, venció o el usuario ya no existe.
    // Si el pedido cambia datos, además exige el token CSRF (403).
    protected function exigirUsuario(): Usuario
    {
        $this->exigirBase();
        $usuario = $this->app->usuario();
        if ($usuario === null) {
            throw HttpException::unauthorized();
        }
        $this->app->exigirCsrfSiEscribe();
        return $usuario;
    }

    // 401 si no inició sesión, 403 si su rol no tiene ninguno de los permisos pedidos.
    // Los controladores piden permisos ('libros.crear'), no roles: los permisos de cada rol están en la base.
    protected function exigirPermiso(string ...$permisos): Usuario
    {
        $usuario = $this->exigirUsuario();
        if (array_intersect($permisos, $this->app->permisos()) === []) {
            throw HttpException::forbidden('No tenés permisos para hacer esto.');
        }
        return $usuario;
    }
}
