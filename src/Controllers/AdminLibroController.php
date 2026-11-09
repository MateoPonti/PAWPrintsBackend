<?php

namespace Paw\Controllers;

use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\LibroDbRepository;
use Paw\Models\LibroValidator;
use Paw\Models\OpenLibrary;

// Alta de libros. Pide el permiso 'libros.crear' (lo tiene el rol admin; se consulta en la base en cada pedido).
// Si el libro llega sin tapa (ni subida ni dirección), se busca una en Open Library por título y autor.
class AdminLibroController extends Controller
{
    // POST /api/admin/libros -> 201 {libro}
    public function store(Request $request): Response
    {
        $this->exigirPermiso('libros.crear');

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
        if ($valores['imagen_url'] === '') {
            // Si Open Library no tiene tapa o no responde, queda vacío y se usa la portada por defecto
            $valores['imagen_url'] = (new OpenLibrary($this->app->logger))->tapaPara($valores['titulo'], $valores['autor']);
        }
        $id = $libros->crear($valores);
        $this->app->logger->info('Libro creado: {id} por {usuario}', ['id' => $id, 'usuario' => $this->app->usuario()?->email]);

        return $this->json(['libro' => $libros->find($id)->toArray(true)], 201)
            ->withHeader('Location', '/api/libros/' . rawurlencode($id));
    }
}
