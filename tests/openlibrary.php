<?php

// Pruebas de la conexión con Open Library (autocompletar el alta y tapa automática).
// Se usa un HTTP falso con respuestas armadas a mano: no se conecta a Internet.
// Ejecutar desde la raíz: php tests/openlibrary.php

use Paw\Models\OpenLibrary;
use Psr\Log\NullLogger;

require dirname(__DIR__) . '/vendor/autoload.php';

function afirmar(bool $condicion, string $caso): void
{
    if (!$condicion) {
        throw new RuntimeException($caso);
    }
    echo "OK: $caso\n";
}

// Responde según el principio de la ruta pedida y guarda las direcciones pedidas
$pedidas = [];
$respuestas = [
    '/search.json' => [200, json_encode(['docs' => [
        ['key' => '/works/OL1W', 'title' => 'Rayuela', 'author_name' => ['Julio Cortázar'], 'first_publish_year' => 1963, 'cover_i' => 123],
        ['key' => '/works/OL2W', 'title' => 'Sin tapa', 'cover_i' => -1],
        ['title' => 'Sin clave'],
    ]])],
    '/works/OL1W.json' => [200, json_encode([
        'title' => 'Rayuela',
        'description' => ['type' => '/type/text', 'value' => "*Rayuela* es una novela ([fuente][1]).\n\n----------\n[1]: https://ejemplo.com"],
        'authors' => [['author' => ['key' => '/authors/OL9A']]],
        'covers' => [456],
    ])],
    '/authors/OL9A.json' => [200, json_encode(['name' => 'Julio Cortázar', 'bio' => 'Escritor argentino.'])],
];
$http = static function (string $url) use (&$pedidas, $respuestas): array {
    $pedidas[] = $url;
    $ruta = substr($url, strlen(OpenLibrary::URL));
    foreach ($respuestas as $inicio => $respuesta) {
        if (str_starts_with($ruta, $inicio)) {
            return $respuesta;
        }
    }
    return [404, '{}'];
};
$ol = new OpenLibrary(new NullLogger(), $http);

afirmar(OpenLibrary::esIsbn('978-84-376-0457-2') && OpenLibrary::esIsbn('843760457X') && !OpenLibrary::esIsbn('rayuela'), 'Reconoce un ISBN');

$r = $ol->buscar('rayuela cortazar');
afirmar(count($r) === 2, 'Búsqueda: descarta resultados sin clave');
afirmar($r[0] === ['clave' => '/works/OL1W', 'titulo' => 'Rayuela', 'autor' => 'Julio Cortázar', 'anio' => 1963,
    'portada' => 'https://covers.openlibrary.org/b/id/123-L.jpg'], 'Búsqueda: forma del resultado');
afirmar($r[1]['portada'] === '' && $r[1]['autor'] === '', 'Búsqueda: sin tapa (-1) ni autor quedan vacíos');
afirmar(str_contains(end($pedidas), 'q=rayuela+cortazar'), 'Búsqueda por texto usa q=');
$ol->buscar('978-84-376-0457-2');
afirmar(str_contains(end($pedidas), 'isbn=9788437604572'), 'Búsqueda por ISBN usa isbn= sin guiones');

$obra = $ol->obra('/works/OL1W');
afirmar($obra['autor'] === 'Julio Cortázar' && $obra['autor_bio'] === 'Escritor argentino.', 'Obra: trae el autor y su biografía');
afirmar($obra['descripcion'] === 'Rayuela es una novela.', 'Obra: limpia el Markdown y las notas de la descripción');
afirmar($obra['portada'] === 'https://covers.openlibrary.org/b/id/456-L.jpg', 'Obra: tapa');
$antes = count($pedidas);
afirmar($ol->obra('https://otro-sitio.com/x') === null && $ol->obra('/works/OL1W/../../x') === null, 'Obra: rechaza claves que no son de una obra');
afirmar(count($pedidas) === $antes, 'Obra: con una clave inválida no se conecta a nada');
afirmar($ol->obra('/works/OL7W') === null, 'Obra inexistente (404): null');

afirmar($ol->tapaPara('Rayuela', 'Cortázar') === 'https://covers.openlibrary.org/b/id/123-L.jpg', 'Tapa para un libro sin imagen');

$caido = new OpenLibrary(new NullLogger(), static function (): array {
    throw new RuntimeException('sin conexión');
});
afirmar($caido->buscar('rayuela') === [] && $caido->obra('/works/OL1W') === null && $caido->tapaPara('a', 'b') === '', 'Open Library caído: vacío, sin romper el alta');

$roto = new OpenLibrary(new NullLogger(), static fn (): array => [200, 'no es json']);
afirmar($roto->buscar('rayuela') === [], 'Respuesta que no es JSON: vacío');

echo "\nTodo OK\n";
