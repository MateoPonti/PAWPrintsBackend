<?php

// Mapa de URLs de la API: método HTTP + ruta => [controlador, método].
// $router lo provee Paw\Core\App. Todas las respuestas son JSON, salvo las imágenes de las portadas.

use Paw\Controllers\AdminLibroController;
use Paw\Controllers\AuthController;
use Paw\Controllers\CatalogoController;
use Paw\Controllers\InfoController;
use Paw\Controllers\PortadaController;
use Paw\Controllers\ReservaController;

// Estado del servicio (sirve como health check del hosting)
$router->get('/', [InfoController::class, 'index']);
$router->get('/api/health', [InfoController::class, 'health']);

// Catálogo (público)
$router->get('/api/libros', [CatalogoController::class, 'index']);
$router->get('/api/libros/{id}', [CatalogoController::class, 'show']);

// Portadas subidas desde el alta de libros (público: las usa el <img> del catálogo)
$router->get('/api/portadas/{id}', [PortadaController::class, 'show']);

// Reservas (público): crea la reserva y avisa al personal por correo
$router->post('/api/reservas', [ReservaController::class, 'store']);

// Cuentas. El login devuelve un token que el front manda como "Authorization: Bearer <token>"
$router->post('/api/registro', [AuthController::class, 'registro']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->get('/api/me', [AuthController::class, 'me']);

// Alta de libros y subida de su portada: solo para usuarios con rol admin
$router->post('/api/admin/libros', [AdminLibroController::class, 'store']);
$router->post('/api/admin/portadas', [PortadaController::class, 'store']);
