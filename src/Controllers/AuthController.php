<?php

namespace Paw\Controllers;

use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\UsuarioRepository;
use PDOException;

// Registro, ingreso y salida de usuarios. La sesión es del servidor (cookie HttpOnly) y todos los
// POST llevan token anti-CSRF. El registro siempre crea usuarios con rol 'usuario'.
class AuthController extends Controller
{
    public const NOMBRE_MAX = 80;
    public const EMAIL_MAX = 254;
    public const PASSWORD_MIN = 8;
    public const PASSWORD_MAX = 72; // límite de bcrypt

    public function registroForm(Request $request): Response
    {
        $this->exigirBase();
        if ($this->app->usuario() !== null) {
            return $this->redirect('/');
        }
        return $this->renderRegistro(['nombre' => '', 'email' => ''], []);
    }

    public function registro(Request $request): Response
    {
        $this->exigirBase();
        $this->exigirCsrf($request);

        $texto = static fn (mixed $v): string => is_string($v) ? trim($v) : '';
        $valores = ['nombre' => $texto($request->input('nombre')), 'email' => mb_strtolower($texto($request->input('email')))];
        $password = is_string($request->input('password')) ? $request->input('password') : '';
        $confirmar = is_string($request->input('password_confirm')) ? $request->input('password_confirm') : '';

        $errores = [];
        if ($valores['nombre'] === '' || mb_strlen($valores['nombre']) > self::NOMBRE_MAX
            || preg_match('/^\P{Cc}*$/u', $valores['nombre']) !== 1) {
            $errores['nombre'] = 'Ingresá tu nombre (hasta ' . self::NOMBRE_MAX . ' caracteres).';
        }
        if ($valores['email'] === '' || strlen($valores['email']) > self::EMAIL_MAX
            || filter_var($valores['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['email'] = 'Ingresá un correo electrónico válido, por ejemplo nombre@dominio.com.';
        }
        if (strlen($password) < self::PASSWORD_MIN || strlen($password) > self::PASSWORD_MAX) {
            $errores['password'] = 'La contraseña debe tener entre ' . self::PASSWORD_MIN . ' y ' . self::PASSWORD_MAX . ' caracteres.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errores['password'] = 'La contraseña necesita al menos una letra y un número.';
        } elseif ($password !== $confirmar) {
            $errores['password_confirm'] = 'Las contraseñas no coinciden.';
        }
        if ($errores !== []) {
            return $this->renderRegistro($valores, $errores, 422);
        }

        $usuarios = new UsuarioRepository($this->app->pdo());
        try {
            $id = $usuarios->crear($valores['nombre'], $valores['email'], password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $e) {
            if ($e->getCode() !== '23505') { // 23505 = unique_violation: el email ya tiene cuenta
                throw $e;
            }
            return $this->renderRegistro($valores, ['email' => 'Ese correo ya tiene una cuenta. Ingresá o usá otro.'], 422);
        }

        $this->app->iniciarSesion($usuarios->find($id));
        $this->app->logger->info('Usuario registrado: {email}', ['email' => $valores['email']]);
        return $this->redirect('/', 303);
    }

    public function loginForm(Request $request): Response
    {
        $this->exigirBase();
        if ($this->app->usuario() !== null) {
            return $this->redirect('/');
        }
        return $this->renderLogin('', null);
    }

    public function login(Request $request): Response
    {
        $this->exigirBase();
        $this->exigirCsrf($request);

        $email = mb_strtolower(is_string($request->input('email')) ? trim($request->input('email')) : '');
        $password = is_string($request->input('password')) ? $request->input('password') : '';

        $encontrado = null;
        if ($email !== '' && strlen($email) <= self::EMAIL_MAX && $password !== '' && strlen($password) <= self::PASSWORD_MAX) {
            $encontrado = (new UsuarioRepository($this->app->pdo()))->buscarPorEmail($email);
        }

        // Con un correo inexistente se verifica igual contra un hash falso: tarda lo mismo y no revela si la cuenta existe
        $hash = $encontrado[1] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok = password_verify($password, $hash) && $encontrado !== null;

        if (!$ok) {
            $this->app->logger->warning('Ingreso fallido para {email} desde {ip}', ['email' => $email, 'ip' => $request->ip()]);
            return $this->renderLogin($email, 'El correo o la contraseña no son correctos.', 422);
        }

        $this->app->iniciarSesion($encontrado[0]);
        return $this->redirect('/', 303);
    }

    public function logout(Request $request): Response
    {
        $this->exigirBase();
        $this->exigirCsrf($request);
        $this->app->cerrarSesion();
        return $this->redirect('/', 303);
    }

    private function renderRegistro(array $valores, array $errores, int $status = 200): Response
    {
        return $this->render('auth/registro', [
            'title' => 'Crear cuenta - PawPrints',
            'description' => 'Creá tu cuenta en PawPrints.',
            'current' => 'registro',
            'valores' => $valores,
            'errores' => $errores,
        ], $status);
    }

    private function renderLogin(string $email, ?string $aviso, int $status = 200): Response
    {
        return $this->render('auth/login', [
            'title' => 'Ingresar - PawPrints',
            'description' => 'Ingresá a tu cuenta de PawPrints.',
            'current' => 'login',
            'email' => $email,
            'aviso' => $aviso,
        ], $status);
    }
}
