    <h2>Catálogo</h2>

    <form class="filtros" action="<?= e($view->url('/catalogo')) ?>" method="get" aria-labelledby="filtro-heading">
      <fieldset>
        <legend id="filtro-heading">Filtrar</legend>

        <label for="genero">Género</label>
        <select id="genero" name="genero">
          <?php foreach (['' => 'Todos', 'novela' => 'Novela', 'cuento' => 'Cuento', 'poesia' => 'Poesía', 'infantil' => 'Infantil'] as $valor => $etiqueta): ?>
            <option value="<?= e($valor) ?>"<?= $filtros['genero'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="autor">Autor</label>
        <input type="text" id="autor" name="autor" placeholder="Nombre del autor" value="<?= e($filtros['autor']) ?>">

        <label for="precio-max">Precio máximo</label>
        <input type="number" id="precio-max" name="precio_max" min="0" step="1" value="<?= e($filtros['precio_max']) ?>">
      </fieldset>

      <fieldset>
        <legend>Ordenar</legend>
        <label for="orden">Ordenar por</label>
        <select id="orden" name="orden">
          <?php foreach (['relevancia' => 'Relevancia', 'precio-asc' => 'Precio: menor a mayor', 'precio-desc' => 'Precio: mayor a menor', 'titulo' => 'Título (A-Z)'] as $valor => $etiqueta): ?>
            <option value="<?= e($valor) ?>"<?= $filtros['orden'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="per-page">Libros por página</label>
        <select id="per-page" name="per_page">
          <?php foreach ([6, 12, 24] as $cantidad): ?>
            <option value="<?= e($cantidad) ?>"<?= $porPagina === $cantidad ? ' selected' : '' ?>><?= e($cantidad) ?></option>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <button type="submit">Aplicar</button>
    </form>

    <section aria-labelledby="listado-heading">
      <h2 id="listado-heading">Libros disponibles</h2>
      <p>Mostrando <?= e($desde) ?>-<?= e($hasta) ?> de <?= e($total) ?> libros</p>

      <?php if ($total === 0): ?>
        <p>No encontramos libros con esos filtros</p>
      <?php else: ?>
      <ul class="catalogo-lista">
        <?php foreach ($libros as $libro): ?>
          <?php $detalle = $view->url('/catalogo/' . rawurlencode($libro->id)); ?>
          <li>
            <article aria-labelledby="libro-<?= e($libro->id) ?>-titulo">
              <a href="<?= e($detalle) ?>">
                <figure>
                  <img src="<?= e($view->url($libro->portada)) ?>" alt="Portada de <?= e($libro->titulo) ?>" width="120" height="180">
                  <figcaption><?= e($libro->titulo) ?></figcaption>
                </figure>
              </a>
              <h3 id="libro-<?= e($libro->id) ?>-titulo"><a href="<?= e($detalle) ?>"><?= e($libro->titulo) ?></a></h3>
              <p>Autor: <?= e($libro->autor) ?></p>
              <p>Precio: <data value="<?= e($libro->precio) ?>"><?= e($libro->precioFormateado()) ?></data></p>
              <p><?= e($libro->resumen) ?></p>
              <a class="btn" href="<?= e($detalle) ?>">Ver detalle</a>
            </article>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if ($totalPaginas > 1): ?>
        <?php $consulta = $filtros + ['per_page' => $porPagina]; ?>
        <nav class="paginacion" aria-label="Páginas del catálogo">
          <ul>
            <li>
              <?php if ($pagina > 1): ?>
                <a href="<?= e($view->url('/catalogo', $consulta + ['page' => $pagina - 1])) ?>" rel="prev">Anterior</a>
              <?php else: ?>
                <span aria-disabled="true">Anterior</span>
              <?php endif; ?>
            </li>
            <?php for ($numero = 1; $numero <= $totalPaginas; $numero++): ?>
              <li>
                <?php if ($numero === $pagina): ?>
                  <span aria-current="page" aria-label="<?= e('Página ' . $numero) ?>"><?= e($numero) ?></span>
                <?php else: ?>
                  <a href="<?= e($view->url('/catalogo', $consulta + ['page' => $numero])) ?>" aria-label="<?= e('Página ' . $numero) ?>"><?= e($numero) ?></a>
                <?php endif; ?>
              </li>
            <?php endfor; ?>
            <li>
              <?php if ($pagina < $totalPaginas): ?>
                <a href="<?= e($view->url('/catalogo', $consulta + ['page' => $pagina + 1])) ?>" rel="next">Siguiente</a>
              <?php else: ?>
                <span aria-disabled="true">Siguiente</span>
              <?php endif; ?>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </section>
