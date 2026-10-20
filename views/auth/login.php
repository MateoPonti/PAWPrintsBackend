<?php
// Formulario de ingreso. $aviso es el mensaje de error (credenciales incorrectas) o null.
$aviso ??= null;
?>
    <h2>Ingresar</h2>
    <p>Ingresá con tu correo y contraseña. ¿Todavía no tenés cuenta? <a href="<?= e($view->url('/registro')) ?>">Creala acá</a>.</p>

    <?php if ($aviso !== null): ?>
      <div class="aviso-error" role="alert">
        <p><?= e($aviso) ?></p>
      </div>
    <?php endif; ?>

    <form action="<?= e($view->url('/login')) ?>" method="post">
      <input type="hidden" name="_csrf" value="<?= e($csrfToken()) ?>">
      <fieldset>
        <legend>Tus datos</legend>

        <p>
          <label for="email">Correo electrónico</label><br>
          <input type="email" id="email" name="email" autocomplete="email" required value="<?= e($email) ?>">
        </p>

        <p>
          <label for="password">Contraseña</label><br>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </p>
      </fieldset>

      <button type="submit">Ingresar</button>
    </form>
