<?php

// Pruebas de los orígenes permitidos (Cors).
// Ejecutar desde la raíz: php tests/cors.php

use Paw\Core\Cors;
use Paw\Core\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

function afirmar(bool $condicion, string $caso): void
{
    if (!$condicion) {
        throw new RuntimeException($caso);
    }
    echo "OK: $caso\n";
}

$produccion = new Cors('https://usuario.github.io/, https://pawprints.com.ar');
afirmar($produccion->permitido('https://usuario.github.io'), 'Origen de la lista (la barra final se ignora)');
afirmar($produccion->permitido('https://PawPrints.com.ar'), 'Mayúsculas en el origen');
afirmar(!$produccion->permitido('https://otro.com'), 'Origen que no está en la lista');
afirmar(!$produccion->permitido('https://usuario.github.io.malo.com'), 'Dominio que solo empieza igual');
afirmar(!$produccion->permitido('http://localhost:5500'), 'En producción localhost no se acepta solo');
afirmar(!$produccion->permitido(null), 'Pedido sin Origin');
afirmar($produccion->cabeceras('https://otro.com') === [], 'Sin cabeceras para un origen no permitido');
afirmar($produccion->cabeceras('https://usuario.github.io')['Access-Control-Allow-Origin'] === 'https://usuario.github.io', 'Devuelve el origen del pedido');

$desarrollo = new Cors('http://localhost:5500', true);
afirmar($desarrollo->permitido('http://localhost:3000'), 'En desarrollo, cualquier puerto de localhost');
afirmar($desarrollo->permitido('http://127.0.0.1:8080'), 'En desarrollo, cualquier puerto de 127.0.0.1');
afirmar(!$desarrollo->permitido('http://localhost.malo.com'), 'En desarrollo, no cualquier dominio que empiece con localhost');

$todos = new Cors('*');
afirmar($todos->cabeceras('https://cualquiera.com')['Access-Control-Allow-Origin'] === '*', 'Comodín');

$pre = $produccion->preflight(new Request('OPTIONS', '/api/login', [], '', ['HTTP_ORIGIN' => 'https://usuario.github.io']));
afirmar($pre->status() === 204 && str_contains($pre->headers()['Access-Control-Allow-Headers'], 'Authorization'), 'Preflight permite Authorization');
$preMalo = $produccion->preflight(new Request('OPTIONS', '/api/login', [], '', ['HTTP_ORIGIN' => 'https://otro.com']));
afirmar($preMalo->status() === 204 && !isset($preMalo->headers()['Access-Control-Allow-Origin']), 'Preflight de origen no permitido sin cabeceras CORS');
