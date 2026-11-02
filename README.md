# PAWPrints - back-end (API)

API JSON de la librería PAWPrints. Trabajos prácticos de Programación en Ambiente Web (11086), Comisión 50, UNLu.

Integrantes: Federico Kasparian, Justino Bernal, Lautaro Marino y Ponti Mateo. Los legajos están en `autores.txt` y la versión en `VERSION`.

- **API publicada (Render):** https://pawprintsbackend.onrender.com (por ejemplo `/api/libros`)

Este proyecto es **solo la API**: no genera HTML. El sitio (HTML, CSS y JavaScript) está en el proyecto `Frontend`, que pide los datos a esta API con `fetch`. Hecha en PHP sin framework, con un único punto de entrada.

## Cómo ejecutar

Se necesita PHP 8.1 o superior (con `pdo_pgsql` si se usa la base) y Composer.

```
git clone https://github.com/MateoPonti/PAWPrintsBackend.git
cd PAWPrintsBackend
composer install
cp .env.example .env          # y completar los datos de la base (ver abajo)
php -S localhost:8000 -t public public/index.php
```

Probar: `http://localhost:8000/api/libros`. Para usarla con el sitio, levantar el front (`python3 -m http.server 5500` en su carpeta) y que el front apunte a `http://localhost:8000` (ver el README del front). Con `APP_ENV=development` se acepta cualquier puerto de localhost; si no, el origen del front tiene que estar en `FRONT_URL`.

En un servidor Apache el document root tiene que ser la carpeta `public/`.

### Base de datos local (Docker)

Para desarrollar con base sin tocar Neon hay una PostgreSQL en `docker-compose.yml` (requiere Docker Desktop abierto):

```
docker compose up -d
```

La primera vez carga `database/schema.sql` y `database/seed.sql` (los 20 libros). Queda en el puerto **5434**, para no chocar con un Postgres instalado en 5432. En el `.env`:

```
DB_HOST=127.0.0.1
DB_PORT=5434
DB_NAME=pawprints
DB_USER=pawprints
DB_PASS=pawprints
DB_SSLMODE=disable
```

Apagar: `docker compose down` (los datos quedan). Empezar de cero: `docker compose down -v` y de nuevo `docker compose up -d`. Para el primer administrador (ver más abajo) la consola de la base es `docker exec -it pawprints-db psql -U pawprints -d pawprints`.

## Entorno de prueba

Versiones con las que se desarrolló y probó (lo pide la guía de entrega). En local la base corre en Docker y en producción el back corre en la imagen Docker del `Dockerfile`, así que esas versiones son las mismas en cualquier máquina.

| | Local (desarrollo) | Producción |
|---|---|---|
| Sistema operativo | Windows 11 Pro (build 26200) | Debian (imagen `php:8.3-apache`) en Render |
| PHP | 8.4.10 CLI, con `pdo_pgsql`, `mbstring` y `fileinfo` | 8.3.36 + Apache |
| Composer | 2.10.3 | 2.11.1 (imagen `composer:2`) |
| PostgreSQL | 17.11 en Docker (imagen `postgres:17`) | Neon (PostgreSQL administrado) |
| Docker | Docker Desktop 4.92.0 (Engine 29.8.0, Compose v5.5.1) | — |
| Git | 2.55.0 | — |
| Navegadores | Chrome 156 y Edge 156 | — |

El código sólo usa funciones de PHP 8.2 o anteriores, así que la diferencia entre 8.4 (local) y 8.3 (producción) no afecta.

## Variables de entorno (`.env`)

`.env` no se sube al repo; `.env.example` es la plantilla. En Render las mismas variables se cargan en *Environment*.

| Variable | Uso |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `development` / `true` agrega el campo `detalle` a los errores 500. En producción: `production` / `false`. |
| `FRONT_URL` | Orígenes desde los que el navegador puede usar la API (CORS): la dirección donde se publica el front, sin barra final. Varios, separados por coma. **Si falta el del front publicado, el navegador bloquea todos los pedidos.** |
| `SESION_TTL` | Segundos de validez de la sesión (por defecto 28800, 8 horas). |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE` | Base PostgreSQL (Neon). Con `DB_HOST` vacío el catálogo sale de `data/libros.json`, sin cuentas ni alta de libros. |
| `MAIL_API_KEY` | API key de Brevo para el aviso de reservas (ver [Correo de reservas](#correo-de-reservas-brevo)). **Secreta.** Vacía: no se envía nada y el correo queda en `logs/app.log`. |
| `MAIL_TO`, `MAIL_FROM`, `MAIL_FROM_NAME` | Quién recibe el aviso, el remitente (verificado en Brevo) y el nombre que se muestra. Por defecto, el mail del proyecto. |
| `LOG_LEVEL` | Nivel mínimo del log. |

## Arquitectura

```
public/
  index.php        Front controller: toda petición entra por acá
  .htaccess        Redirige todo a index.php (Apache)
