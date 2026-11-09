<?php

namespace Paw\Controllers;

use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\ReservaRepository;

// Listado de pedidos (reservas) para el personal de la librería: permiso reservas.ver (roles personal y admin).
class AdminReservaController extends Controller
{
    // GET /api/admin/reservas?q=&page= -> {items, total, pagina, por_pagina, paginas}
    // q busca en el nombre o el correo de quien reservó.
    public function index(Request $request): Response
    {
        $this->exigirPermiso('reservas.ver');

        $texto = $request->query('q', '');
        $texto = is_string($texto) ? mb_substr(trim($texto), 0, 80) : '';
        $pagina = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        $resultado = (new ReservaRepository($this->app->pdo()))->listar($texto, $pagina);
        return $this->json([
            'items' => $resultado['items'],
            'total' => $resultado['total'],
            'pagina' => $pagina,
            'por_pagina' => ReservaRepository::POR_PAGINA,
            'paginas' => max(1, (int) ceil($resultado['total'] / ReservaRepository::POR_PAGINA)),
        ]);
    }
}
