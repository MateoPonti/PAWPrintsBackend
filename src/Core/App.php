<?php

namespace Paw\Core;

use Paw\Models\LibroDbRepository;
use Paw\Models\LibroRepository;
use Paw\Models\Usuario;
use Paw\Models\UsuarioRepository;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Throwable;

// Arma los servicios compartidos (config, logger, router, autenticación, CORS) y atiende la petición.
// Es lo único que usa public/index.php.
class App
{
    public readonly Config $config;
    public readonly LoggerInterface $logger;
    public readonly Router $router;
    public readonly Auth $auth;
    private Cors $cors;
    private ErrorHandler $errorHandler;
    private ?PDO $pdo = null;
    private ?LibroRepository $libros = null;
    private ?Usuario $usuario = null;
    private bool $usuarioCargado = false;

    public function __construct(
        private string $rootDir,
        private Request $request,
    ) {
        $this->config = Config::load($rootDir);
        $this->logger = new FileLogger($this->config->get('log.path'), $this->config->get('log.level'));

        $this->cors = new Cors(
            (string) $this->config->get('cors.origins'),
            $this->config->get('app.env') === 'development',
        );
        $this->errorHandler = new ErrorHandler(
            $this->logger,
            (bool) $this->config->get('app.debug'),
            fn (Response $respuesta): Response => $this->conCors($respuesta),
        );
        $this->errorHandler->register();

        $this->auth = new Auth(
            (string) $this->config->get('auth.secret'),
            (int) $this->config->get('auth.ttl'),
        );

        $this->router = new Router();
        $router = $this->router;
        require $rootDir . '/config/routes.php';
    }

    public function dbConfigurada(): bool
    {
        return Database::configurada($this->config->get('db'));
    }

    // Conexión única a Neon. Si la base no responde, 503 (en el plan gratis también "se duerme")
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            try {
                $this->pdo = Database::connect($this->config->get('db'));
            } catch (PDOException $e) {
                $this->logger->error('No se pudo conectar a la base: {message}', ['message' => $e->getMessage()]);
                throw HttpException::unavailable(
                    'No pudimos conectar con la base de datos. Probá de nuevo en un minuto.',
                    'base_no_disponible',
                );
            }
        }
        return $this->pdo;
    }

    // Libros del catálogo: de la base si está configurada, si no del JSON (como antes)
    public function libros(): LibroRepository
    {
        return $this->libros ??= $this->dbConfigurada()
            ? new LibroDbRepository($this->pdo())
            : new LibroRepository($this->config->get('catalogo.data'));
    }

    // Usuario del token del pedido, o null (sin token, token inválido o vencido, o usuario borrado)
    public function usuario(): ?Usuario
    {
        if ($this->usuarioCargado) {
            return $this->usuario;
        }
        $this->usuarioCargado = true;

        $id = $this->dbConfigurada() ? $this->auth->userId($this->request) : null;
        if ($id !== null) {
            $this->usuario = (new UsuarioRepository($this->pdo()))->find($id);
        }
        return $this->usuario;
    }

    public function handle(): Response
    {
        try {
            if ($this->request->method() === 'OPTIONS') {
                return $this->cors->preflight($this->request);
            }

            [[$class, $method], $params] = $this->router->match($this->request);
            $controller = new $class($this);
            return $this->conCors($controller->$method($this->request, ...$params));
        } catch (Throwable $e) {
            return $this->errorHandler->handle($e);
        }
    }

    private function conCors(Response $respuesta): Response
    {
        return $respuesta->withHeaders($this->cors->cabeceras($this->request->origin()));
    }
}
