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
}
