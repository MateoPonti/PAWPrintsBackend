<?php

namespace Paw\Controllers;

use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\UsuarioRepository;
use PDOException;

// Registro, ingreso y datos del usuario actual. No hay sesión en el servidor: el login devuelve un token
// (JWT) y salir es descartarlo en el front. El registro siempre crea usuarios con rol 'usuario'.
class AuthController extends Controller
{
    public const NOMBRE_MAX = 80;
    public const EMAIL_MAX = 254;
    public const PASSWORD_MIN = 8;
    public const PASSWORD_MAX = 72; // límite de bcrypt

    // POST /api/registro {nombre, email, password, password_confirm} -> 201 {usuario}
    public function registro(Request $request): Response
    {
        $this->exigirBase();

        $texto = static fn (mixed $v): string => is_string($v) ? trim($v) : '';
        $nombre = $texto($request->input('nombre'));
        $email = mb_strtolower($texto($request->input('email')));
        $password = is_string($request->input('password')) ? $request->input('password') : '';
        $confirmar = is_string($request->input('password_confirm')) ? $request->input('password_confirm') : '';

        $errores = [];
        if ($nombre === '' || mb_strlen($nombre) > self::NOMBRE_MAX || preg_match('/^\P{Cc}*$/u', $nombre) !== 1) {
            $errores['nombre'] = 'Ingresá tu nombre (hasta ' . self::NOMBRE_MAX . ' caracteres).';
        }
        if ($email === '' || strlen($email) > self::EMAIL_MAX || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
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
            throw HttpException::invalid($errores);
        }

        $usuarios = new UsuarioRepository($this->app->pdo());
        try {
            $id = $usuarios->crear($nombre, $email, password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $e) {
            if ($e->getCode() !== '23505') { // 23505 = unique_violation: el email ya tiene cuenta
                throw $e;
            }
            throw HttpException::invalid(['email' => 'Ese correo ya tiene una cuenta. Ingresá o usá otro.']);
        }

        $this->app->logger->info('Usuario registrado: {email}', ['email' => $email]);
        return $this->json(['usuario' => $usuarios->find($id)->toArray()], 201);
    }

    // POST /api/login {email, password} -> {token, tipo, expira_en, usuario}
    public function login(Request $request): Response
    {
        $this->exigirBase();

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
            throw new HttpException(422, 'El correo o la contraseña no son correctos.', 'credenciales_invalidas');
        }

        $usuario = $encontrado[0];
        return $this->json($this->app->auth->emitir($usuario) + ['usuario' => $usuario->toArray()]);
    }

    // GET /api/me (con token) -> {usuario}
    public function me(Request $request): Response
    {
        return $this->json(['usuario' => $this->exigirUsuario()->toArray()]);
    }
}
