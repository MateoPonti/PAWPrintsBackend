<?php

namespace Paw\Controllers;

use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\OpenLibrary;

// Autocompletar el alta de libros con Open Library. Mismo permiso que el alta (libros.crear).
// El front no llama a Open Library directamente: pasa por acá, así la conexión con la API está en un solo lugar.
class OpenLibraryController extends Controller
{
    public const TEXTO_MIN = 3;
    public const TEXTO_MAX = 100;

    // GET /api/admin/openlibrary?q=rayuela cortazar (o un ISBN) -> {items: [{clave, titulo, autor, anio, portada}]}
    public function buscar(Request $request): Response
    {
        $this->exigirPermiso('libros.crear');

        $texto = $request->query('q', '');
        $texto = is_string($texto) ? trim($texto) : '';
        if (mb_strlen($texto) < self::TEXTO_MIN || mb_strlen($texto) > self::TEXTO_MAX) {
            throw HttpException::invalid(['q' => 'Escribí entre ' . self::TEXTO_MIN . ' y ' . self::TEXTO_MAX . ' caracteres: el ISBN, o el título y el autor.']);
        }

        return $this->json(['items' => (new OpenLibrary($this->app->logger))->buscar($texto)]);
    }

    // GET /api/admin/openlibrary/obra?clave=/works/OL14860424W -> {libro: {titulo, autor, descripcion, autor_bio, portada}}
    public function obra(Request $request): Response
    {
        $this->exigirPermiso('libros.crear');

        $clave = $request->query('clave', '');
        $libro = is_string($clave) ? (new OpenLibrary($this->app->logger))->obra($clave) : null;
        if ($libro === null) {
            throw HttpException::notFound('No encontramos ese libro en Open Library.');
        }
        return $this->json(['libro' => $libro]);
    }
}
