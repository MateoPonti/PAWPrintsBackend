<?php

namespace Paw\Controllers;

use Paw\Core\HttpException;
use Paw\Core\Request;
use Paw\Core\Response;
use Paw\Models\PortadaRepository;
use Paw\Models\PortadaValidator;

// Tapas de los libros. El alta (solo admin) recibe la imagen tal cual en el cuerpo del pedido,
// con su Content-Type (image/jpeg, image/png o image/webp), sin multipart ni JSON.
class PortadaController extends Controller
{
    // POST /api/admin/portadas -> 201 {portada: {id, url}}
    public function store(Request $request): Response
    {
        $this->exigirAdmin();

        $tipo = $request->contentType();
        $datos = $request->body();
        $error = PortadaValidator::validar($tipo, $datos);
        if ($error !== '') {
            throw HttpException::invalid(['portada' => $error], $error);
        }

        $id = (new PortadaRepository($this->app->pdo()))->guardar($tipo, $datos);
        $this->app->logger->info('Portada subida: {id} por {usuario}', ['id' => $id, 'usuario' => $this->app->usuario()?->email]);

        // La URL es relativa a la API: el front le agrega la dirección del back antes de usarla como imagen_url
        $url = '/api/portadas/' . $id;
        return $this->json(['portada' => ['id' => $id, 'url' => $url]], 201)->withHeader('Location', $url);
    }

    // GET /api/portadas/{id} -> la imagen
    public function show(Request $request, string $id): Response
    {
        $this->exigirBase();
        if (!ctype_digit($id)) {
            throw HttpException::notFound('La portada que buscás no existe.');
        }
        $portada = (new PortadaRepository($this->app->pdo()))->find((int) $id);
        if ($portada === null) {
            throw HttpException::notFound('La portada que buscás no existe.');
        }
        // Una portada no cambia nunca (si se sube otra, tiene otro id): el navegador la puede guardar sin preguntar
        return Response::archivo($portada['datos'], $portada['tipo'])
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
