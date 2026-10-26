# PAWPrints - back-end (API)

API JSON de la librería PAWPrints. Trabajos prácticos de Programación en Ambiente Web (11086), Comisión 50, UNLu.

Integrantes: Federico Kasparian, Justino Bernal, Lautaro Marino y Ponti Mateo. Los legajos están en `autores.txt` y la versión en `VERSION`.

Este proyecto es **solo la API**: no genera HTML. El sitio (HTML, CSS y JavaScript) está en el proyecto `Frontend`, que pide los datos a esta API con `fetch`. Hecha en PHP sin framework, con un único punto de entrada.

## Cómo ejecutar

Se necesita PHP 8.1 o superior (con `pdo_pgsql` si se usa la base) y Composer.

```
git clone https://github.com/MateoPonti/PAWPrintsBackend.git
cd PAWPrintsBackend
composer install
cp .env.example .env          # y completar JWT_SECRET (ver abajo)
php -S localhost:8000 -t public public/index.php
```

Probar: `http://localhost:8000/api/libros`. Para usarla con el sitio, levantar el front (`python3 -m http.server 5500` en su carpeta) y que el front apunte a `http://localhost:8000` (ver el README del front). Con `APP_ENV=development` se acepta cualquier puerto de localhost; si no, el origen del front tiene que estar en `FRONT_URL`.

En un servidor Apache el document root tiene que ser la carpeta `public/`.

## Variables de entorno (`.env`)

`.env` no se sube al repo; `.env.example` es la plantilla. En Render las mismas variables se cargan en *Environment*.

| Variable | Uso |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `development` / `true` agrega el campo `detalle` a los errores 500. En producción: `production` / `false`. |
| `FRONT_URL` | Orígenes desde los que el navegador puede usar la API (CORS): la dirección donde se publica el front, sin barra final. Varios, separados por coma. **Si falta el del front publicado, el navegador bloquea todos los pedidos.** |
| `JWT_SECRET` | Clave con la que se firman los tokens. **Obligatoria para ingresar**, mínimo 32 caracteres. Generar una distinta para producción: `php -r "echo bin2hex(random_bytes(32));"`. |
| `JWT_TTL` | Segundos de validez del token (por defecto 28800, 8 horas). |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE` | Base PostgreSQL (Neon). Con `DB_HOST` vacío el catálogo sale de `data/libros.json`, sin cuentas ni alta de libros. |
| `MAIL_TO`, `MAIL_FROM`, `MAIL_FROM_NAME`, `SMTP_*` | Aviso por correo de cada reserva. Con `SMTP_HOST` vacío no se envía nada y el correo queda en `logs/app.log`. |
| `LOG_LEVEL` | Nivel mínimo del log. |

## Arquitectura

```
public/
  index.php        Front controller: toda petición entra por acá
  .htaccess        Redirige todo a index.php y le pasa a PHP el encabezado Authorization (Apache)
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
| Petición HTTP | `Core/Request`: método, path, query string, cuerpo JSON, cabeceras y token Bearer. Los controladores no leen `$_GET` ni `$_SERVER`. |
| Mapeo de URLs | `Core/Router` y `config/routes.php`. Parámetros (`/api/libros/{id}`), 404 y 405 con `Allow`. |
| Respuesta | `Core/Response::json()`. Todas las respuestas, también los errores, son JSON. |
| Errores | `Core/ErrorHandler` + `Core/HttpException`: todo error se registra en el log y sale como `{"error", "mensaje", "errores"}`. En producción el 500 es un mensaje genérico. |
| Autenticación | `Core/Auth` + `Core/Jwt`: token JWT (HS256) en `Authorization: Bearer <token>`. Sin sesión ni cookies, así que no hace falta CSRF. El rol se consulta en la base en cada pedido. |
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
| `POST /api/login` | público | `email`, `password` | `{token, tipo, expira_en, usuario}` o 422 con `mensaje` |
| `GET /api/me` | token | - | `{usuario}` o 401 |
| `POST /api/admin/libros` | admin | `titulo`, `autor`, `genero`, `precio`, `resumen`, `descripcion`, `autor_bio`, `imagen_url` | `201 {libro}`, 401, 403 o 422 con errores |

Códigos: 400 cuerpo inválido, 401 sin token o token vencido, 403 sin permisos, 404 no existe, 405 método no permitido, 422 datos inválidos, 503 función no disponible (sin base de datos, base caída, falló el correo o falta `JWT_SECRET`).

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
- El token dura `JWT_TTL` segundos (`expira_en` es esa cantidad). Cerrar sesión es descartarlo en el front.
- Si el usuario ya no existe, o el token venció o fue alterado, los endpoints con token responden 401.

## Base de datos (Neon)

1. En la consola de Neon abrir el **SQL Editor**, pegar `database/schema.sql` y ejecutarlo; después `database/seed.sql` (los 20 libros).
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

`APP_ENV=production`, `APP_DEBUG=false`, `FRONT_URL` (donde está publicado el front), `JWT_SECRET` (distinto al de local), `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE=require` y, para que lleguen las reservas, `MAIL_TO` y las variables `SMTP_*`.

Como *Health Check Path* se puede usar `/api/health`. En el plan gratuito el servicio se duerme tras un rato sin pedidos y el primero tarda en responder; el front avisa que el servidor no respondió y ofrece reintentar.

## Pruebas

Después de `composer install`:

```
php tests/catalogo.php   # filtrado y orden del catálogo
php tests/reserva.php    # validaciones de la reserva
php tests/libro.php      # validaciones del alta de libros
php tests/jwt.php        # token de acceso
php tests/cors.php       # orígenes permitidos
php tests/api.php        # la API completa, sin servidor, base ni SMTP
```

## Versiones

Usamos versionado semántico en el archivo `VERSION` y un tag de git por entrega: `tp1` es la 0.1.0 y `tp2` la 0.2.0.
