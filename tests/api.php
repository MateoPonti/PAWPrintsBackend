<?php

// Prueba de punta a punta de la API sin levantar un servidor: arma pedidos y mira las respuestas.
// Corre sin base de datos y sin mandar correos (catálogo desde data/libros.json, correos al log).
// Ejecutar desde la raíz: php tests/api.php

use Paw\Core\App;
use Paw\Core\Request;
use Paw\Core\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

// Fija el ambiente de la prueba: lo que ya está definido gana sobre el .env (ver Config::loadEnv)
foreach ([
    'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'DB_HOST' => '', 'MAIL_API_KEY' => '',
    'FRONT_URL' => 'https://front.ejemplo.com', 'JWT_SECRET' => str_repeat('t', 40),
    'LOG_PATH' => sys_get_temp_dir() . '/pawprints-test.log', 'LOG_LEVEL' => 'emergency',
] as $clave => $valor) {
    putenv("$clave=$valor");
}

function pedir(string $metodo, string $ruta, array $consulta = [], mixed $cuerpo = null, array $cabeceras = []): Response
{
    $server = ['HTTP_ORIGIN' => 'https://front.ejemplo.com'];
    foreach ($cabeceras as $nombre => $valor) {
        $server['HTTP_' . strtoupper(str_replace('-', '_', $nombre))] = $valor;
    }
    $crudo = is_string($cuerpo) ? $cuerpo : ($cuerpo === null ? '' : json_encode($cuerpo));
    return (new App(dirname(__DIR__), new Request($metodo, $ruta, $consulta, $crudo, $server)))->handle();
}

function datos(Response $respuesta): array
{
    return json_decode($respuesta->body(), true) ?? [];
}

function afirmar(bool $condicion, string $caso, ?Response $respuesta = null): void
{
    if (!$condicion) {
        throw new RuntimeException($caso . ($respuesta ? ' -> ' . $respuesta->status() . ' ' . $respuesta->body() : ''));
    }
    echo "OK: $caso\n";
}

// Estado y CORS
$r = pedir('GET', '/api/health');
afirmar($r->status() === 200 && datos($r)['estado'] === 'ok', 'GET /api/health', $r);
afirmar(str_starts_with($r->headers()['Content-Type'], 'application/json'), 'Responde JSON');
afirmar(($r->headers()['Access-Control-Allow-Origin'] ?? '') === 'https://front.ejemplo.com', 'CORS para el origen del front', $r);

$r = pedir('OPTIONS', '/api/login');
afirmar($r->status() === 204 && isset($r->headers()['Access-Control-Allow-Methods']), 'Preflight OPTIONS', $r);

// Catálogo
$r = pedir('GET', '/api/libros');
$c = datos($r);
afirmar($r->status() === 200 && $c['total'] === 20 && count($c['items']) === 6 && $c['paginas'] === 4, 'Listado: 20 libros, 6 por página', $r);
afirmar(array_keys($c['items'][0]) === ['id', 'titulo', 'autor', 'genero', 'precio', 'resumen', 'portada'], 'Listado: campos de la ficha', $r);
afirmar(array_keys($c) === ['items', 'total', 'pagina', 'por_pagina', 'paginas'], 'Listado: forma de la respuesta', $r);

$c = datos(pedir('GET', '/api/libros', ['per_page' => '100']));
afirmar(count($c['items']) === 20 && $c['paginas'] === 1, 'per_page=100 trae todo');
$c = datos(pedir('GET', '/api/libros', ['per_page' => '3']));
afirmar(count($c['items']) === 3 && $c['por_pagina'] === 3, 'per_page=3');
$c = datos(pedir('GET', '/api/libros', ['per_page' => '500']));
afirmar($c['por_pagina'] === 6, 'per_page fuera de rango vuelve al valor por defecto');
$c = datos(pedir('GET', '/api/libros', ['genero' => 'poesia', 'orden' => 'precio-desc']));
afirmar($c['total'] > 0 && $c['items'][0]['genero'] === 'poesia', 'Filtro por género');
$r = pedir('GET', '/api/libros', ['page' => '999']);
afirmar($r->status() === 404 && datos($r)['error'] === 'no_encontrado', 'Página inexistente: 404', $r);
$r = pedir('GET', '/api/libros', ['page' => 'abc']);
afirmar($r->status() === 404, 'Página inválida: 404', $r);
$r = pedir('GET', '/api/libros', ['autor' => 'zzzzzz']);
afirmar($r->status() === 200 && datos($r)['total'] === 0 && datos($r)['paginas'] === 1, 'Búsqueda sin resultados: 200 con 0 libros', $r);

$r = pedir('GET', '/api/libros/rayuela');
$l = datos($r)['libro'] ?? [];
afirmar($r->status() === 200 && $l['id'] === 'rayuela' && isset($l['descripcion'], $l['autor_bio'], $l['portada']), 'Detalle de un libro', $r);
$r = pedir('GET', '/api/libros/no-existe');
afirmar($r->status() === 404 && isset(datos($r)['mensaje']), 'Libro inexistente: 404', $r);

// Rutas y métodos
$r = pedir('GET', '/api/nada');
afirmar($r->status() === 404 && datos($r)['error'] === 'no_encontrado', 'Ruta inexistente: 404 JSON', $r);
$r = pedir('GET', '/api/reservas');
afirmar($r->status() === 405 && isset($r->headers()['Allow']), 'Método no permitido: 405 con Allow', $r);

// Reserva
$reserva = ['nombre' => 'Ana Pérez', 'email' => 'ana@mail.com', 'telefono' => '', 'items' => [['libro' => 'rayuela', 'cantidad' => 2]]];
$r = pedir('POST', '/api/reservas', [], $reserva);
afirmar($r->status() === 201 && datos($r)['reserva']['email'] === 'ana@mail.com' && datos($r)['reserva']['items'][0]['cantidad'] === 2, 'Reserva válida: 201', $r);

$r = pedir('POST', '/api/reservas', [], ['nombre' => '', 'email' => 'x', 'items' => [['libro' => 'no-existe', 'cantidad' => 99]]]);
$e = datos($r)['errores'] ?? [];
afirmar($r->status() === 422 && isset($e['nombre'], $e['email'], $e['items.0.libro'], $e['items.0.cantidad']), 'Reserva inválida: 422 con errores por campo', $r);
$r = pedir('POST', '/api/reservas', [], ['nombre' => 'Ana', 'email' => 'ana@mail.com', 'items' => []]);
afirmar($r->status() === 422 && isset(datos($r)['errores']['items']), 'Reserva sin libros: error en items', $r);
$r = pedir('POST', '/api/reservas', [], '{no es json');
afirmar($r->status() === 400 && datos($r)['error'] === 'solicitud_invalida', 'JSON roto: 400', $r);
$r = pedir('POST', '/api/reservas', [], '[1,2]');
afirmar($r->status() === 400, 'JSON que no es un objeto: 400', $r);

// Cuentas: sin base de datos no están disponibles
foreach ([['POST', '/api/registro'], ['POST', '/api/login'], ['GET', '/api/me'], ['POST', '/api/admin/libros']] as [$m, $ruta]) {
    $r = pedir($m, $ruta, [], $m === 'POST' ? [] : null);
    afirmar($r->status() === 503 && datos($r)['error'] === 'base_no_configurada', "$m $ruta sin base de datos: 503", $r);
}

echo "\nTodo OK\n";
