<?php

namespace Paw\Models;

// Arma el CSV del catálogo (RFC 4180: separado por comas, campos entre comillas si hace falta).
// Empieza con BOM para que Excel reconozca los acentos (UTF-8).
class CatalogoCsv
{
    public const COLUMNAS = ['id', 'titulo', 'autor', 'genero', 'precio', 'resumen'];

    /** @param Libro[] $libros */
    public static function generar(array $libros): string
    {
        $filas = [self::COLUMNAS];
        foreach ($libros as $libro) {
            $filas[] = [
                $libro->id,
                $libro->titulo,
                $libro->autor,
                LibroValidator::GENEROS[$libro->genero] ?? $libro->genero,
                (string) $libro->precio,
                $libro->resumen,
            ];
        }
        return "\u{FEFF}" . implode("\r\n", array_map(
            static fn (array $fila): string => implode(',', array_map([self::class, 'celda'], $fila)),
            $filas,
        )) . "\r\n";
    }

    private static function celda(string $valor): string
    {
        // Un texto que empieza con = + - @ lo ejecutaría Excel como fórmula: se le antepone un apóstrofo
        if (preg_match('/^[=+\-@\t\r]/', $valor) === 1) {
            $valor = "'" . $valor;
        }
        if (preg_match('/[",\r\n]/', $valor) === 1) {
            $valor = '"' . str_replace('"', '""', $valor) . '"';
        }
        return $valor;
    }
}
