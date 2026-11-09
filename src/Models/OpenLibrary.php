<?php

namespace Paw\Models;

use Psr\Log\LoggerInterface;

// Conexión con la API pública de Open Library (https://openlibrary.org/developers/api). Sirve para:
//  - buscar(): buscar un libro por ISBN o por título y autor (para autocompletar el alta de libros);
//  - obra(): traer los datos completos de un resultado (descripción y biografía del autor);
//  - tapaPara(): conseguir la tapa de un libro que se guardó sin imagen.
// Open Library no pide clave. Si no responde, las funciones devuelven vacío y lo dejan en el log:
// el alta de libros funciona igual, sin autocompletar o con la portada por defecto.
class OpenLibrary
{
    public const URL = 'https://openlibrary.org';
    public const URL_TAPA = 'https://covers.openlibrary.org/b/id/%d-L.jpg';
    public const TIMEOUT = 8;
    public const MAX_RESULTADOS = 5;

    /** @var callable(string $url): array{0: int, 1: string} */
    private $http;

    /** @param null|callable $http Reemplaza el GET real (lo usan los tests) */
    public function __construct(private LoggerInterface $logger, ?callable $http = null)
    {
        $this->http = $http ?? [self::class, 'get'];
    }

    // Un ISBN tiene 10 o 13 caracteres (dígitos y, en el de 10, una X al final), con o sin guiones
    public static function esIsbn(string $texto): bool
    {
        $limpio = str_replace(['-', ' '], '', $texto);
        return preg_match('/^(\d{9}[\dXx]|\d{13})$/', $limpio) === 1;
    }

    /**
     * Busca por ISBN o por texto (título, autor). Devuelve hasta MAX_RESULTADOS resultados.
     *
     * @return array<int, array{clave: string, titulo: string, autor: string, anio: int|null, portada: string}>
     */
    public function buscar(string $texto): array
    {
        $consulta = [
            'fields' => 'key,title,author_name,first_publish_year,cover_i',
            'limit' => self::MAX_RESULTADOS,
        ];
        if (self::esIsbn($texto)) {
            $consulta['isbn'] = str_replace(['-', ' '], '', $texto);
        } else {
            $consulta['q'] = $texto;
        }

        $datos = $this->pedirJson('/search.json?' . http_build_query($consulta));
        if ($datos === null || !is_array($datos['docs'] ?? null)) {
            return [];
        }

        $resultados = [];
        foreach ($datos['docs'] as $doc) {
            if (!is_string($doc['key'] ?? null) || !is_string($doc['title'] ?? null)) {
                continue;
            }
            $resultados[] = [
                'clave' => $doc['key'],
                'titulo' => $doc['title'],
                'autor' => is_string($doc['author_name'][0] ?? null) ? $doc['author_name'][0] : '',
                'anio' => is_int($doc['first_publish_year'] ?? null) ? $doc['first_publish_year'] : null,
                'portada' => self::urlTapa($doc['cover_i'] ?? null),
            ];
        }
        return $resultados;
    }

    /**
     * Datos completos de una obra a partir de su clave (/works/OL45804W), para llenar el formulario.
     * null si la clave no es válida o la obra no existe.
     *
     * @return null|array{titulo: string, autor: string, descripcion: string, autor_bio: string, portada: string}
     */
    public function obra(string $clave): ?array
    {
        // Solo claves de obras: así nunca se arma una dirección con lo que mandó el usuario
        if (preg_match('#^/works/OL\d+W$#', $clave) !== 1) {
            return null;
        }
        $obra = $this->pedirJson($clave . '.json');
        if ($obra === null || !is_string($obra['title'] ?? null)) {
            return null;
        }

        $autor = '';
        $bio = '';
        $claveAutor = $obra['authors'][0]['author']['key'] ?? null;
        if (is_string($claveAutor) && preg_match('#^/authors/OL\d+A$#', $claveAutor) === 1) {
            $datosAutor = $this->pedirJson($claveAutor . '.json');
            $autor = is_string($datosAutor['name'] ?? null) ? $datosAutor['name'] : '';
            $bio = self::texto($datosAutor['bio'] ?? null);
        }

        return [
            'titulo' => mb_substr($obra['title'], 0, LibroValidator::TITULO_MAX),
            'autor' => mb_substr($autor, 0, LibroValidator::AUTOR_MAX),
            'descripcion' => mb_substr(self::texto($obra['description'] ?? null), 0, LibroValidator::DESCRIPCION_MAX),
            'autor_bio' => mb_substr($bio, 0, LibroValidator::AUTOR_BIO_MAX),
            'portada' => self::urlTapa($obra['covers'][0] ?? null),
        ];
    }

