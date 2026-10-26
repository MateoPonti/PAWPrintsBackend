<?php

// Configuración de la aplicación. Los valores sensibles o que cambian según el
// ambiente se leen del archivo .env (ver .env.example) y acá se les da un valor por defecto.

$env = fn (string $key, mixed $default = null) => getenv($key) !== false ? getenv($key) : $default;

return [
    'app' => [
        'name' => 'PawPrints',
        // development | production
        'env' => $env('APP_ENV', 'production'),
        // En producción tiene que ser false para no mostrar detalles de los errores
        'debug' => filter_var($env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
        'url' => $env('APP_URL', 'http://localhost:8000'),
    ],

    // Orígenes desde los que el navegador puede usar la API (la dirección donde se publica el front).
    // Varios se separan con coma. En development también se acepta cualquier puerto de localhost.
    'cors' => [
        'origins' => $env('FRONT_URL', 'http://localhost:5500,http://127.0.0.1:5500'),
    ],

    // Token de acceso (JWT). JWT_SECRET es obligatorio para ingresar: mínimo 32 caracteres.
    'auth' => [
        'secret' => $env('JWT_SECRET', ''),
        // Segundos de validez del token (por defecto 8 horas)
        'ttl' => (int) $env('JWT_TTL', 28800),
    ],

    'log' => [
        'path' => $env('LOG_PATH', __DIR__ . '/../logs/app.log'),
        // debug | info | notice | warning | error | critical | alert | emergency
        'level' => $env('LOG_LEVEL', 'info'),
    ],

    // Base de datos PostgreSQL (Neon). Con DB_HOST vacío la API sigue usando data/libros.json
    // (sin registro de usuarios ni alta de libros), como antes de agregar la base.
    'db' => [
        'host' => $env('DB_HOST', ''),
        'port' => $env('DB_PORT', '5432'),
        'name' => $env('DB_NAME', 'neondb'),
        'user' => $env('DB_USER', 'postgres'),
        'pass' => $env('DB_PASS', ''),
        'sslmode' => $env('DB_SSLMODE', 'require'),
    ],

    'catalogo' => [
        'data' => __DIR__ . '/../data/libros.json',
        // Libros por página si el pedido no manda per_page (o manda uno inválido), y máximo permitido
        'per_page' => 6,
        'per_page_max' => 100,
    ],

    // Lo usa el procesamiento de la reserva
    'mail' => [
        'to' => $env('MAIL_TO', 'reservas@pawprints.com.ar'),
        'from' => $env('MAIL_FROM', 'no-reply@pawprints.com.ar'),
        'from_name' => $env('MAIL_FROM_NAME', 'PawPrints'),
        'smtp' => [
            'host' => $env('SMTP_HOST', ''),
            'port' => (int) $env('SMTP_PORT', 587),
            'user' => $env('SMTP_USER', ''),
            'pass' => $env('SMTP_PASS', ''),
            'secure' => $env('SMTP_SECURE', 'tls'),
        ],
    ],
];
