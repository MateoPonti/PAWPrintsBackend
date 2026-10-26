<?php

// Mapa de URLs de la API: método HTTP + ruta => [controlador, método].
// $router lo provee Paw\Core\App. Todas las respuestas son JSON.

use Paw\Controllers\AdminLibroController;
use Paw\Controllers\AuthController;
use Paw\Controllers\CatalogoController;
use Paw\Controllers\InfoController;
use Paw\Controllers\ReservaController;

// Estado del servicio (sirve como health check del hosting)
$router->get('/', [InfoController::class, 'index']);
$router->get('/api/health', [InfoController::class, 'health']);

// Catálogo (público)
$router->get('/api/libros', [CatalogoController::class, 'index']);
$router->get('/api/libros/{id}', [CatalogoController::class, 'show']);

// Reservas (público): crea la reserva y avisa al personal por correo
$router->post('/api/reservas', [ReservaController::class, 'store']);

// Cuentas. El login devuelve un token que el front manda como "Authorization: Bearer <token>"
$router->post('/api/registro', [AuthController::class, 'registro']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->get('/api/me', [AuthController::class, 'me']);

// Alta de libros: solo para usuarios con rol admin
$router->post('/api/admin/libros', [AdminLibroController::class, 'store']);
