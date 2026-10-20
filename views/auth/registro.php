<?php
use Paw\Controllers\AuthController;

// Formulario de registro. Los límites salen de AuthController para que HTML y backend validen lo mismo.
$errores ??= [];

$invalido = static fn (?string $error, string $id): string =>
    $error === null ? '' : ' aria-invalid="true" aria-describedby="' . e($id) . '-error"';
$mensaje = static fn (?string $error, string $id): string =>
    $error === null ? '' : '<span class="error-campo" id="' . e($id) . '-error">' . e($error) . '</span>';
?>
    <h2>Crear cuenta</h2>
    <p>Registrate para tener tu cuenta en PawPrints. ¿Ya tenés una? <a href="<?= e($view->url('/login')) ?>">Ingresá</a>.</p>

    <?php if ($errores !== []): ?>
      <div class="aviso-error" role="alert">
        <p>No pudimos crear la cuenta. Revisá los campos marcados.</p>
      </div>
    <?php endif; ?>

    <form action="<?= e($view->url('/registro')) ?>" method="post">
      <input type="hidden" name="_csrf" value="<?= e($csrfToken()) ?>">
      <fieldset>
        <legend>Tus datos</legend>

        <p>
          <label for="nombre">Nombre</label><br>
          <input type="text" id="nombre" name="nombre" autocomplete="name" required
                 maxlength="<?= e(AuthController::NOMBRE_MAX) ?>"
                 value="<?= e($valores['nombre']) ?>"<?= $invalido($errores['nombre'] ?? null, 'nombre') ?>>
          <?= $mensaje($errores['nombre'] ?? null, 'nombre') ?>
        </p>

        <p>
          <label for="email">Correo electrónico</label><br>
          <input type="email" id="email" name="email" autocomplete="email" required
                 maxlength="<?= e(AuthController::EMAIL_MAX) ?>"
                 pattern="[^@\s]+@[^@\s]+\.[^@\s]+" title="Por ejemplo: nombre@dominio.com"
                 value="<?= e($valores['email']) ?>"<?= $invalido($errores['email'] ?? null, 'email') ?>>
          <?= $mensaje($errores['email'] ?? null, 'email') ?>
        </p>

        <p>
          <label for="password">Contraseña</label><br>
          <input type="password" id="password" name="password" autocomplete="new-password" required
                 minlength="<?= e(AuthController::PASSWORD_MIN) ?>" maxlength="<?= e(AuthController::PASSWORD_MAX) ?>"
                 aria-describedby="password-ayuda<?= isset($errores['password']) ? ' password-error' : '' ?>"<?= isset($errores['password']) ? ' aria-invalid="true"' : '' ?>>
          <small id="password-ayuda">Entre <?= e(AuthController::PASSWORD_MIN) ?> y <?= e(AuthController::PASSWORD_MAX) ?> caracteres, con al menos una letra y un número.</small>
          <?= $mensaje($errores['password'] ?? null, 'password') ?>
        </p>

        <p>
          <label for="password_confirm">Repetí la contraseña</label><br>
          <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required
                 minlength="<?= e(AuthController::PASSWORD_MIN) ?>" maxlength="<?= e(AuthController::PASSWORD_MAX) ?>"
                 <?= $invalido($errores['password_confirm'] ?? null, 'password_confirm') ?>>
          <?= $mensaje($errores['password_confirm'] ?? null, 'password_confirm') ?>
        </p>
      </fieldset>

      <button type="submit">Crear cuenta</button>
    </form>