    // Dirección de la tapa del primer resultado para ese título y autor, o '' si Open Library no tiene
    public function tapaPara(string $titulo, string $autor): string
    {
        $consulta = http_build_query(['title' => $titulo, 'author' => $autor, 'fields' => 'cover_i', 'limit' => 1]);
        $datos = $this->pedirJson('/search.json?' . $consulta);
        return self::urlTapa($datos['docs'][0]['cover_i'] ?? null);
    }

    private static function urlTapa(mixed $id): string
    {
        // Open Library usa -1 cuando no tiene la imagen
        return is_int($id) && $id > 0 ? sprintf(self::URL_TAPA, $id) : '';
    }

    // La descripción y la biografía vienen como texto o como {"type": "/type/text", "value": "..."}.
    // Vienen en Markdown y muchas traen al final notas con enlaces ("----------" y "[1]: https://..."):
    // se cortan esas notas y se sacan los asteriscos, porque el sitio los muestra como texto.
    private static function texto(mixed $valor): string
    {
        if (is_array($valor)) {
            $valor = $valor['value'] ?? '';
        }
        if (!is_string($valor)) {
            return '';
        }
        $valor = explode("\n----------", $valor)[0];
        $valor = (string) preg_replace('/\s*\(\[[^\]]*\]\[\d+\]\)/', '', $valor); // ([fuente][1])
        $valor = (string) preg_replace('/^\[\d+\]:.*$/m', '', $valor);           // [1]: https://...
        $valor = (string) preg_replace('/\*+([^*\n]+)\*+/', '$1', $valor);        // *cursiva* y **negrita**
        return trim(str_replace("\r\n", "\n", $valor));
    }

    private function pedirJson(string $ruta): ?array
    {
        try {
            [$status, $cuerpo] = ($this->http)(self::URL . $ruta);
        } catch (\RuntimeException $e) {
            $this->logger->warning('Open Library no respondió: {error}', ['error' => $e->getMessage()]);
            return null;
        }
        if ($status !== 200) {
            $this->logger->warning('Open Library respondió HTTP {status} a {ruta}', ['status' => $status, 'ruta' => $ruta]);
            return null;
        }
        $datos = json_decode($cuerpo, true);
        return is_array($datos) ? $datos : null;
    }

    /**
     * GET HTTPS con las funciones nativas de PHP (igual que el correo, sin cURL ni librerías).
     *
     * @return array{0: int, 1: string} [código HTTP, cuerpo]
     * @throws \RuntimeException si no hay conexión o se vence el tiempo de espera
     */
    public static function get(string $url): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'GET',
                // Open Library pide que las aplicaciones se identifiquen con un User-Agent propio
                'header' => "User-Agent: PawPrints/0.5 (contacto@pawprints.com.ar)\r\nAccept: application/json",
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);
        $respuesta = @file_get_contents($url, false, $contexto);
        if ($respuesta === false) {
            throw new \RuntimeException(error_get_last()['message'] ?? 'sin detalle');
        }
        $cabeceras = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        // Si hubo redirección hay varias líneas "HTTP/..."; vale la última
        $status = 0;
        foreach ($cabeceras as $linea) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m)) {
                $status = (int) $m[1];
            }
        }
        return [$status, $respuesta];
    }
}
