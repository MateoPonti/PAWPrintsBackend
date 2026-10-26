<?php

namespace Paw\Core;

// CORS: el front se sirve desde otro origen que la API, así que el navegador solo deja usarla
// si la respuesta autoriza ese origen. Los orígenes permitidos salen de FRONT_URL (separados por coma).
// En desarrollo también se acepta cualquier puerto de localhost / 127.0.0.1.
class Cors
{
    /** @var string[] */
    private array $origenes;

    public function __construct(string $lista, private bool $permitirLocal = false)
    {
        $this->origenes = array_values(array_filter(array_map(
            static fn (string $o): string => strtolower(rtrim(trim($o), '/')),
            explode(',', $lista),
        ), static fn (string $o): bool => $o !== ''));
    }

    public function permitido(?string $origen): bool
    {
        if ($origen === null || $origen === '') {
            return false;
        }
        $origen = strtolower(rtrim($origen, '/'));
        if (in_array('*', $this->origenes, true) || in_array($origen, $this->origenes, true)) {
            return true;
        }
        return $this->permitirLocal && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origen) === 1;
    }

    /** @return array<string, string> Cabeceras CORS para el origen del pedido (vacío si no está permitido) */
    public function cabeceras(?string $origen): array
    {
        if (!$this->permitido($origen)) {
            return [];
        }
        return [
            'Access-Control-Allow-Origin' => in_array('*', $this->origenes, true) ? '*' : (string) $origen,
            'Vary' => 'Origin',
        ];
    }

    // Respuesta al pedido OPTIONS que el navegador manda antes de un POST con JSON o con token
    public function preflight(Request $request): Response
    {
        $respuesta = Response::empty(204);
        if (!$this->permitido($request->origin())) {
            return $respuesta;
        }
        return $respuesta->withHeaders($this->cabeceras($request->origin()) + [
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept',
            'Access-Control-Max-Age' => '86400',
        ]);
    }
}
