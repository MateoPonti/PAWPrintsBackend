<?php

namespace Paw\Controllers;

use Paw\Core\App;
use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\CorreoException;
use Paw\Models\LibroRepository;
use Paw\Models\ReservaMailer;
use Paw\Models\ReservaRepository;
use Paw\Models\ReservaValidator;

// POST /api/reservas: valida, guarda la reserva en la base y avisa al personal por correo.
// La reserva se guarda ANTES del correo: si el correo falla, la reserva no se pierde (queda en el
// listado de pedidos con correo_enviado = false). Sin base configurada solo se manda el correo, como en el TP3.
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

        $numero = null;
        if ($this->app->dbConfigurada()) {
            $reservas = new ReservaRepository($this->app->pdo());
            $numero = $reservas->guardar($reserva);
            $this->app->logger->info('Reserva {numero} guardada', ['numero' => $numero]);
        }

        try {
            (new ReservaMailer($this->app->config->get('mail'), $this->app->logger))->enviar($reserva);
            if ($numero !== null) {
                $reservas->marcarCorreoEnviado($numero);
            }
        } catch (CorreoException $e) {
            // El detalle queda en el log
            $this->app->logger->error('No se pudo enviar el correo de reserva: {error}', ['error' => $e->getMessage()]);
            if ($numero === null) {
                // Sin base, el correo era la única forma de registrarla: se le pide al usuario que reintente
                throw HttpException::unavailable(
                    'No pudimos registrar tu reserva en este momento. Probá de nuevo en unos minutos o escribinos desde Contacto.',
                    'correo_no_enviado',
                );
            }
            // Con base, la reserva ya está guardada y el personal la ve en el listado de pedidos
        }

        $datos = $reserva->toArray();
        $datos['numero'] = $numero;
        return $this->json(['reserva' => $datos], 201);
    }
}
