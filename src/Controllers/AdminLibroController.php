<?php

namespace Paw\Controllers;

use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\LibroDbRepository;
use Paw\Models\LibroValidator;

// Alta de libros. Solo entran los usuarios con rol 'admin' (se consulta en la base en cada pedido).
class AdminLibroController extends Controller
{
    public function nuevo(Request $request): Response
    {
        if (($respuesta = $this->exigirAdmin()) !== null) {
            return $respuesta;
        }
        return $this->renderForm(LibroValidator::valores([]), []);
    }

    public function store(Request $request): Response
    {
        if (($respuesta = $this->exigirAdmin()) !== null) {
            return $respuesta;
        }
        $this->exigirCsrf($request);

        $valores = LibroValidator::valores($request->allInput());
        $errores = LibroValidator::validar($valores);
        if ($errores !== []) {
            return $this->renderForm($valores, $errores, 422);
        }

        $libros = $this->app->libros();
        if (!$libros instanceof LibroDbRepository) {
            throw new HttpException(503, 'No se pueden cargar libros: falta configurar la base de datos.');
        }
        $valores['precio'] = (int) $valores['precio'];
        $id = $libros->crear($valores);
        $this->app->logger->info('Libro creado: {id} por {usuario}', ['id' => $id, 'usuario' => $this->app->usuario()?->email]);

        // Post/Redirect/Get: al recargar no se vuelve a crear el libro
        return $this->redirect('/catalogo/' . rawurlencode($id), 303);
    }

    // null si es admin; una redirección al login si no inició sesión; 403 si no es admin
    private function exigirAdmin(): ?Response
    {
        $this->exigirBase();
        $usuario = $this->app->usuario();
        if ($usuario === null) {
            return $this->redirect('/login');
        }
        if (!$usuario->esAdmin()) {
            throw new HttpException(403, 'No tenés permisos para cargar libros.');
        }
        return null;
    }

    private function renderForm(array $valores, array $errores, int $status = 200): Response
    {
        return $this->render('admin/libro_nuevo', [
            'title' => 'Cargar libro - PawPrints',
            'description' => 'Alta de libros del catálogo.',
            'current' => 'admin',
            'valores' => $valores,
            'errores' => $errores,
        ], $status);
    }
}
