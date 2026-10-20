<?php

// Mapa de URLs de la aplicación: método HTTP + ruta => [controlador, método].
// $router lo provee Paw\Core\App.

use Paw\Controllers\AdminLibroController;
use Paw\Controllers\AuthController;
use Paw\Controllers\CatalogoController;
use Paw\Controllers\PageController;
use Paw\Controllers\ReservaController;

$router->get('/', [PageController::class, 'home']);
$router->get('/nosotros', [PageController::class, 'nosotros']);
$router->get('/contacto', [PageController::class, 'contacto']);

$router->get('/catalogo', [CatalogoController::class, 'index']);
$router->get('/catalogo/{id}', [CatalogoController::class, 'show']);

$router->get('/reserva', [ReservaController::class, 'form']);
// POST: crea una reserva y manda un correo (no es idempotente) y los datos personales no quedan en la URL
$router->post('/reserva', [ReservaController::class, 'store']);
$router->get('/reserva/confirmada', [ReservaController::class, 'confirmada']);

// Cuentas de usuario. Los POST llevan token anti-CSRF (campo oculto _csrf).
$router->get('/registro', [AuthController::class, 'registroForm']);
$router->post('/registro', [AuthController::class, 'registro']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Alta de libros: solo para usuarios con rol admin
$router->get('/admin/libros/nuevo', [AdminLibroController::class, 'nuevo']);
$router->post('/admin/libros', [AdminLibroController::class, 'store']);
