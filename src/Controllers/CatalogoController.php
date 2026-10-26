<?php

namespace Paw\Controllers;

use Paw\Core\App;
use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\Libro;
use Paw\Models\LibroRepository;

// Catálogo de libros: listado con filtros, orden y paginación, y detalle de un libro.
class CatalogoController extends Controller
{
    private LibroRepository $libros;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->libros = $app->libros();
    }

    // GET /api/libros?genero=&autor=&precio_max=&orden=&page=&per_page=
    public function index(Request $request): Response
    {
        $texto = static function (string $clave, string $default = '') use ($request): string {
            $valor = $request->query($clave, $default);
            return is_string($valor) ? trim($valor) : $default;
        };

        $precioMax = filter_var($texto('precio_max'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);
        $orden = $texto('orden', 'relevancia');
        $filtros = [
            'genero' => $texto('genero'),
            'autor' => $texto('autor'),
            'precio_max' => $precioMax === false ? null : $precioMax,
            'orden' => in_array($orden, ['relevancia', 'precio-asc', 'precio-desc', 'titulo'], true)
                ? $orden : 'relevancia',
        ];

        $pagina = filter_var($request->query('page', '1'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($pagina === false) {
            throw HttpException::notFound('La página del catálogo no existe.');
        }

        $porPagina = filter_var($request->query('per_page'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => (int) $this->app->config->get('catalogo.per_page_max')],
        ]);
        if ($porPagina === false) {
            $porPagina = (int) $this->app->config->get('catalogo.per_page');
        }

        $resultados = $this->libros->search($filtros);
        $total = count($resultados);
        // Una búsqueda vacía tiene una primera página válida.
        $paginas = max(1, (int) ceil($total / $porPagina));
        if ($pagina > $paginas) {
            throw HttpException::notFound('La página del catálogo no existe.');
        }

        $enPagina = array_slice($resultados, ($pagina - 1) * $porPagina, $porPagina);

        return $this->json([
            'items' => array_map(static fn (Libro $libro): array => $libro->toArray(), $enPagina),
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'paginas' => $paginas,
        ]);
    }

    // GET /api/libros/{id}
    public function show(Request $request, string $id): Response
    {
        $libro = $this->libros->find($id);
        if ($libro === null) {
            throw HttpException::notFound('El libro que buscás no está en el catálogo.');
        }
        return $this->json(['libro' => $libro->toArray(true)]);
    }
}
