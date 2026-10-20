<?php
// Estructura común de todas las páginas. Recibe $content (la vista ya renderizada),
// $title, $description y $current (clave del ítem activo del menú).
$current ??= '';
$menu = [
    'inicio' => ['/', 'Inicio'],
    'catalogo' => ['/catalogo', 'Catálogo'],
    'reserva' => ['/reserva', 'Formulario de Reserva'],
    'nosotros' => ['/nosotros', 'Nuestra Historia'],
    'contacto' => ['/contacto', 'Contacto'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'PawPrints') ?></title>
  <meta name="description" content="<?= e($description ?? 'PawPrints, librería online y física.') ?>">
  <link rel="stylesheet" href="<?= e($view->url('styles/style.css')) ?>">
  <link rel="stylesheet" href="<?= e($view->url('styles/print.css')) ?>" media="print">
</head>
<body>

  <header>
    <h1>PawPrints</h1>
    <?php if ($current === 'inicio'): ?>
      <p>Tu librería de siempre, ahora también online.</p>
    <?php endif; ?>
  </header>

  <nav aria-label="Navegación principal">
    <ul>
      <?php foreach ($menu as $key => [$path, $label]): ?>
        <li><a href="<?= e($view->url($path)) ?>"<?= $key === $current ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
      <?php endforeach; ?>
      <?php if ($cuentasHabilitadas ?? false): ?>
        <?php $usuario = isset($usuarioActual) ? $usuarioActual() : null; ?>
        <?php if ($usuario === null): ?>
          <li><a href="<?= e($view->url('/login')) ?>"<?= $current === 'login' ? ' aria-current="page"' : '' ?>>Ingresar</a></li>
          <li><a href="<?= e($view->url('/registro')) ?>"<?= $current === 'registro' ? ' aria-current="page"' : '' ?>>Crear cuenta</a></li>
        <?php else: ?>
          <?php if ($usuario->esAdmin()): ?>
            <li><a href="<?= e($view->url('/admin/libros/nuevo')) ?>"<?= $current === 'admin' ? ' aria-current="page"' : '' ?>>Cargar libro</a></li>
          <?php endif; ?>
          <li>
            <form class="menu-sesion" action="<?= e($view->url('/logout')) ?>" method="post">
              <input type="hidden" name="_csrf" value="<?= e($csrfToken()) ?>">
              <button type="submit">Salir (<?= e($usuario->nombre) ?>)</button>
            </form>
          </li>
        <?php endif; ?>
      <?php endif; ?>
    </ul>
  </nav>

  <main>
<?= $content ?>
  </main>

  <footer>
    <address>
      Av. Siempre Viva 742, Luján, Buenos Aires<br>
      Teléfono: <a href="tel:+542323123456">(02323) 12-3456</a><br>
      Email: <a href="mailto:contacto@pawprints.com.ar">contacto@pawprints.com.ar</a>
    </address>

    <nav aria-label="Redes sociales">
      <ul>
        <li><a href="https://instagram.com/pawprints" target="_blank" rel="noopener noreferrer">Instagram</a></li>
        <li><a href="https://facebook.com/pawprints" target="_blank" rel="noopener noreferrer">Facebook</a></li>
        <li><a href="https://twitter.com/pawprints" target="_blank" rel="noopener noreferrer">Twitter/X</a></li>
      </ul>
    </nav>

    <p><small>&copy; <?= date('Y') ?> PawPrints. Todos los derechos reservados.</small></p>
  </footer>

</body>
</html>
