<?php

namespace Paw\Controllers;

use Paw\Core\App;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\LibroRepository;
use Paw\Models\ReservaMailer;
use Paw\Models\ReservaValidator;
use PHPMailer\PHPMailer\Exception as MailException;

// Formulario de reserva: lo muestra (GET), lo procesa (POST) y avisa al personal por correo.
class ReservaController extends Controller
{
    private LibroRepository $libros;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->libros = $app->libros();
    }

    public function form(Request $request): Response
    {
        // Permite llegar desde el detalle con el libro ya elegido: /reserva?libro=rayuela
        $seleccionado = $request->query('libro', '');
        return $this->mostrarFormulario([
            'nombre' => '',
            'email' => '',
            'telefono' => '',
            'libro' => [is_string($seleccionado) ? $seleccionado : ''],
            'cantidad' => ['1'],
        ]);
    }

    // POST /reserva, con el cuerpo en application/x-www-form-urlencoded
    public function store(Request $request): Response
    {
        $validator = new ReservaValidator($this->libros);
        $valores = ReservaValidator::valores($request->allInput());
        $errores = $validator->validar($valores);

        if ($errores !== []) {
            $this->app->logger->notice('Reserva rechazada por validación', ['campos' => array_keys($errores)]);
            return $this->mostrarFormulario($valores, $errores, 422);
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
            (new ReservaMailer($this->app->config->get('mail'), $this->app->view, $this->app->logger))
                ->enviar($reserva);
        } catch (MailException $e) {
            // El detalle queda en el log; al usuario solo se le pide que reintente
            $this->app->logger->error('No se pudo enviar el correo de reserva: {error}', ['error' => $e->getMessage()]);
            return $this->mostrarFormulario(
                $valores,
                [],
                503,
                'No pudimos registrar tu reserva en este momento. Probá de nuevo en unos minutos o escribinos desde Contacto.',
            );
        }

        // Post/Redirect/Get: recargar la confirmación no vuelve a enviar el formulario
        return $this->redirect('/reserva/confirmada', 303);
    }

    public function confirmada(Request $request): Response
    {
        return $this->render('reserva/confirmada', [
            'title' => 'Reserva recibida - PawPrints',
            'description' => 'Recibimos tu reserva en PawPrints.',
            'current' => 'reserva',
        ]);
    }

    private function mostrarFormulario(array $valores, array $errores = [], int $status = 200, ?string $aviso = null): Response
    {
        return $this->render('reserva/form', [
            'title' => 'Formulario de Reserva - PawPrints',
            'description' => 'Reservá libros del catálogo de PawPrints.',
            'current' => 'reserva',
            'libros' => $this->libros->all(),
            'valores' => $valores,
            'errores' => $errores,
            'aviso' => $aviso,
        ], $status);
    }
}
