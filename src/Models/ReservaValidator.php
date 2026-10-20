<?php

namespace Paw\Models;

// Validaciones del formulario de reserva. Son las mismas que las de views/reserva/form.php:
// la vista toma los límites de estas constantes, así que si se cambia una regla acá
// cambia también el atributo del HTML.
class ReservaValidator
{
    public const NOMBRE_MAX = 100;                      // maxlength del nombre
    // pattern del teléfono. Los navegadores lo compilan con el flag v de JavaScript, que
    // exige escapar ( ) y - dentro de [ ]; sin escapar, el navegador ignoraba el pattern.
    public const TELEFONO_PATRON = '[0-9+ \(\)\-]{6,20}';
    public const CANTIDAD_MIN = 1;                      // min de cada cantidad
    public const CANTIDAD_MAX = 10;                     // max de cada cantidad

    public function __construct(private LibroRepository $libros)
    {
    }

    // Normaliza el cuerpo del POST: textos recortados y libro[] / cantidad[] como listas de strings.
    public static function valores(array $input): array
    {
        $texto = static fn (mixed $valor): string => is_string($valor) ? trim($valor) : '';
        $lista = static fn (mixed $valor): array => match (true) {
            $valor === null => [],
            is_array($valor) => array_values(array_map($texto, $valor)),
            default => [$texto($valor)],
        };

        return [
            'nombre' => $texto($input['nombre'] ?? ''),
            'email' => $texto($input['email'] ?? ''),
            'telefono' => $texto($input['telefono'] ?? ''),
            'libro' => $lista($input['libro'] ?? null),
            'cantidad' => $lista($input['cantidad'] ?? null),
        ];
    }

    /**
     * Devuelve los mensajes de error por campo; vacío si la reserva es válida.
     * Los de libro y cantidad son arrays indexados por fila.
     */
    public function validar(array $valores): array
    {
        $errores = [];

        if ($valores['nombre'] === '') {
            $errores['nombre'] = 'Ingresá tu nombre y apellido.';
        } elseif (mb_strlen($valores['nombre']) > self::NOMBRE_MAX) {
            $errores['nombre'] = 'El nombre puede tener hasta ' . self::NOMBRE_MAX . ' caracteres.';
        } elseif (preg_match('/^\P{Cc}*$/u', $valores['nombre']) !== 1) {
            // Un input de texto nunca manda saltos de línea ni caracteres de control
            $errores['nombre'] = 'El nombre tiene caracteres no válidos.';
        }

        if ($valores['email'] === '') {
            $errores['email'] = 'Ingresá tu correo electrónico.';
        } elseif (filter_var($valores['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['email'] = 'Ingresá un correo electrónico válido, por ejemplo nombre@dominio.com.';
        }

        if ($valores['telefono'] !== '' && !preg_match('/^' . self::TELEFONO_PATRON . '$/', $valores['telefono'])) {
            $errores['telefono'] = 'El teléfono debe tener entre 6 y 20 caracteres: números, espacios, +, -, ( y ).';
        }

        $filas = max(count($valores['libro']), count($valores['cantidad']));
        if ($filas === 0) {
            $errores['libros'] = 'Elegí al menos un libro.';
        }
        for ($i = 0; $i < $filas; $i++) {
            $id = $valores['libro'][$i] ?? '';
            if ($id === '') {
                $errores['libro'][$i] = 'Elegí un libro del catálogo.';
            } elseif ($this->libros->find($id) === null) {
                $errores['libro'][$i] = 'El libro elegido no está en el catálogo.';
            }

            if (self::cantidad($valores['cantidad'][$i] ?? '') === null) {
                $errores['cantidad'][$i] = 'Ingresá una cantidad entera entre '
                    . self::CANTIDAD_MIN . ' y ' . self::CANTIDAD_MAX . '.';
            }
        }

        return $errores;
    }

    // Arma la reserva a partir de valores que ya pasaron validar().
    public function crear(array $valores): Reserva
    {
        $items = [];
        foreach ($valores['libro'] as $i => $id) {
            $items[] = [
                'libro' => $this->libros->find($id),
                'cantidad' => self::cantidad($valores['cantidad'][$i]),
            ];
        }
        return new Reserva($valores['nombre'], $valores['email'], $valores['telefono'], $items);
    }

    private static function cantidad(string $valor): ?int
    {
        $cantidad = filter_var($valor, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::CANTIDAD_MIN, 'max_range' => self::CANTIDAD_MAX],
        ]);
        return $cantidad === false ? null : $cantidad;
    }
}
