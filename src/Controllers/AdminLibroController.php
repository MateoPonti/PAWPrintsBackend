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
    // POST /api/admin/libros -> 201 {libro}
    public function store(Request $request): Response
    {
        $this->exigirAdmin();

        $valores = LibroValidator::valores($request->allInput());
        $errores = LibroValidator::validar($valores);
        if ($errores !== []) {
            throw HttpException::invalid($errores);
        }

        $libros = $this->app->libros();
        if (!$libros instanceof LibroDbRepository) {
            throw HttpException::unavailable(
                'No se pueden cargar libros: falta configurar la base de datos.',
                'base_no_configurada',
            );
        }
        $valores['precio'] = (int) $valores['precio'];
        $id = $libros->crear($valores);
        $this->app->logger->info('Libro creado: {id} por {usuario}', ['id' => $id, 'usuario' => $this->app->usuario()?->email]);

        return $this->json(['libro' => $libros->find($id)->toArray(true)], 201)
            ->withHeader('Location', '/api/libros/' . rawurlencode($id));
    }
}
