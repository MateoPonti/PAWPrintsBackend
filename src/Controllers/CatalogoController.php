<?php

namespace Paw\Controllers;

use Paw\Core\App;
use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\LibroRepository;

// Catálogo de libros. Versión base: lista todos los libros y muestra el detalle de uno.
// TODO (Parte 2): búsqueda por GET, paginación y cantidad por página.
class CatalogoController extends Controller
{
    private LibroRepository $libros;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->libros = $app->libros();
    }

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
            throw HttpException::notFound('La página del catálogo no existe');
        }

        $porPagina = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);
        if (!in_array($porPagina, [6, 12, 24], true)) {
            $porPagina = (int) $this->app->config->get('catalogo.per_page');
        }

        $resultados = $this->libros->search($filtros);
        $total = count($resultados);
        // Una búsqueda vacía tiene una primera página válida.
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        if ($pagina > $totalPaginas) {
            throw HttpException::notFound('La página del catálogo no existe');
        }

        $offset = ($pagina - 1) * $porPagina;

        return $this->render('catalogo/index', [
            'title' => 'Catálogo - PawPrints',
            'description' => 'Catálogo completo de libros de PawPrints.',
            'current' => 'catalogo',
            'libros' => array_slice($resultados, $offset, $porPagina),
            'filtros' => $filtros,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'total' => $total,
            'totalPaginas' => $totalPaginas,
            'desde' => $total === 0 ? 0 : $offset + 1,
            'hasta' => min($offset + $porPagina, $total),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $libro = $this->libros->find($id);
        if ($libro === null) {
            throw HttpException::notFound('El libro que buscás no está en el catálogo');
        }
        return $this->render('catalogo/show', [
            'title' => $libro->titulo . ' - PawPrints',
            'description' => $libro->resumen,
            'current' => 'catalogo',
            'libro' => $libro,
        ]);
    }
}
