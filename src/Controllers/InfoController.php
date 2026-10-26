<?php

namespace Paw\Controllers;

use Paw\Core\Request;
use Paw\Core\Response;

// Estado del servicio. No toca la base: responde rápido aunque la base esté dormida.
class InfoController extends Controller
{
    public function index(Request $request): Response
    {
        $version = @file_get_contents(dirname(__DIR__, 2) . '/VERSION');

        return $this->json([
            'nombre' => 'PawPrints API',
            'version' => $version === false ? null : trim($version),
            'estado' => 'ok',
        ]);
    }

    public function health(Request $request): Response
    {
        return $this->json([
            'estado' => 'ok',
            'base_de_datos' => $this->app->dbConfigurada() ? 'configurada' : 'no configurada',
        ]);
    }
}
