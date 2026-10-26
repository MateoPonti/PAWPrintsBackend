<?php

namespace Paw\Models;

class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $rol,
    ) {
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    // Forma del usuario en la API (nunca incluye la contraseña)
    public function toArray(): array
    {
        return ['id' => $this->id, 'nombre' => $this->nombre, 'email' => $this->email, 'rol' => $this->rol];
    }
}
