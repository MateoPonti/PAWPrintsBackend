<?php

// Pruebas del token de acceso (Jwt y Auth).
// Ejecutar desde la raíz: php tests/jwt.php

use Paw\Core\Auth;
use Paw\Core\HttpException;
use Paw\Core\Jwt;
use Paw\Core\Request;
use Paw\Models\Usuario;

require dirname(__DIR__) . '/vendor/autoload.php';

function afirmar(bool $condicion, string $caso): void
{
    if (!$condicion) {
        throw new RuntimeException($caso);
    }
    echo "OK: $caso\n";
}

$secreto = str_repeat('s', 40);
$ahora = time();

$token = Jwt::encode(['sub' => '7', 'exp' => $ahora + 60], $secreto);
afirmar(Jwt::decode($token, $secreto)['sub'] === '7', 'Token válido');
afirmar(Jwt::decode($token, str_repeat('x', 40)) === null, 'Firma con otra clave');
afirmar(Jwt::decode(Jwt::encode(['sub' => '7', 'exp' => $ahora - 1], $secreto), $secreto) === null, 'Token vencido');
afirmar(Jwt::decode(Jwt::encode(['sub' => '7'], $secreto), $secreto) === null, 'Token sin vencimiento');
afirmar(Jwt::decode('abc', $secreto) === null, 'Formato inválido');
afirmar(Jwt::decode('a.b.c', $secreto) === null, 'Partes que no son base64 de JSON');

// Cambiar el contenido sin volver a firmar
[$cabecera, , $firma] = explode('.', $token);
$falso = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'exp' => $ahora + 60])), '+/', '-_'), '=');
afirmar(Jwt::decode("$cabecera.$falso.$firma", $secreto) === null, 'Contenido alterado');

// alg: none
$sinAlg = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=');
afirmar(Jwt::decode("$sinAlg.$falso.", $secreto) === null, 'alg none rechazado');

// Auth
$usuario = new Usuario(7, 'Ana', 'ana@mail.com', 'usuario');
$auth = new Auth($secreto, 3600);
$emitido = $auth->emitir($usuario);
afirmar($emitido['tipo'] === 'Bearer' && $emitido['expira_en'] === 3600, 'Auth emite token Bearer');

$pedido = static fn (string $cabecera): Request => new Request('GET', '/api/me', [], '', ['HTTP_AUTHORIZATION' => $cabecera]);
afirmar($auth->userId($pedido('Bearer ' . $emitido['token'])) === 7, 'Auth lee el id del token');
afirmar($auth->userId($pedido('bearer ' . $emitido['token'])) === 7, 'Bearer sin distinguir mayúsculas');
afirmar($auth->userId($pedido($emitido['token'])) === null, 'Sin la palabra Bearer');
afirmar($auth->userId($pedido('Bearer basura')) === null, 'Token basura');
afirmar($auth->userId(new Request('GET', '/api/me')) === null, 'Pedido sin token');

$sinClave = new Auth('corta');
afirmar(!$sinClave->configurada(), 'Clave corta no es válida');
afirmar($sinClave->userId($pedido('Bearer ' . $emitido['token'])) === null, 'Sin clave configurada no se acepta ningún token');
try {
    $sinClave->emitir($usuario);
    afirmar(false, 'Sin clave configurada no se emiten tokens');
} catch (HttpException $e) {
    afirmar($e->status() === 503, 'Sin clave configurada no se emiten tokens (503)');
}
