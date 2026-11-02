<?php

namespace Paw\Models;

class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $rol,
        public readonly bool $activo = true,
        public readonly string $creado = '',
    ) {
    }

    // Forma del usuario en la API (nunca incluye la contraseña)
    public function toArray(): array
    {
        return ['id' => $this->id, 'nombre' => $this->nombre, 'email' => $this->email, 'rol' => $this->rol];
    }

    // Lo que ve el admin en la gestión de usuarios
    public function paraAdmin(): array
    {
        return $this->toArray() + ['activo' => $this->activo, 'creado' => $this->creado];
    }
}
