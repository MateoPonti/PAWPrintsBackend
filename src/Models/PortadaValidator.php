<?php

namespace Paw\Models;

// Validación de las tapas que se suben desde el alta de libros. El front controla lo mismo antes de enviar
// (tipo y tamaño) y además achica la imagen antes de mandarla, pero acá se mira el contenido:
// no alcanza con que el Content-Type diga "image/png". SVG no se acepta porque puede traer scripts.
class PortadaValidator
{
    public const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
    public const MAX_BYTES = 1024 * 1024;

    /** Devuelve el mensaje de error, o '' si la imagen es válida. */
    public static function validar(string $tipo, string $datos): string
    {
        if ($datos === '') {
            return 'No llegó ninguna imagen.';
        }
        if (strlen($datos) > self::MAX_BYTES) {
            return 'La imagen puede pesar hasta 1 MB.';
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            return 'La imagen tiene que ser JPG, PNG o WebP.';
        }
        // getimagesizefromstring lee la cabecera del archivo: si no es una imagen de ese tipo, no se acepta
        $info = @getimagesizefromstring($datos);
        if ($info === false || ($info['mime'] ?? '') !== $tipo) {
            return 'El archivo no es una imagen JPG, PNG o WebP válida.';
        }
        return '';
    }
}
