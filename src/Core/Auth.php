<?php

namespace Paw\Core;

use Closure;
use PDO;
use Paw\Models\Usuario;

// Autenticación con la sesión de PHP ($_SESSION), guardada en la base (ver SesionDb).
// El navegador solo tiene la cookie "pawprints_sesion" con el id de la sesión: HttpOnly (JavaScript no la
// puede leer aunque hubiera un XSS), Secure y SameSite=Strict. Como el navegador manda la cookie solo,
// los pedidos con sesión que cambian datos llevan además el header X-CSRF-Token (ver exigirCsrf).
// En la sesión solo se guarda quién es el usuario; el rol se consulta en la base en cada pedido.
class Auth
{
    public const COOKIE = 'pawprints_sesion';

    /** @param Closure(): PDO $pdo */
    public function __construct(
        private Closure $pdo,
        private int $duracion = 28800,
    ) {
    }

    // Ingreso: id de sesión nuevo (evita fijación de sesión) y se guardan el usuario y el token CSRF.
    /** @return array{csrf: string, expira_en: int} expira_en = segundos de validez */
    public function iniciar(Usuario $usuario, Request $request): array
    {
        $this->arrancar($request);
        session_regenerate_id(true); // true: borra la sesión vieja
        $_SESSION = [
            'usuario_id' => (int) $usuario->id,
            'csrf' => bin2hex(random_bytes(32)),
            'vence' => time() + $this->duracion,
        ];
        return ['csrf' => $_SESSION['csrf'], 'expira_en' => $this->duracion];
    }

    // Salir en los tres pasos de siempre: vaciar $_SESSION, vencer la cookie y destruir la sesión
    public function cerrar(Request $request): void
    {
        if ($this->cookieValida($request)) {
            $this->arrancar($request);
        }
        $_SESSION = [];
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600, // vencida: el navegador la borra
            'path' => '/',
            'secure' => $request->esHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy(); // borra la fila de la tabla sesiones: el id deja de servir aunque alguien lo haya copiado
        }
    }

    // Id del usuario de la sesión del pedido, o null si no hay sesión o venció
    public function userId(Request $request): ?int
    {
        if (!$this->cookieValida($request)) {
            return null; // sin cookie no se arranca la sesión: los pedidos anónimos no tocan la tabla
        }
        $this->arrancar($request);
        $id = $_SESSION['usuario_id'] ?? null;
        if (!is_int($id) || ($_SESSION['vence'] ?? 0) < time()) {
            $_SESSION = []; // vencida o vacía: al terminar el pedido SesionDb borra la fila
            return null;
        }
        return $id;
    }

    // Token CSRF de la sesión. Se genera al azar en el ingreso y otro sitio no lo puede conocer.
    public function csrf(Request $request): ?string
    {
        return $this->userId($request) !== null && is_string($_SESSION['csrf'] ?? null) ? $_SESSION['csrf'] : null;
    }

    // Para los POST con sesión: el header X-CSRF-Token tiene que coincidir con el de la sesión
    public function exigirCsrf(Request $request): void
    {
        $esperado = $this->csrf($request);
        $recibido = $request->header('X-CSRF-Token') ?? '';
        if ($esperado === null || !hash_equals($esperado, $recibido)) {
            throw new HttpException(403, 'La sesión no es válida. Recargá la página e intentá de nuevo.', 'csrf');
        }
    }

    // Arranca la sesión de PHP la primera vez que se necesita en el pedido
    private function arrancar(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');  // no acepta ids inventados por el cliente
        ini_set('session.use_only_cookies', '1'); // el id nunca viaja en la URL
        ini_set('session.gc_maxlifetime', (string) $this->duracion);
        session_name(self::COOKIE);
        session_set_cookie_params([
            'lifetime' => $this->duracion,
            'path' => '/',
            'secure' => $request->esHttps(), // en local, con http, el navegador descartaría una cookie Secure
            'httponly' => true,
            'samesite' => 'Strict',          // no viaja en pedidos que se originan en otro sitio (primera barrera contra CSRF)
        ]);
        session_cache_limiter(''); // las cabeceras de caché las decide cada Response
        session_set_save_handler(new SesionDb($this->pdo, $this->duracion), true);
        session_start();
    }

    // Un id con otros caracteres (por ejemplo, un token viejo) haría que PHP emita un warning
    private function cookieValida(Request $request): bool
    {
        $c = $request->cookie(self::COOKIE);
        return $c !== null && preg_match('/^[A-Za-z0-9,-]{22,256}$/', $c) === 1;
    }
}
