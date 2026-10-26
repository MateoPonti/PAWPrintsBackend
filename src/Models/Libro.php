<?php

namespace Paw\Models;

class Libro
{
    public function __construct(
        public readonly string $id,
        public readonly string $titulo,
        public readonly string $autor,
        public readonly string $genero,
        public readonly int $precio,
        public readonly string $resumen,
        public readonly string $descripcion,
        public readonly string $autorBio,
        // URL de la imagen; vacía si el libro no tiene (el front muestra su imagen por defecto)
        public readonly string $portada = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['titulo'],
            $data['autor'],
            $data['genero'],
            (int) $data['precio'],
            $data['resumen'] ?? '',
            $data['descripcion'] ?? '',
            $data['autor_bio'] ?? '',
            $data['portada'] ?? '',
        );
    }

    /**
     * Forma del libro en la API. El listado trae lo justo para las fichas;
     * el detalle (completo = true) agrega la descripción y la biografía del autor.
     */
    public function toArray(bool $completo = false): array
    {
        $datos = [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'autor' => $this->autor,
            'genero' => $this->genero,
            'precio' => $this->precio,
            'resumen' => $this->resumen,
        ];
        if ($completo) {
            $datos['descripcion'] = $this->descripcion;
            $datos['autor_bio'] = $this->autorBio;
        }
        $datos['portada'] = $this->portada;
        return $datos;
    }

    // Precio con formato argentino: 15000 -> $15.000 (lo usa el correo de la reserva)
    public function precioFormateado(): string
    {
        return '$' . number_format($this->precio, 0, ',', '.');
    }
}
