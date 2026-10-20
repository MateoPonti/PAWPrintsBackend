<?php
use Paw\Models\ReservaValidator;

// Los límites de los atributos salen de ReservaValidator para que el HTML y el backend validen lo mismo.
$errores ??= [];
$aviso ??= null;
$filas = max(1, count($valores['libro']), count($valores['cantidad']));

// Atributos ARIA para que el lector de pantalla asocie el campo con su mensaje de error
$invalido = static fn (?string $error, string $id): string =>
    $error === null ? '' : ' aria-invalid="true" aria-describedby="' . e($id) . '-error"';
$mensaje = static fn (?string $error, string $id): string =>
    $error === null ? '' : '<span class="error-campo" id="' . e($id) . '-error">' . e($error) . '</span>';
?>
    <h2>Formulario de reserva</h2>
    <p>Completá tus datos y elegí el libro que querés reservar. Nos vamos a comunicar para confirmar la reserva.</p>

    <?php if ($aviso !== null): ?>
      <div class="aviso-error" role="alert">
        <p><?= e($aviso) ?></p>
      </div>
    <?php elseif ($errores !== []): ?>
      <div class="aviso-error" role="alert">
        <p>No pudimos tomar la reserva. Revisá los campos marcados.</p>
        <?php if (isset($errores['libros'])): ?>
          <p><?= e($errores['libros']) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form action="<?= e($view->url('/reserva')) ?>" method="post">
      <fieldset>
        <legend>Datos del solicitante</legend>

        <p>
          <label for="nombre">Nombre y apellido</label><br>
          <input type="text" id="nombre" name="nombre" autocomplete="name" required
                 maxlength="<?= e(ReservaValidator::NOMBRE_MAX) ?>"
                 value="<?= e($valores['nombre']) ?>"<?= $invalido($errores['nombre'] ?? null, 'nombre') ?>>
          <?= $mensaje($errores['nombre'] ?? null, 'nombre') ?>
        </p>

        <p>
          <label for="email">Correo electrónico</label><br>
          <?php /* El pattern pide un dominio con punto, como FILTER_VALIDATE_EMAIL en el backend */ ?>
          <input type="email" id="email" name="email" autocomplete="email" required
                 pattern="[^@\s]+@[^@\s]+\.[^@\s]+" title="Por ejemplo: nombre@dominio.com"
                 value="<?= e($valores['email']) ?>"<?= $invalido($errores['email'] ?? null, 'email') ?>>
          <?= $mensaje($errores['email'] ?? null, 'email') ?>
        </p>

        <p>
          <label for="telefono">Teléfono</label><br>
          <input type="tel" id="telefono" name="telefono" autocomplete="tel"
                 pattern="<?= e(ReservaValidator::TELEFONO_PATRON) ?>" placeholder="Ej: 011 4444-5555"
                 title="Entre 6 y 20 caracteres: números, espacios, +, -, ( y )"
                 value="<?= e($valores['telefono']) ?>"<?= $invalido($errores['telefono'] ?? null, 'telefono') ?>>
          <?= $mensaje($errores['telefono'] ?? null, 'telefono') ?>
        </p>
      </fieldset>

      <fieldset>
        <legend>Libro a reservar</legend>

        <?php for ($i = 0; $i < $filas; $i++): ?>
          <?php
          $n = $i + 1;
          $libroElegido = $valores['libro'][$i] ?? '';
          $errorLibro = $errores['libro'][$i] ?? null;
          $errorCantidad = $errores['cantidad'][$i] ?? null;
          ?>
          <p>
            <label for="libro-<?= e($n) ?>">Título</label><br>
            <select id="libro-<?= e($n) ?>" name="libro[]" required<?= $invalido($errorLibro, "libro-$n") ?>>
              <option value="">Seleccioná un libro del catálogo...</option>
              <?php foreach ($libros as $libro): ?>
                <option value="<?= e($libro->id) ?>"<?= $libro->id === $libroElegido ? ' selected' : '' ?>><?= e($libro->titulo) ?> - <?= e($libro->autor) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $mensaje($errorLibro, "libro-$n") ?>
          </p>

          <p>
            <label for="cantidad-<?= e($n) ?>">Cantidad</label><br>
            <input type="number" id="cantidad-<?= e($n) ?>" name="cantidad[]" required step="1"
                   min="<?= e(ReservaValidator::CANTIDAD_MIN) ?>" max="<?= e(ReservaValidator::CANTIDAD_MAX) ?>"
                   value="<?= e($valores['cantidad'][$i] ?? '1') ?>"<?= $invalido($errorCantidad, "cantidad-$n") ?>>
            <?= $mensaje($errorCantidad, "cantidad-$n") ?>
          </p>
        <?php endfor; ?>
      </fieldset>

      <p>
        <button type="submit">Confirmar reserva</button>
      </p>
    </form>
