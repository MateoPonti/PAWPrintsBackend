<?php

namespace Paw\Core;

use Paw\Models\Usuario;

// Autenticación por token (JWT). No hay sesión en el servidor ni cookies: el login devuelve un
// token y el front lo manda en cada pedido como "Authorization: Bearer <token>".
// El token solo dice quién es el usuario (sub); el rol se consulta en la base en cada pedido.
class Auth
{
    public const SECRETO_MIN = 32;

    public function __construct(
        private string $secreto,
        private int $duracion = 28800,
    ) {
    }

    public function configurada(): bool
    {
        return strlen($this->secreto) >= self::SECRETO_MIN;
    }

    /** @return array{token: string, tipo: string, expira_en: int} expira_en = segundos de validez */
    public function emitir(Usuario $usuario): array
    {
        if (!$this->configurada()) {
            throw HttpException::unavailable(
                'El ingreso no está disponible en este momento. Probá de nuevo más tarde.',
                'auth_no_configurada',
            );
        }

        $ahora = time();
        $token = Jwt::encode([
            'iss' => 'pawprints',
            'sub' => (string) $usuario->id,
            'iat' => $ahora,
            'exp' => $ahora + $this->duracion,
        ], $this->secreto);

        return ['token' => $token, 'tipo' => 'Bearer', 'expira_en' => $this->duracion];
    }

    // Id del usuario del token del pedido, o null si no hay token o no es válido
    public function userId(Request $request): ?int
    {
        $token = $request->bearerToken();
        if ($token === null || !$this->configurada()) {
            return null;
        }
        $datos = Jwt::decode($token, $this->secreto);
        $sub = $datos['sub'] ?? null;
        return is_string($sub) && ctype_digit($sub) ? (int) $sub : null;
    }
}
