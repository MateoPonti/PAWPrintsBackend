<?php

namespace Paw\Controllers;

use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\Usuario;
use Paw\Models\UsuarioRepository;

// Gestión de usuarios (rol admin): permisos usuarios.ver y usuarios.editar.
// La baja es lógica (activo = false): no puede ingresar y su sesión deja de valer en el pedido siguiente.
class AdminUsuarioController extends Controller
{
    // GET /api/admin/roles -> {items: [{nombre, descripcion, permisos}]}
    public function roles(Request $request): Response
    {
        $this->exigirPermiso('usuarios.ver');
        return $this->json(['items' => $this->usuarios()->roles()]);
    }

    // GET /api/admin/usuarios?q=&rol=&activo=1|0&page= -> {items, total, pagina, por_pagina, paginas}
    public function index(Request $request): Response
    {
        $this->exigirPermiso('usuarios.ver');

        $texto = static fn (string $clave, int $max): string => is_string($request->query($clave))
            ? mb_substr(trim($request->query($clave)), 0, $max) : '';
        $activo = match ($request->query('activo')) {
            '1' => true,
            '0' => false,
            default => null,
        };
        $pagina = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        $r = $this->usuarios()->listar($texto('q', 80), $texto('rol', 20), $activo, $pagina);
        return $this->json([
            'items' => array_map(static fn (Usuario $u): array => $u->paraAdmin(), $r['items']),
            'total' => $r['total'],
            'pagina' => $pagina,
            'por_pagina' => UsuarioRepository::POR_PAGINA,
            'paginas' => max(1, (int) ceil($r['total'] / UsuarioRepository::POR_PAGINA)),
        ]);
    }

    // GET /api/admin/usuarios/{id} -> {usuario}
    public function show(Request $request, string $id): Response
    {
        $this->exigirPermiso('usuarios.ver');
        return $this->json(['usuario' => $this->existente($id)->paraAdmin()]);
    }

    // PATCH /api/admin/usuarios/{id} {nombre?, rol?, activo?} -> {usuario}. Solo cambia lo que se manda.
    // Un admin no puede cambiarse su propio rol ni darse de baja: se quedaría sin acceso.
    public function update(Request $request, string $id): Response
    {
        $yo = $this->exigirPermiso('usuarios.editar');
        $usuario = $this->existente($id);
        $datos = $request->allInput();
        $cambios = [];
        $errores = [];

        if (array_key_exists('nombre', $datos)) {
            $nombre = is_string($datos['nombre']) ? trim($datos['nombre']) : '';
            if ($nombre === '' || mb_strlen($nombre) > AuthController::NOMBRE_MAX || preg_match('/^\P{Cc}*$/u', $nombre) !== 1) {
                $errores['nombre'] = 'Ingresá un nombre (hasta ' . AuthController::NOMBRE_MAX . ' caracteres).';
            }
            $cambios['nombre'] = $nombre;
        }
        if (array_key_exists('rol', $datos)) {
            $rol = is_string($datos['rol']) ? $datos['rol'] : '';
            if (!$this->usuarios()->existeRol($rol)) {
                $errores['rol'] = 'Ese rol no existe.';
            }
            $cambios['rol'] = $rol;
        }
        if (array_key_exists('activo', $datos)) {
            if (!is_bool($datos['activo'])) {
                $errores['activo'] = 'Tiene que ser true o false.';
            }
            $cambios['activo'] = $datos['activo'] === true;
        }
        if ($cambios === []) {
            $errores['general'] = 'No se mandó ningún cambio (nombre, rol o activo).';
        }
        if ($errores !== []) {
            throw HttpException::invalid($errores);
        }
        if ($usuario->id === $yo->id && (isset($cambios['rol']) || isset($cambios['activo']))) {
            throw new HttpException(409, 'No podés cambiar tu propio rol ni darte de baja.', 'sobre_si_mismo');
        }

        $this->usuarios()->actualizar($usuario->id, $cambios);
        $this->app->logger->info('Usuario {id} modificado por {admin}', ['id' => $usuario->id, 'admin' => $yo->email]);
        return $this->json(['usuario' => $this->usuarios()->find($usuario->id)->paraAdmin()]);
    }

    // DELETE /api/admin/usuarios/{id} -> 204. Baja lógica.
    public function destroy(Request $request, string $id): Response
    {
        $yo = $this->exigirPermiso('usuarios.editar');
        $usuario = $this->existente($id);
        if ($usuario->id === $yo->id) {
            throw new HttpException(409, 'No podés darte de baja a vos mismo.', 'sobre_si_mismo');
        }
        $this->usuarios()->actualizar($usuario->id, ['activo' => false]);
        $this->app->logger->info('Usuario {id} dado de baja por {admin}', ['id' => $usuario->id, 'admin' => $yo->email]);
        return Response::empty(204);
    }

    private function usuarios(): UsuarioRepository
    {
        return new UsuarioRepository($this->app->pdo());
    }

    // El id llega como texto desde la URL: solo dígitos, si no 404
    private function existente(string $id): Usuario
    {
        $usuario = ctype_digit($id) ? $this->usuarios()->find((int) $id) : null;
        if ($usuario === null) {
            throw HttpException::notFound('El usuario no existe.');
        }
        return $usuario;
    }
}
