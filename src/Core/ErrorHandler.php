<?php

namespace Paw\Core;

use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

// Atrapa cualquier error o excepción no manejada, la registra en el log y responde JSON con la forma
// {"error": "codigo", "mensaje": "..."}. Con debug = false (producción) el cliente solo ve un mensaje
// genérico, sin rutas, consultas ni trazas; el detalle queda en el log.
class ErrorHandler
{
    /** @param null|callable(Response): Response $decorar Se aplica a toda respuesta de error (la usa App para las cabeceras CORS) */
    public function __construct(
        private LoggerInterface $logger,
        private bool $debug,
        private mixed $decorar = null,
    ) {
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        // La salida es JSON: los errores nunca se imprimen en el cuerpo, van al log (y a "detalle" en debug)
        ini_set('display_errors', '0');
        ini_set('html_errors', '0');

        // Los warnings y notices también se tratan como excepciones
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(fn (Throwable $e) => $this->handle($e)->send());

        // Errores fatales que no pasan por el exception handler
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->handle(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']))->send();
            }
        });
    }

    public function handle(Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            $this->logger->notice('HTTP {status}: {message}', [
                'status' => $e->status(),
                'message' => $e->getMessage(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            $cuerpo = ['error' => $e->codigo(), 'mensaje' => $e->getMessage()];
            if ($e->errores() !== []) {
                $cuerpo['errores'] = $e->errores();
            }
            $respuesta = Response::json($cuerpo, $e->status())->withHeaders($e->headers());
        } else {
            $this->logger->error('{class}: {message} en {file}:{line}', [
                'class' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'trace' => $e->getTraceAsString(),
            ]);
            $cuerpo = [
                'error' => 'error_interno',
                'mensaje' => 'Ocurrió un error inesperado. Probá de nuevo en unos minutos.',
            ];
            if ($this->debug) {
                $cuerpo['detalle'] = [
                    'clase' => $e::class,
                    'mensaje' => $e->getMessage(),
                    'archivo' => $e->getFile() . ':' . $e->getLine(),
                ];
            }
            $respuesta = Response::json($cuerpo, 500);
        }

        return $this->decorar !== null ? ($this->decorar)($respuesta) : $respuesta;
    }
}
