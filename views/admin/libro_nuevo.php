<?php
use Paw\Models\LibroValidator;

// Alta de libros (solo admin). Los límites salen de LibroValidator para que HTML y backend validen lo mismo.
$errores ??= [];

$invalido = static fn (?string $error, string $id): string =>
    $error === null ? '' : ' aria-invalid="true" aria-describedby="' . e($id) . '-error"';
$mensaje = static fn (?string $error, string $id): string =>
    $error === null ? '' : '<span class="error-campo" id="' . e($id) . '-error">' . e($error) . '</span>';
?>
    <h2>Cargar un libro</h2>
    <p>El libro nuevo aparece en el catálogo apenas se guarda. La portada es opcional: si la dejás vacía se usa la imagen por defecto.</p>

    <?php if ($errores !== []): ?>
      <div class="aviso-error" role="alert">
        <p>No pudimos guardar el libro. Revisá los campos marcados.</p>
      </div>
    <?php endif; ?>

    <form action="<?= e($view->url('/admin/libros')) ?>" method="post">
      <input type="hidden" name="_csrf" value="<?= e($csrfToken()) ?>">
      <fieldset>
        <legend>Datos del libro</legend>

        <p>
          <label for="titulo">Título</label><br>
          <input type="text" id="titulo" name="titulo" required maxlength="<?= e(LibroValidator::TITULO_MAX) ?>"
                 value="<?= e($valores['titulo']) ?>"<?= $invalido($errores['titulo'] ?? null, 'titulo') ?>>
          <?= $mensaje($errores['titulo'] ?? null, 'titulo') ?>
        </p>

        <p>
          <label for="autor">Autor</label><br>
          <input type="text" id="autor" name="autor" required maxlength="<?= e(LibroValidator::AUTOR_MAX) ?>"
                 value="<?= e($valores['autor']) ?>"<?= $invalido($errores['autor'] ?? null, 'autor') ?>>
          <?= $mensaje($errores['autor'] ?? null, 'autor') ?>
        </p>

        <p>
          <label for="genero">Género</label><br>
          <select id="genero" name="genero" required<?= $invalido($errores['genero'] ?? null, 'genero') ?>>
            <option value="">Elegí un género...</option>
            <?php foreach (LibroValidator::GENEROS as $valor => $etiqueta): ?>
              <option value="<?= e($valor) ?>"<?= $valores['genero'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $mensaje($errores['genero'] ?? null, 'genero') ?>
        </p>

        <p>
          <label for="precio">Precio (en pesos)</label><br>
          <input type="number" id="precio" name="precio" required step="1"
                 min="<?= e(LibroValidator::PRECIO_MIN) ?>" max="<?= e(LibroValidator::PRECIO_MAX) ?>"
                 value="<?= e($valores['precio']) ?>"<?= $invalido($errores['precio'] ?? null, 'precio') ?>>
          <?= $mensaje($errores['precio'] ?? null, 'precio') ?>
        </p>
      </fieldset>

      <fieldset>
        <legend>Descripción</legend>

        <p>
          <label for="resumen">Resumen</label><br>
          <input type="text" id="resumen" name="resumen" maxlength="<?= e(LibroValidator::RESUMEN_MAX) ?>"
                 value="<?= e($valores['resumen']) ?>"<?= $invalido($errores['resumen'] ?? null, 'resumen') ?>>
          <?= $mensaje($errores['resumen'] ?? null, 'resumen') ?>
        </p>

        <p>
          <label for="descripcion">Descripción</label><br>
          <textarea id="descripcion" name="descripcion" rows="5" maxlength="<?= e(LibroValidator::DESCRIPCION_MAX) ?>"<?= $invalido($errores['descripcion'] ?? null, 'descripcion') ?>><?= e($valores['descripcion']) ?></textarea>
          <?= $mensaje($errores['descripcion'] ?? null, 'descripcion') ?>
        </p>

        <p>
          <label for="autor_bio">Sobre el autor</label><br>
          <textarea id="autor_bio" name="autor_bio" rows="3" maxlength="<?= e(LibroValidator::AUTOR_BIO_MAX) ?>"<?= $invalido($errores['autor_bio'] ?? null, 'autor_bio') ?>><?= e($valores['autor_bio']) ?></textarea>
          <?= $mensaje($errores['autor_bio'] ?? null, 'autor_bio') ?>
        </p>

        <p>
          <label for="imagen_url">Portada (dirección de la imagen, opcional)</label><br>
          <input type="url" id="imagen_url" name="imagen_url" maxlength="<?= e(LibroValidator::IMAGEN_URL_MAX) ?>"
                 placeholder="https://..." value="<?= e($valores['imagen_url']) ?>"<?= $invalido($errores['imagen_url'] ?? null, 'imagen_url') ?>>
          <?= $mensaje($errores['imagen_url'] ?? null, 'imagen_url') ?>
        </p>
      </fieldset>

      <button type="submit">Guardar libro</button>
    </form>
