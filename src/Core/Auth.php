<?php

namespace Paw\Core;

// Sesión del usuario y token anti-CSRF. La sesión solo se abre cuando hace falta:
// si el visitante no tiene cookie de sesión (no ingresó) no se toca la sesión ni la base.
class Auth
{
    public function __construct(private bool $https = false)
    {
    }

    // Id del usuario que inició sesión, o null
    public function userId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!isset($_COOKIE[session_name()])) {
                return null;
            }
            $this->start();
        }
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public function login(int $userId): void
    {
        $this->start();
        session_regenerate_id(true); // evita fijación de sesión
        $_SESSION['user_id'] = $userId;
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }

    // Token que va en un campo oculto de cada formulario que modifica datos
    public function csrfToken(): string
    {
        $this->start();
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public function csrfValido(mixed $token): bool
    {
        if (!is_string($token) || $token === '' || !isset($_COOKIE[session_name()])) {
            return false;
        }
        $this->start();
        $esperado = $_SESSION['csrf'] ?? null;
        return is_string($esperado) && hash_equals($esperado, $token);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
