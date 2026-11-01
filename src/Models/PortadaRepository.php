<?php

namespace Paw\Models;

use PDO;

// Tapas de libros guardadas en la tabla portadas (los bytes de la imagen en una columna BYTEA).
class PortadaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    // Guarda la imagen (ya validada por PortadaValidator) y devuelve su id
    public function guardar(string $tipo, string $datos): int
    {
        $st = $this->pdo->prepare('INSERT INTO portadas (tipo, datos) VALUES (:tipo, :datos) RETURNING id');
        $st->bindValue('tipo', $tipo);
        $st->bindValue('datos', $datos, PDO::PARAM_LOB);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    /** @return array{tipo: string, datos: string}|null */
    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT tipo, datos FROM portadas WHERE id = :id');
        $st->execute(['id' => $id]);
        $fila = $st->fetch();
        if ($fila === false) {
            return null;
        }
        // pdo_pgsql devuelve el BYTEA como stream
        $datos = is_resource($fila['datos']) ? stream_get_contents($fila['datos']) : (string) $fila['datos'];
        return ['tipo' => $fila['tipo'], 'datos' => $datos];
    }
}
