<?php

namespace Paw\Core;

use PDO;

// Conexión a PostgreSQL (Neon). Se arma una sola vez con los datos de config/config.php ('db').
// Todas las consultas del proyecto usan sentencias preparadas.
class Database
{
    public static function configurada(array $db): bool
    {
        return ($db['host'] ?? '') !== '';
    }

    public static function connect(array $db): PDO
    {
        // Neon exige SSL (require). Con un Postgres local sin SSL se usa DB_SSLMODE=disable.
        $ssl = in_array($db['sslmode'], ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)
            ? $db['sslmode'] : 'require';

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=15',
            $db['host'],
            $db['port'],
            $db['name'],
            $ssl,
        );

        return new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
