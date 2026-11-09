<?php

namespace Paw\Models;

use Paw\Core\Database;
use PDO;
use Throwable;

// Reservas guardadas en PostgreSQL (tablas reservas y reserva_items). Todas las consultas son preparadas.
class ReservaRepository
{
    public const POR_PAGINA = 20;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Guarda la reserva y sus libros en una transacción: se guarda todo o nada.
     * Devuelve el número de la reserva (su id).
     */
    public function guardar(Reserva $reserva): int
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO reservas (nombre, email, telefono, total)
                 VALUES (:nombre, :email, :telefono, :total)
                 RETURNING id'
            );
            $st->execute([
                'nombre' => $reserva->nombre,
                'email' => $reserva->email,
                'telefono' => $reserva->telefono !== '' ? $reserva->telefono : null,
                'total' => $reserva->total(),
            ]);
            $id = (int) $st->fetchColumn();

            // El libro llega con su slug (el id público); en la tabla se guarda el id interno
            $item = $this->pdo->prepare(
                'INSERT INTO reserva_items (reserva_id, libro_id, cantidad, precio)
                 SELECT :reserva_id, id, :cantidad, :precio FROM libros WHERE slug = :slug'
            );
            foreach ($reserva->items as $fila) {
                $item->execute([
                    'reserva_id' => $id,
                    'cantidad' => $fila['cantidad'],
                    'precio' => $fila['libro']->precio,
                    'slug' => $fila['libro']->id,
                ]);
                if ($item->rowCount() !== 1) {
                    throw new \RuntimeException('El libro ' . $fila['libro']->id . ' ya no está en la base');
                }
            }

            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function marcarCorreoEnviado(int $id): void
    {
        $st = $this->pdo->prepare('UPDATE reservas SET correo_enviado = true WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    /**
     * Listado de pedidos para el personal, del más nuevo al más viejo.
     * $texto busca en el nombre o el correo de quien reservó.
     *
     * @return array{items: array<int, array>, total: int}
     */
    public function listar(string $texto, int $pagina): array
    {
        $donde = '';
        $parametros = [];
        if ($texto !== '') {
            $donde = ' WHERE nombre ILIKE :texto OR email ILIKE :texto2';
            $buscado = '%' . Database::escaparLike($texto) . '%';
            $parametros['texto'] = $buscado;
            $parametros['texto2'] = $buscado;
        }

        $st = $this->pdo->prepare('SELECT count(*) FROM reservas' . $donde);
        $st->execute($parametros);
        $total = (int) $st->fetchColumn();

        $st = $this->pdo->prepare(
            'SELECT id, nombre, email, telefono, total, correo_enviado, created_at
             FROM reservas' . $donde . '
             ORDER BY created_at DESC, id DESC
             LIMIT :limite OFFSET :desde'
        );
        foreach ($parametros as $clave => $valor) {
            $st->bindValue($clave, $valor);
        }
        $st->bindValue('limite', self::POR_PAGINA, PDO::PARAM_INT);
        $st->bindValue('desde', ($pagina - 1) * self::POR_PAGINA, PDO::PARAM_INT);
        $st->execute();
        $reservas = $st->fetchAll();

        // Los libros de cada reserva de la página (son 20 como máximo)
        $libros = $this->pdo->prepare(
            'SELECT l.slug, l.titulo, l.autor, i.cantidad, i.precio
             FROM reserva_items i
             JOIN libros l ON l.id = i.libro_id
             WHERE i.reserva_id = :reserva_id
             ORDER BY i.id'
        );
        $items = [];
        foreach ($reservas as $r) {
            $libros->execute(['reserva_id' => $r['id']]);
            $items[] = [
                'id' => (int) $r['id'],
                'nombre' => $r['nombre'],
                'email' => $r['email'],
                'telefono' => $r['telefono'] ?? '',
                'total' => (int) $r['total'],
                'correo_enviado' => (bool) $r['correo_enviado'],
                'fecha' => (new \DateTimeImmutable($r['created_at']))->format(DATE_ATOM),
                'items' => array_map(static fn (array $l): array => [
                    'libro' => $l['slug'],
                    'titulo' => $l['titulo'],
                    'autor' => $l['autor'],
                    'cantidad' => (int) $l['cantidad'],
                    'precio' => (int) $l['precio'],
                ], $libros->fetchAll()),
            ];
        }

        return ['items' => $items, 'total' => $total];
    }
}
