<?php

namespace Paw\Core;

use Paw\Models\LibroDbRepository;
use Paw\Models\LibroRepository;
use Paw\Models\Usuario;
use Paw\Models\UsuarioRepository;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

// Arma los servicios compartidos (config, logger, vistas, router) y atiende la petición.
// Es lo único que usa public/index.php.
class App
{
    public readonly Config $config;
    public readonly LoggerInterface $logger;
    public readonly View $view;
    public readonly Router $router;
    public readonly Auth $auth;
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
        $this->view = new View($rootDir . '/views', $request->basePath());
        $this->errorHandler = new ErrorHandler($this->logger, $this->view, (bool) $this->config->get('app.debug'));
        $this->errorHandler->register();

        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || $request->header('X-Forwarded-Proto') === 'https';
        $this->auth = new Auth($https);
        // El menú del layout muestra si hay un usuario con sesión iniciada
        $this->view->share('usuarioActual', fn (): ?Usuario => $this->usuario());
        $this->view->share('cuentasHabilitadas', $this->dbConfigurada());
        $this->view->share('csrfToken', fn (): string => $this->auth->csrfToken());

        $this->router = new Router();
        $router = $this->router;
        require $rootDir . '/config/routes.php';
    }

    public function dbConfigurada(): bool
    {
        return Database::configurada($this->config->get('db'));
    }

    // Conexión única a Neon. Falla (500) si la base no responde; ver DB_* en .env
    public function pdo(): PDO
    {
        return $this->pdo ??= Database::connect($this->config->get('db'));
    }

    // Libros del catálogo: de la base si está configurada, si no del JSON (como antes)
    public function libros(): LibroRepository
    {
        return $this->libros ??= $this->dbConfigurada()
            ? new LibroDbRepository($this->pdo())
            : new LibroRepository($this->config->get('catalogo.data'));
    }

    // Usuario con sesión iniciada, o null. Si la base falla no rompe la página: se la ve como visitante.
    public function usuario(): ?Usuario
    {
        if ($this->usuarioCargado) {
            return $this->usuario;
        }
        $this->usuarioCargado = true;
        $id = $this->dbConfigurada() ? $this->auth->userId() : null;
        if ($id === null) {
            return null;
        }
        try {
            $this->usuario = (new UsuarioRepository($this->pdo()))->find($id);
        } catch (Throwable $e) {
            $this->logger->error('No se pudo leer el usuario de la sesión: {message}', ['message' => $e->getMessage()]);
        }
        return $this->usuario;
    }

    public function iniciarSesion(Usuario $u): void
    {
        $this->auth->login($u->id);
        $this->usuario = $u;
        $this->usuarioCargado = true;
    }

    public function cerrarSesion(): void
    {
        $this->auth->logout();
        $this->usuario = null;
        $this->usuarioCargado = true;
    }

    public function handle(): Response
    {
        try {
            [[$class, $method], $params] = $this->router->match($this->request);
            $controller = new $class($this);
            $response = $controller->$method($this->request, ...$params);
            if (is_string($response)) {
                $response = Response::html($response);
            }
            return $response;
        } catch (Throwable $e) {
            return $this->errorHandler->handle($e);
        }
    }
}
