<?php

namespace Paw\Core;

// JSON Web Token firmado con HMAC-SHA256 (HS256), sin dependencias.
// Un token son tres partes en base64url separadas por puntos: cabecera.datos.firma.
final class Jwt
{
    public static function encode(array $datos, string $secreto): string
    {
        $cabecera = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $cuerpo = self::base64url(json_encode($datos, JSON_THROW_ON_ERROR));
        $firma = self::base64url(hash_hmac('sha256', "$cabecera.$cuerpo", $secreto, true));
        return "$cabecera.$cuerpo.$firma";
    }

    /**
     * Devuelve los datos del token, o null si la firma no coincide, el algoritmo no es HS256,
     * el formato está mal o el token venció (claim "exp").
     */
    public static function decode(string $token, string $secreto): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }
        [$cabecera, $cuerpo, $firma] = $partes;

        $encabezado = json_decode((string) self::decodeBase64url($cabecera), true);
        if (!is_array($encabezado) || ($encabezado['alg'] ?? null) !== 'HS256') {
            return null; // también rechaza alg: none
        }

        $esperada = self::base64url(hash_hmac('sha256', "$cabecera.$cuerpo", $secreto, true));
        if (!hash_equals($esperada, $firma)) {
            return null;
        }

        $datos = json_decode((string) self::decodeBase64url($cuerpo), true);
        if (!is_array($datos)) {
            return null;
        }
        if (!isset($datos['exp']) || !is_int($datos['exp']) || $datos['exp'] < time()) {
            return null;
        }
        return $datos;
    }

    private static function base64url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }

    private static function decodeBase64url(string $texto): string|false
    {
        return base64_decode(strtr($texto, '-_', '+/'), true);
    }
}
