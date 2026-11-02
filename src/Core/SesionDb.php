<?php

namespace Paw\Core;

use Closure;
use PDO;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

// Guarda las sesiones de PHP en la tabla "sesiones" en vez de en archivos: el disco de Render se borra en
// cada despliegue y con archivos se cerraría la sesión de todos. PHP llama a estos métodos solo
// (session_start, session_regenerate_id, session_destroy y al terminar el pedido).
final class SesionDb implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** @param Closure(): PDO $pdo la conexión se pide recién cuando hace falta */
    public function __construct(
        private Closure $pdo,
        private int $duracion,
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    // Los datos de la sesión (serializados por PHP), o '' si no existe o venció
    public function read(string $id): string|false
    {
        $st = ($this->pdo)()->prepare('SELECT datos FROM sesiones WHERE id = :id AND vence > now()');
        $st->execute(['id' => $id]);
        $datos = $st->fetchColumn();
        return $datos === false ? '' : $datos;
    }

    // Una sesión vacía no se guarda: así un pedido sin ingreso no deja filas en la tabla
    public function write(string $id, string $data): bool
    {
        if ($data === '') {
            return $this->destroy($id);
        }
        $st = ($this->pdo)()->prepare(
            'INSERT INTO sesiones (id, datos, vence) VALUES (:id, :datos, now() + make_interval(secs => :duracion))
             ON CONFLICT (id) DO UPDATE SET datos = EXCLUDED.datos, vence = EXCLUDED.vence'
        );
        $st->bindValue(':id', $id);
        $st->bindValue(':datos', $data);
        $st->bindValue(':duracion', $this->duracion, PDO::PARAM_INT);
        return $st->execute();
    }

    public function destroy(string $id): bool
    {
        return ($this->pdo)()->prepare('DELETE FROM sesiones WHERE id = :id')->execute(['id' => $id]);
    }

    // Borra las vencidas (PHP lo llama de vez en cuando, según session.gc_probability)
    public function gc(int $max_lifetime): int|false
    {
        return ($this->pdo)()->exec('DELETE FROM sesiones WHERE vence < now()');
    }

    // Con session.use_strict_mode, PHP rechaza un id que no existe y genera uno nuevo (evita fijación de sesión)
    public function validateId(string $id): bool
    {
        $st = ($this->pdo)()->prepare('SELECT 1 FROM sesiones WHERE id = :id AND vence > now()');
        $st->execute(['id' => $id]);
        return $st->fetchColumn() !== false;
    }

    // Los datos no cambiaron en este pedido: solo se extiende la vida de la fila
    public function updateTimestamp(string $id, string $data): bool
    {
        $st = ($this->pdo)()->prepare('UPDATE sesiones SET vence = now() + make_interval(secs => :duracion) WHERE id = :id');
        $st->bindValue(':id', $id);
        $st->bindValue(':duracion', $this->duracion, PDO::PARAM_INT);
        return $st->execute();
    }
}
