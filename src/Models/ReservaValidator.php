<?php

namespace Paw\Models;

// Validaciones de la reserva. Son las mismas que las del formulario del front: los límites
// (nombre, teléfono, cantidad) son los que ese formulario pone en sus atributos HTML.
//
// Los errores se devuelven por campo, con las claves que espera el front:
// nombre, email, telefono, items.N.libro, items.N.cantidad e items (error general).
class ReservaValidator
{
    public const NOMBRE_MAX = 100;
    public const TELEFONO_PATRON = '[0-9+ \(\)\-]{6,20}';
    public const CANTIDAD_MIN = 1;
    public const CANTIDAD_MAX = 10;
    public const ITEMS_MAX = 10;                        // filas (libros distintos) por reserva

    public function __construct(private LibroRepository $libros)
    {
    }

    // Normaliza el cuerpo JSON: textos recortados e items como lista de {libro, cantidad} en texto.
    // Lo que no tenga la forma esperada queda vacío y lo rechaza validar().
    public static function valores(array $input): array
    {
        $texto = static fn (mixed $valor): string => is_string($valor) ? trim($valor) : '';
        // La cantidad puede venir como número (2) o como texto ("2")
        $cantidad = static fn (mixed $valor): string => is_int($valor) ? (string) $valor : $texto($valor);

        $items = [];
        if (is_array($input['items'] ?? null)) {
            foreach (array_values($input['items']) as $item) {
                $item = is_array($item) ? $item : [];
                $items[] = [
                    'libro' => $texto($item['libro'] ?? ''),
                    'cantidad' => $cantidad($item['cantidad'] ?? ''),
                ];
            }
        }

        return [
            'nombre' => $texto($input['nombre'] ?? ''),
            'email' => $texto($input['email'] ?? ''),
            'telefono' => $texto($input['telefono'] ?? ''),
            'items' => $items,
        ];
    }

    /**
     * Devuelve los mensajes de error por campo; vacío si la reserva es válida.
     *
     * @return array<string, string>
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

        $filas = count($valores['items']);
        if ($filas === 0) {
            $errores['items'] = 'Elegí al menos un libro.';
            return $errores;
        }
        if ($filas > self::ITEMS_MAX) {
            // No se revisa fila por fila: así un pedido enorme no hace trabajo de más
            $errores['items'] = 'Podés reservar hasta ' . self::ITEMS_MAX . ' libros por vez.';
            return $errores;
        }

        foreach ($valores['items'] as $i => $item) {
            if ($item['libro'] === '') {
                $errores["items.$i.libro"] = 'Elegí un libro del catálogo.';
            } elseif ($this->libros->find($item['libro']) === null) {
                $errores["items.$i.libro"] = 'El libro elegido no está en el catálogo.';
            }

            if (self::cantidad($item['cantidad']) === null) {
                $errores["items.$i.cantidad"] = 'Ingresá una cantidad entera entre '
                    . self::CANTIDAD_MIN . ' y ' . self::CANTIDAD_MAX . '.';
            }
        }

        return $errores;
    }

    // Arma la reserva a partir de valores que ya pasaron validar().
    public function crear(array $valores): Reserva
    {
        $items = [];
        foreach ($valores['items'] as $item) {
            $items[] = [
                'libro' => $this->libros->find($item['libro']),
                'cantidad' => self::cantidad($item['cantidad']),
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
