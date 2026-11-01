<?php

// Pruebas de la subida de portadas (PortadaValidator).
// Ejecutar desde la raíz: php tests/portada.php

use Paw\Models\PortadaValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

function afirmar(bool $condicion, string $caso): void
{
    if (!$condicion) {
        throw new RuntimeException($caso);
    }
    echo "OK: $caso\n";
}

// PNG de 1x1 píxel
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

afirmar(PortadaValidator::validar('image/png', $png) === '', 'PNG válido');
afirmar(PortadaValidator::validar('image/png', '') !== '', 'Cuerpo vacío');
afirmar(PortadaValidator::validar('image/jpeg', $png) !== '', 'El tipo declarado no coincide con el contenido');
afirmar(PortadaValidator::validar('image/png', 'no soy una imagen') !== '', 'Texto con tipo image/png');
afirmar(PortadaValidator::validar('image/svg+xml', $svg) !== '', 'SVG no se acepta');
afirmar(PortadaValidator::validar('application/json', '{}') !== '', 'JSON no se acepta');
afirmar(PortadaValidator::validar('image/png', $png . str_repeat("\0", PortadaValidator::MAX_BYTES)) !== '', 'Más de 1 MB');

echo "\nTodo OK\n";
