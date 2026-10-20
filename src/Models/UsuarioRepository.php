<?php

namespace Paw\Models;

use PDO;

// Acceso a la tabla users. El rol de un registro público es siempre 'usuario':
// el primer admin se asigna en la base (ver README).
class UsuarioRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?Usuario
    {
        $st = $this->pdo->prepare('SELECT id, nombre, email, rol FROM users WHERE id = :id');
        $st->execute(['id' => $id]);
        $fila = $st->fetch();
        return $fila ? self::desdeFila($fila) : null;
    }

    // Devuelve [usuario, hash de la contraseña] o null si el email no existe
    public function buscarPorEmail(string $email): ?array
    {
        $st = $this->pdo->prepare('SELECT id, nombre, email, rol, password_hash FROM users WHERE email = :email');
        $st->execute(['email' => $email]);
        $fila = $st->fetch();
        return $fila ? [self::desdeFila($fila), $fila['password_hash']] : null;
    }

    // Lanza PDOException con código 23505 si el email ya existe (campo UNIQUE)
    public function crear(string $nombre, string $email, string $passwordHash): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO users (nombre, email, password_hash, rol) VALUES (:nombre, :email, :hash, 'usuario') RETURNING id"
        );
        $st->execute(['nombre' => $nombre, 'email' => $email, 'hash' => $passwordHash]);
        return (int) $st->fetchColumn();
    }

    private static function desdeFila(array $f): Usuario
    {
        return new Usuario((int) $f['id'], $f['nombre'], $f['email'], $f['rol']);
    }
}
