<?php

namespace Paw\Models;

// Validaciones del alta de libros. views/admin/libro_nuevo.php toma los límites de estas
// constantes, así que el HTML (maxlength, min, max) y el backend validan lo mismo.
class LibroValidator
{
    public const TITULO_MAX = 150;
    public const AUTOR_MAX = 100;
    public const PRECIO_MIN = 1;
    public const PRECIO_MAX = 999999999;
    public const RESUMEN_MAX = 300;
    public const DESCRIPCION_MAX = 2000;
    public const AUTOR_BIO_MAX = 1000;
    public const IMAGEN_URL_MAX = 255;
    public const GENEROS = ['novela' => 'Novela', 'cuento' => 'Cuento', 'poesia' => 'Poesía', 'infantil' => 'Infantil'];

    // Normaliza el cuerpo del POST: todos los campos son texto recortado
    public static function valores(array $input): array
    {
        $texto = static fn (mixed $v): string => is_string($v) ? trim($v) : '';
        $valores = [];
        foreach (['titulo', 'autor', 'genero', 'precio', 'resumen', 'descripcion', 'autor_bio', 'imagen_url'] as $campo) {
            $valores[$campo] = $texto($input[$campo] ?? '');
        }
        return $valores;
    }

    /** Devuelve los mensajes de error por campo; vacío si el libro es válido. */
    public static function validar(array $v): array
    {
        $errores = [];

        if ($v['titulo'] === '') {
            $errores['titulo'] = 'Ingresá el título.';
        } elseif (mb_strlen($v['titulo']) > self::TITULO_MAX) {
            $errores['titulo'] = 'El título puede tener hasta ' . self::TITULO_MAX . ' caracteres.';
        }

        if ($v['autor'] === '') {
            $errores['autor'] = 'Ingresá el autor.';
        } elseif (mb_strlen($v['autor']) > self::AUTOR_MAX) {
            $errores['autor'] = 'El autor puede tener hasta ' . self::AUTOR_MAX . ' caracteres.';
        }

        if (!array_key_exists($v['genero'], self::GENEROS)) {
            $errores['genero'] = 'Elegí un género de la lista.';
        }

        $precio = filter_var($v['precio'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::PRECIO_MIN, 'max_range' => self::PRECIO_MAX],
        ]);
        if ($precio === false) {
            $errores['precio'] = 'Ingresá un precio entero en pesos, mayor a 0 (por ejemplo 15000).';
        }

        if (mb_strlen($v['resumen']) > self::RESUMEN_MAX) {
            $errores['resumen'] = 'El resumen puede tener hasta ' . self::RESUMEN_MAX . ' caracteres.';
        }
        if (mb_strlen($v['descripcion']) > self::DESCRIPCION_MAX) {
            $errores['descripcion'] = 'La descripción puede tener hasta ' . self::DESCRIPCION_MAX . ' caracteres.';
        }
        if (mb_strlen($v['autor_bio']) > self::AUTOR_BIO_MAX) {
            $errores['autor_bio'] = 'La biografía puede tener hasta ' . self::AUTOR_BIO_MAX . ' caracteres.';
        }

        $url = $v['imagen_url'];
        if ($url !== '' && (
            mb_strlen($url) > self::IMAGEN_URL_MAX
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
        )) {
            $errores['imagen_url'] = 'Ingresá una dirección http:// o https:// válida, o dejalo vacío.';
        }

        return $errores;
    }
}
