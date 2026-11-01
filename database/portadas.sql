-- PAWPrints · tapas de los libros del catálogo (imágenes de Open Library, ediciones en español).
-- Correr después de seed.sql. Los libros sin una tapa confiable se borran del catálogo.

UPDATE libros AS l SET imagen_url = 'https://covers.openlibrary.org/b/id/' || t.tapa || '-L.jpg'
FROM (VALUES
  ('rayuela',               1047466),
  ('ficciones',             10832290),
  ('el-aleph',              14408958),
  ('bestiario',             5721778),
  ('veinte-poemas-de-amor', 747596),
  ('desolacion',            11618024),
  ('languidez',             13993184),
  ('los-heraldos-negros',   11773080),
  ('el-rayo-que-no-cesa',   12337025),
  ('cuentos-de-la-selva',   13500123),
  ('el-principito',         13499066),
  ('dailan-kifki',          6425486)
) AS t(slug, tapa)
WHERE l.slug = t.slug;

DELETE FROM libros WHERE slug IN (
  'cien-anos-de-soledad',
  'la-casa-de-los-espiritus',
  'el-tunel',
  'aura',
  'el-llano-en-llamas',
  'pedro-paramo',
  'la-invencion-de-morel',
  'cuentopos-de-gulubu'
);
