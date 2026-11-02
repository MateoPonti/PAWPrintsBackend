<?php

namespace Paw\Models;

use DateTimeImmutable;
use PDO;

// Acceso a la tabla users y a sus roles y permisos (roles, permisos, rol_permisos).
// Un registro público siempre nace con el rol 'usuario'; los demás roles los asigna el admin.
class UsuarioRepository
{
    public const POR_PAGINA = 20;

    // El rol se trae por nombre con un JOIN
    private const CAMPOS = 'u.id, u.nombre, u.email, u.activo, u.created_at, r.nombre AS rol';

    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?Usuario
    {
        $st = $this->pdo->prepare('SELECT ' . self::CAMPOS . ' FROM users u JOIN roles r ON r.id = u.rol_id WHERE u.id = :id');
        $st->execute(['id' => $id]);
        $fila = $st->fetch();
        return $fila ? self::desdeFila($fila) : null;
    }

    // Devuelve [usuario, hash de la contraseña] o null si el email no existe
    public function buscarPorEmail(string $email): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT ' . self::CAMPOS . ', u.password_hash FROM users u JOIN roles r ON r.id = u.rol_id WHERE u.email = :email'
        );
        $st->execute(['email' => $email]);
        $fila = $st->fetch();
        return $fila ? [self::desdeFila($fila), $fila['password_hash']] : null;
    }

    // Lanza PDOException con código 23505 si el email ya existe (campo UNIQUE)
    public function crear(string $nombre, string $email, string $passwordHash): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO users (nombre, email, password_hash, rol_id)
             VALUES (:nombre, :email, :hash, (SELECT id FROM roles WHERE nombre = 'usuario')) RETURNING id"
        );
        $st->execute(['nombre' => $nombre, 'email' => $email, 'hash' => $passwordHash]);
        return (int) $st->fetchColumn();
    }

    /** @return string[] nombres de los permisos del rol del usuario (vacío si no existe o está dado de baja) */
    public function permisosDe(int $id): array
    {
        $st = $this->pdo->prepare(
            'SELECT p.nombre FROM users u
               JOIN rol_permisos rp ON rp.rol_id = u.rol_id
               JOIN permisos p ON p.id = rp.permiso_id
              WHERE u.id = :id AND u.activo
              ORDER BY p.nombre'
        );
        $st->execute(['id' => $id]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Listado para el admin. Filtros: q (nombre o email), rol, activo (true / false / null = todos).
     * @return array{items: Usuario[], total: int}
     */
    public function listar(string $q, string $rol, ?bool $activo, int $pagina): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.nombre ILIKE :q1 OR u.email ILIKE :q2)';
            $params['q1'] = $params['q2'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
        }
        if ($rol !== '') {
            $where[] = 'r.nombre = :rol';
            $params['rol'] = $rol;
        }
        if ($activo !== null) {
            $where[] = $activo ? 'u.activo' : 'NOT u.activo';
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.rol_id $sqlWhere");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->pdo->prepare(
            'SELECT ' . self::CAMPOS . " FROM users u JOIN roles r ON r.id = u.rol_id $sqlWhere ORDER BY u.id LIMIT :lim OFFSET :off"
        );
        foreach ($params as $clave => $valor) {
            $st->bindValue(":$clave", $valor);
        }
        $st->bindValue(':lim', self::POR_PAGINA, PDO::PARAM_INT);
        $st->bindValue(':off', ($pagina - 1) * self::POR_PAGINA, PDO::PARAM_INT);
        $st->execute();
        return ['items' => array_map([self::class, 'desdeFila'], $st->fetchAll()), 'total' => $total];
    }

    // Cambia nombre, rol y/o activo (solo las claves que vengan en $cambios)
    public function actualizar(int $id, array $cambios): void
    {
        $set = [];
        $params = ['id' => $id];
        if (array_key_exists('nombre', $cambios)) {
            $set[] = 'nombre = :nombre';
            $params['nombre'] = $cambios['nombre'];
        }
        if (array_key_exists('rol', $cambios)) {
            $set[] = 'rol_id = (SELECT id FROM roles WHERE nombre = :rol)';
            $params['rol'] = $cambios['rol'];
        }
        if (array_key_exists('activo', $cambios)) {
            $set[] = 'activo = :activo';
            $params['activo'] = $cambios['activo'] ? 'true' : 'false'; // PDO mandaría false como '' y Postgres lo rechaza
        }
        if ($set !== []) {
            $this->pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        }
    }

    // Los roles con sus permisos (para el selector de rol)
    public function roles(): array
    {
        $filas = $this->pdo->query(
            "SELECT r.nombre, r.descripcion, COALESCE(string_agg(p.nombre, ',' ORDER BY p.nombre), '') AS permisos
               FROM roles r
               LEFT JOIN rol_permisos rp ON rp.rol_id = r.id
               LEFT JOIN permisos p ON p.id = rp.permiso_id
              GROUP BY r.id ORDER BY r.id"
        )->fetchAll();
        return array_map(static fn (array $r): array => [
            'nombre' => $r['nombre'],
            'descripcion' => $r['descripcion'],
            'permisos' => $r['permisos'] === '' ? [] : explode(',', $r['permisos']),
        ], $filas);
    }

    public function existeRol(string $nombre): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM roles WHERE nombre = :nombre');
        $st->execute(['nombre' => $nombre]);
        return $st->fetchColumn() !== false;
    }

    private static function desdeFila(array $f): Usuario
    {
        return new Usuario(
            (int) $f['id'],
            $f['nombre'],
            $f['email'],
            $f['rol'],
            (bool) $f['activo'],
            (new DateTimeImmutable($f['created_at']))->format(DATE_ATOM),
        );
    }
}
