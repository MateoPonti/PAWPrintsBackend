<?php

namespace Paw\Controllers;

use Paw\Core\App;
use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\CorreoException;
use Paw\Models\LibroRepository;
use Paw\Models\ReservaMailer;
use Paw\Models\ReservaValidator;

// POST /api/reservas: valida, registra la reserva y avisa al personal por correo.
class ReservaController extends Controller
{
    private LibroRepository $libros;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->libros = $app->libros();
    }

    // Cuerpo JSON: {nombre, email, telefono, items: [{libro, cantidad}]}
    public function store(Request $request): Response
    {
        $validator = new ReservaValidator($this->libros);
        $valores = ReservaValidator::valores($request->allInput());
        $errores = $validator->validar($valores);

        if ($errores !== []) {
            $this->app->logger->notice('Reserva rechazada por validación', ['campos' => array_keys($errores)]);
            throw HttpException::invalid($errores);
        }

        $reserva = $validator->crear($valores);
        $this->app->logger->info('Reserva recibida de {email}', [
            'nombre' => $reserva->nombre,
            'email' => $reserva->email,
            'libros' => array_map(
                static fn (array $item): string => $item['cantidad'] . ' x ' . $item['libro']->id,
                $reserva->items,
            ),
        ]);

        try {
            (new ReservaMailer($this->app->config->get('mail'), $this->app->logger))->enviar($reserva);
        } catch (CorreoException $e) {
            // El detalle queda en el log; al usuario solo se le pide que reintente
            $this->app->logger->error('No se pudo enviar el correo de reserva: {error}', ['error' => $e->getMessage()]);
            throw HttpException::unavailable(
                'No pudimos registrar tu reserva en este momento. Probá de nuevo en unos minutos o escribinos desde Contacto.',
                'correo_no_enviado',
            );
        }

        return $this->json(['reserva' => $reserva->toArray()], 201);
    }
}