config/
  config.php       Configuración, toma los valores del .env
  routes.php       Mapa de URLs a controladores
src/
  Core/            Infraestructura común (no sabe nada de libros ni reservas)
  Controllers/     Un método por endpoint
  Models/          Libros, usuarios, reservas, validaciones y acceso a datos
data/libros.json   Catálogo de respaldo cuando no hay base
database/          schema.sql y seed.sql (los 20 libros)
logs/              Registro de la aplicación (no se sube al repo)
tests/             Pruebas (php tests/<archivo>.php)
```

| Problemática | Dónde |
|---|---|
| Petición HTTP | `Core/Request`: método, path, query string, cuerpo JSON, cabeceras y cookies. Los controladores no leen `$_GET` ni `$_SERVER`. |
| Mapeo de URLs | `Core/Router` y `config/routes.php`. Parámetros (`/api/libros/{id}`), 404 y 405 con `Allow`. |
| Respuesta | `Core/Response::json()`. Todas las respuestas, también los errores, son JSON, salvo las imágenes de `GET /api/portadas/{id}` (`Response::archivo()`). |
| Errores | `Core/ErrorHandler` + `Core/HttpException`: todo error se registra en el log y sale como `{"error", "mensaje", "errores"}`. En producción el 500 es un mensaje genérico. |
| Autenticación | `Core/Auth` + `Core/SesionDb`: sesión de PHP (`$_SESSION`) guardada en la tabla `sesiones` (el disco de Render se borra en cada despliegue). La cookie `pawprints_sesion` solo lleva el id (`HttpOnly`, `Secure`, `SameSite=Strict`). Los POST con sesión exigen el token CSRF en `X-CSRF-Token`. El rol se consulta en la base en cada pedido. |
| CORS | `Core/Cors`: responde el preflight (`OPTIONS`) y autoriza solo los orígenes de `FRONT_URL`. |
| Persistencia | `Models/LibroRepository` (JSON) y `Models/LibroDbRepository` (Neon); `App::libros()` elige. Los controladores no saben de dónde salen los libros. |
| Logs | `Core/FileLogger` (PSR-3) en `logs/app.log`. |
| Autoload | Composer, namespace `Paw\` apuntando a `src/` (PSR-4). |

## Contrato de la API

Los errores tienen siempre la forma `{"error": "codigo", "mensaje": "...", "errores": {"campo": "mensaje"}}` (`errores` solo en los 422). Los pedidos con cuerpo mandan JSON (`Content-Type: application/json`); un cuerpo que no es un objeto JSON válido responde 400.

| Método y ruta | Acceso | Cuerpo / parámetros | Respuesta |
|---|---|---|---|
| `GET /` y `GET /api/health` | público | - | `{estado: "ok", ...}`. No tocan la base: sirven de health check. |
| `GET /api/libros` | público | `genero`, `autor`, `precio_max`, `orden`, `page`, `per_page` | `{items, total, pagina, por_pagina, paginas}`. 404 si `page` supera `paginas`. |
| `GET /api/libros/{id}` | público | `id` = slug | `{libro}` o 404 |
| `POST /api/reservas` | público | `{nombre, email, telefono, items: [{libro, cantidad}]}` | `201 {reserva}`, 422 con errores o 503 si falla el correo |
| `POST /api/registro` | público | `nombre`, `email`, `password`, `password_confirm` | `201 {usuario}`, 422 con errores. No inicia sesión. |
| `POST /api/login` | público | `email`, `password` | `{csrf, expira_en, usuario}` + cookie de sesión, o 422 con `mensaje` |
| `POST /api/logout` | público | - | `204`: destruye la sesión en el servidor y borra la cookie |
| `GET /api/me` | sesión | - | `{usuario, csrf}` o 401 |
| `POST /api/admin/libros` | admin + CSRF | `titulo`, `autor`, `genero`, `precio`, `resumen`, `descripcion`, `autor_bio`, `imagen_url` | `201 {libro}`, 401, 403 o 422 con errores |
| `POST /api/admin/portadas` | admin + CSRF | La imagen tal cual en el cuerpo, con `Content-Type: image/jpeg`, `image/png` o `image/webp` | `201 {portada: {id, url}}`, 401, 403 o 422 con `errores.portada` |
| `GET /api/portadas/{id}` | público | `id` numérico | La imagen (no es JSON) o 404 |

Códigos: 400 cuerpo inválido, 401 sin sesión o sesión vencida, 403 sin permisos o token CSRF incorrecto, 404 no existe, 405 método no permitido, 422 datos inválidos, 503 función no disponible (sin base de datos, base caída o falló el correo).

### Catálogo

`GET /api/libros` recibe los filtros y la paginación por query string:

| Parámetro | Valores y comportamiento |
|---|---|
| `genero` | Coincidencia exacta: `novela`, `cuento`, `poesia` o `infantil`. Vacío: todos. |
| `autor` | Parte del nombre, sin distinguir mayúsculas ni acentos. |
| `precio_max` | Precio máximo inclusivo, entero mayor o igual a 0. Vacío o inválido: sin límite. |
| `orden` | `relevancia` (orden de carga), `precio-asc`, `precio-desc` o `titulo`. Inválido: `relevancia`. |
| `page` | Entero mayor o igual a 1. Por defecto 1. Inválido o fuera de rango: 404. |
| `per_page` | De 1 a 100. Ausente o inválido: 6. |
| `formato` | `csv`: devuelve todos los resultados de esa búsqueda, sin paginar, como archivo CSV para descargar (`text/csv`, RFC 4180 con BOM para Excel). Las celdas que empiezan con `=`, `+`, `-` o `@` llevan un apóstrofo adelante para que Excel no las tome como fórmulas. |

Cada libro del listado es `{id, titulo, autor, genero, precio, resumen, portada}`; el detalle agrega `descripcion` y `autor_bio`. `portada` es la URL de la imagen, o vacío si el libro no tiene (el front muestra su imagen por defecto). Una búsqueda sin resultados responde 200 con `total: 0` y `paginas: 1`.

### Reservas

Las validaciones son las mismas que las del formulario del front. Las claves de `errores` son `nombre`, `email`, `telefono`, `items.N.libro`, `items.N.cantidad` (N = posición de la fila, desde 0) e `items` (error general).

| Campo | Regla |
|---|---|
| `nombre` | Obligatorio, hasta 100 caracteres, sin caracteres de control. |
| `email` | Obligatorio y válido. |
| `telefono` | Opcional. Si viene: entre 6 y 20 caracteres entre números, espacios, `+`, `-`, `(` y `)`. |
| `items` | De 1 a 10 filas. Cada `libro` tiene que existir en el catálogo y cada `cantidad` es un entero de 1 a 10. |

La respuesta 201 es `{reserva: {nombre, email, telefono, items: [{libro, titulo, cantidad, precio}], total}}`. Si falla el envío del correo responde 503 y el detalle queda en el log.

### Cuentas

- La contraseña tiene de 8 a 72 caracteres, con al menos una letra y un número. Se guarda con `password_hash`.
- El registro siempre crea usuarios con rol `usuario`. El login con datos incorrectos responde 422 con el mismo mensaje exista o no el correo.
- **Sesión de PHP en la base:** el login hace `session_regenerate_id(true)` (id nuevo y se borra el viejo: evita fijación de sesión) y guarda en `$_SESSION` el id del usuario y un token CSRF al azar (`random_bytes`). `session.use_strict_mode` rechaza ids inventados por el cliente.
- La sesión dura `SESION_TTL` segundos (`expira_en` es esa cantidad).
- **Salir** (`POST /api/logout`) hace los tres pasos: `$_SESSION = []`, cookie vencida y `session_destroy()`, que borra la fila de `sesiones`. El id deja de servir en el servidor aunque alguien lo hubiera copiado.
- **CSRF:** como el navegador manda la cookie solo, los POST con sesión (`/api/admin/*`) exigen el header `X-CSRF-Token` con el token que devolvió el login (o `GET /api/me`). Se compara con `hash_equals`. Primera barrera: `SameSite=Strict`.
- Si el usuario ya no existe o la sesión venció, los endpoints con sesión responden 401.

### Portadas

El alta de libros del front permite arrastrar la tapa. El front la achica y la pasa a WebP antes de mandarla, y la sube con `POST /api/admin/portadas`. La URL que devuelve va como `imagen_url` del libro.

- **Cuerpo:** la imagen tal cual, sin multipart ni JSON. El tipo va en `Content-Type`.
- **Validación (`PortadaValidator`):** hasta 1 MB, y solo JPG, PNG o WebP. No alcanza con el `Content-Type`: con `getimagesizefromstring` se lee la cabecera del archivo y tiene que coincidir. SVG no se acepta porque puede traer scripts.
- **Dónde se guardan:** en la tabla `portadas` (columna `BYTEA`), no en disco, porque en Render el disco se borra en cada despliegue.
- **Respuesta de `GET /api/portadas/{id}`:** sale con su tipo, `X-Content-Type-Options: nosniff` y `Cache-Control: immutable` de un año, así cada navegador la pide una sola vez. Una portada nunca cambia: una nueva tiene otro id.
- Si en algún momento hicieran falta muchas imágenes, el paso siguiente es un almacenamiento de archivos con CDN (S3, Cloudinary). Solo cambiaría `PortadaRepository`.

## Base de datos (Neon)

1. En la consola de Neon abrir el **SQL Editor**, pegar `database/schema.sql` y ejecutarlo; después `database/seed.sql` (los 20 libros). `schema.sql` se puede volver a correr sin perder datos: así se agregan las tablas `portadas` (entrega 4) y `sesiones` (sesión de PHP) a una base que ya existía.
2. Botón **Connect** → desactivar **Connection pooling** → copiar host, base, usuario y contraseña al `.env` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
3. En local hace falta la extensión `pdo_pgsql` de PHP (en XAMPP: descomentar `extension=pdo_pgsql` y `extension=pgsql` en `php.ini`). Con un Postgres local sin SSL, agregar `DB_SSLMODE=disable`.

### Crear el primer administrador

Registrarse con `POST /api/registro` (o desde la pantalla de registro del front) y ejecutar en el SQL Editor de Neon:

```sql
UPDATE users SET rol = 'admin' WHERE email = 'tu@email.com';
```

Después, al ingresar de nuevo, el front muestra **Cargar libro**.

## Despliegue en Render

El `Dockerfile` instala PHP 8.3 con Apache, `pdo_pgsql` y las dependencias de Composer, con `public/` como document root. En *Environment* cargar:

`APP_ENV=production`, `APP_DEBUG=false`, `FRONT_URL` (donde está publicado el front), `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE=require` y, para que lleguen las reservas, `MAIL_API_KEY`, `MAIL_TO` y `MAIL_FROM`.

## Correo de reservas (Brevo)

Cada reserva le llega por correo al personal. El envío usa la **API HTTP de Brevo** (HTTPS, puerto 443) y no SMTP: desde septiembre de 2025 Render bloquea los puertos SMTP (25, 465 y 587) en el plan gratuito. Como el pedido HTTP se hace con las funciones nativas de PHP, el proyecto ya no necesita PHPMailer.

- **Mail del proyecto:** `pawprints2026mercedesgrupokbmp@gmail.com`. Es la cuenta de Brevo, el remitente verificado (`MAIL_FROM`) y quien recibe los avisos (`MAIL_TO`).
- **Plan:** el gratuito de Brevo (300 correos por día).
- **API key:** en Brevo, *SMTP & API → API Keys*. Va en el `.env` local y en *Environment* de Render como `MAIL_API_KEY`; nunca en el repo.
- El correo trae como *Responder a* el mail del cliente, así el personal le contesta directo.
- Si la API rechaza el envío o no responde en 10 segundos, la reserva responde 503 (`correo_no_enviado`) y el detalle queda en el log.
- Como el remitente es una casilla `@gmail.com` enviada desde Brevo, algunos correos pueden caer en **spam**: la primera vez, marcarlos como "No es spam". Para evitarlo del todo haría falta un dominio propio verificado en Brevo.

Como *Health Check Path* se puede usar `/api/health`. En el plan gratuito el servicio se duerme tras un rato sin pedidos y el primero tarda en responder; el front avisa que el servidor no respondió y ofrece reintentar.

## Pruebas

Después de `composer install`:

```
php tests/catalogo.php   # filtrado y orden del catálogo
php tests/reserva.php    # validaciones de la reserva y aviso por correo (con un HTTP falso)
php tests/libro.php      # validaciones del alta de libros
php tests/portada.php    # validación de las portadas subidas
php tests/cors.php       # orígenes permitidos
php tests/api.php        # la API completa, sin servidor, base ni correos reales
```

## Versiones

Usamos versionado semántico en el archivo `VERSION` y un tag de git por entrega: `tp1` es la 0.1.0, `tp2` la 0.2.0, `Entrega_3` la 0.3.0 y `Entrega_4` la 0.4.0.
