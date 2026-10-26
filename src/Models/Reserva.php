<?php

namespace Paw\Models;

// Reserva ya validada (la arma ReservaValidator::crear).
class Reserva
{
    /**
     * @param array<int, array{libro: Libro, cantidad: int}> $items
     */
    public function __construct(
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $telefono,
        public readonly array $items,
    ) {
    }

    public function total(): int
    {
        return array_sum(array_map(
            static fn (array $item): int => $item['libro']->precio * $item['cantidad'],
            $this->items,
        ));
    }

    public function totalFormateado(): string
    {
        return '$' . number_format($this->total(), 0, ',', '.');
    }

    // Forma de la reserva en la respuesta de la API
    public function toArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'items' => array_map(static fn (array $item): array => [
                'libro' => $item['libro']->id,
                'titulo' => $item['libro']->titulo,
                'cantidad' => $item['cantidad'],
                'precio' => $item['libro']->precio,
            ], $this->items),
            'total' => $this->total(),
        ];
    }
}
