# PAWPrints

Sitio web de la librería PAWPrints. Trabajos prácticos de Programación en Ambiente Web (11086), Comisión 50, UNLu.

Integrantes: Federico Kasparian, Justino Bernal , Lautaro Marino y Ponti Mateo. Los legajos están en `autores.txt` y la versión en `VERSION`.

## Enunciado

Es un mismo proyecto que se va armando TP a TP.

En el TP1 había que maquetar el sitio de una librería con tienda online y tienda física usando solo HTML5, sin CSS ni JavaScript. También se pedía el sitemap, los wireframes y un formulario de reserva de libros.

En el TP2 había que agregar el CSS para que el maquetado se vea como los wireframes, siguiendo el manual de identidad de la empresa, y que funcione en celular, escritorio e impresión.

En el TP3 había que servir el sitio con código dinámico siguiendo el patrón MVC: página de inicio con el menú, "Acerca de nosotros", catálogo con búsqueda, paginación y descarga en CSV, procesamiento del formulario de reserva con envío de correo, manejo de errores y publicación en Internet.

## Arquitectura

La aplicación está hecha en PHP sin framework, siguiendo MVC y con un único punto de entrada.

```
public/            Lo único accesible desde la web
  index.php        Front controller: toda petición entra por acá
  .htaccess        Redirige a index.php todo lo que no sea un archivo (Apache)
  styles/ img/     Archivos estáticos
config/
  config.php       Configuración, toma los valores del .env
  routes.php       Mapa de URLs a controladores
src/
  Core/            Infraestructura común (no sabe nada de libros ni reservas)
  Controllers/     Controladores (C)
  Models/          Modelos y acceso a datos (M)
views/             Plantillas PHP (V), todas dentro de layout.php
data/libros.json   "Base de datos" de libros hasta el próximo TP
logs/              Registro de la aplicación (no se sube al repo)
```

Cómo se resuelve cada problemática:

| Problemática | Dónde | PSR de referencia |
|---|---|---|
| Análisis de la petición HTTP | `Core/Request`: método, path, query string, cuerpo del POST y cabeceras. Los controladores no leen `$_GET`, `$_POST` ni `$_SERVER`. | PSR-7 (simplificado) |
| Mapeo de URLs | `Core/Router` y `config/routes.php`. Soporta parámetros (`/catalogo/{id}`). Si la URL no existe responde 404 y si el método no corresponde, 405 con la cabecera `Allow`. | — |
| Generación de la respuesta | `Core/Response`: código de estado, cabeceras y cuerpo. El controlador la devuelve y `index.php` la envía. | PSR-7 (simplificado) |
| Generación de registros | `Core/FileLogger`, implementa `Psr\Log\LoggerInterface` y escribe en `logs/app.log`. El nivel mínimo se configura con `LOG_LEVEL`. | PSR-3 |
| Persistencia | `Models/LibroRepository`: los controladores le piden los libros y no saben de dónde salen. Hoy lee un JSON; en el próximo TP se cambia por una base de datos sin tocar el resto. | — |
| Configuración | `Core/Config` + `config/config.php`. Lo que cambia entre ambientes o es secreto (debug, mail, SMTP) va en `.env`, que no se sube; `.env.example` es la plantilla. | — |
| Representaciones de la información | `Core/View` renderiza HTML con las plantillas de `views/`. Otros formatos (como el CSV del catálogo) se arman como una `Response` con su `Content-Type`. | — |
| Errores | `Core/ErrorHandler` atrapa todo error y excepción, lo registra en el log y muestra una página de error. Con `APP_DEBUG=false` (producción) el usuario solo ve un mensaje genérico; el detalle queda en el log. | — |
| Autoload | Composer, con el namespace `Paw\` apuntando a `src/`. | PSR-4 |
| Estilo de código | — | PSR-12 |

Para agregar una página: se crea el método en un controlador, la vista en `views/` y la ruta en `config/routes.php`.

### URLs

| Método | URL | Qué hace |
|---|---|---|
| GET | `/` | Inicio |
| GET | `/nosotros` | Nuestra historia |
| GET | `/contacto` | Contacto |
| GET | `/catalogo` | Listado del catálogo |
| GET | `/catalogo/{id}` | Detalle de un libro (404 si no existe) |
| GET | `/reserva` | Formulario de reserva (`?libro={id}` lo deja preseleccionado) |
| POST | `/reserva` | Procesa la reserva: 422 con errores o 303 a la confirmación |
| GET | `/reserva/confirmada` | Confirmación de la reserva |

#### Consulta del catálogo

`/catalogo` recibe los filtros y la paginación por **GET**, en la query string
(pares `clave=valor` codificados en la URL). Es una consulta idempotente que no
modifica datos; la URL se puede guardar, compartir y recorrer con el historial del navegador.

| Parámetro | Valores y comportamiento |
|---|---|
| `genero` | Coincidencia exacta: `novela`, `cuento`, `poesia` o `infantil`. Vacío: todos. |
| `autor` | Parte del nombre, sin distinguir mayúsculas ni acentos. Vacío: todos. |
| `precio_max` | Precio máximo inclusivo, entero mayor o igual a 0. Vacío o inválido: sin límite. |
| `orden` | `relevancia` (orden de carga del JSON), `precio-asc`, `precio-desc` o `titulo` (A-Z, sin distinguir mayúsculas ni acentos). Por defecto o inválido: `relevancia`. |
| `page` | Entero mayor o igual a 1. Por defecto: 1. |
| `per_page` | 6, 12 o 24 libros por página. Ausente o inválido: `catalogo.per_page` de `config/config.php` (actualmente 6). |

Ejemplo: `/catalogo?genero=novela&autor=garcia&precio_max=15000&orden=precio-asc&per_page=6&page=1`.
Los filtros se combinan, el formulario conserva la selección y los enlaces de
paginación mantienen los filtros y la cantidad por página. Al aplicar el formulario
se vuelve a la primera página.

Una página inválida (por ejemplo `page=abc`, `page=0` o `page=999`) responde **404**
con «La página del catálogo no existe». Una búsqueda sin resultados responde **200**
en la página 1, con «No encontramos libros con esos filtros» y «Mostrando 0-0 de 0 libros»;
intentar otra página de esa búsqueda responde 404. Un libro inexistente en
`/catalogo/{id}` sigue respondiendo 404.

`LibroRepository::search(array $filtros): array` devuelve todos los libros filtrados
y ordenados, **sin paginar**. Comparte las claves `genero`, `autor`, `precio_max`
(entero o `null`) y `orden` con la consulta HTML para que la exportación CSV pueda
reutilizarlo. La paginación se aplica después, en `CatalogoController::index`.

#### Formulario de reserva

El formulario se envía por **POST** a `/reserva`. Usamos POST porque la reserva no es
una consulta: tiene efectos (avisa al personal por correo) y no es idempotente, así que
no tiene que repetirse si alguien recarga o vuelve a abrir el link. Además, los datos
personales (nombre, correo y teléfono) no quedan en la URL, el historial ni los logs del servidor.

Los datos viajan en el cuerpo con la codificación por defecto de los formularios,
`application/x-www-form-urlencoded` (pares `clave=valor` separados por `&`, con los
caracteres especiales codificados con `%`). Como se pueden pedir varios libros,
los campos se llaman `libro[]` y `cantidad[]` y PHP los recibe como arrays: la
posición `i` de los dos arrays es una fila de la reserva. Ejemplo de cuerpo:

```
nombre=Ana+P%C3%A9rez&email=ana%40mail.com&telefono=011+4444-5555&libro%5B%5D=rayuela&cantidad%5B%5D=2
```

Las validaciones del backend (`Models/ReservaValidator`) son las mismas que las del
HTML. Los límites del HTML (`maxlength`, `pattern`, `min`, `max`) salen de las
constantes de esa clase, así que si se cambia una regla cambia en los dos lados.

| Campo | Regla (HTML y backend) |
|---|---|
| `nombre` | Obligatorio, hasta 100 caracteres (`required maxlength="100"`). El backend recorta los espacios de los extremos y rechaza saltos de línea, que un input de texto no puede enviar. |
| `email` | Obligatorio y válido (`type="email"` + `pattern` que exige dominio con punto; en el backend, `FILTER_VALIDATE_EMAIL`). |
| `telefono` | Opcional. Si viene: entre 6 y 20 caracteres entre números, espacios, `+`, `-`, `(` y `)` (`pattern="[0-9+ \(\)\-]{6,20}"`). |
| `libro[]` | Al menos uno, y cada uno tiene que existir en el catálogo (`LibroRepository::find`). |
| `cantidad[]` | Entero entre 1 y 10 (`min="1" max="10" step="1"`). |

El `pattern` del teléfono tiene `(`, `)` y `-` escapados porque los navegadores
compilan el atributo con el flag `v` de JavaScript, que no los acepta sin escapar
dentro de `[ ]`. Con la versión anterior (`[0-9+ ()-]{6,20}`) el navegador
descartaba el pattern y no validaba el teléfono.

Respuestas:

- **Con errores:** 422 (Unprocessable Content). Se vuelve a mostrar el formulario
  con los datos cargados y el mensaje de cada error al lado del campo.
- **Sin errores:** se registra la reserva en el log, se manda el correo y se
  responde **303 See Other** a `/reserva/confirmada` (patrón Post/Redirect/Get).
  Así, si se recarga la página de confirmación, el navegador hace un GET y no
  reenvía el formulario.
- **Si falla el envío del correo:** 503. El error queda en el log y al usuario se
  le muestra un mensaje genérico, con el formulario completo para que pueda reintentar.

El correo se envía con PHPMailer por SMTP a la dirección configurada. Se configura en el `.env`:

| Variable | Uso |
|---|---|
| `MAIL_TO` | Dirección del personal que recibe las reservas |
| `MAIL_FROM` / `MAIL_FROM_NAME` | Remitente del correo. El `Reply-To` es el correo del cliente, para responderle directamente. |
| `SMTP_HOST` | Servidor SMTP. **Vacío (desarrollo):** no se envía nada y el correo completo queda en `logs/app.log`. |
| `SMTP_PORT` | Puerto, normalmente 587 con `tls` o 465 con `ssl` |
| `SMTP_USER` / `SMTP_PASS` | Credenciales. Con Gmail hay que usar una contraseña de aplicación. |
| `SMTP_SECURE` | `tls` (STARTTLS), `ssl` (SMTPS) o vacío (sin cifrado, solo para pruebas locales) |

## Base de datos, cuentas de usuario y alta de libros

El catálogo puede leer los libros de una base **PostgreSQL (Neon)** en lugar de `data/libros.json`. Si `DB_HOST` está vacío en el `.env`, el sitio funciona exactamente como antes (catálogo desde el JSON, sin cuentas ni alta de libros), así que cada integrante puede seguir trabajando sin base.

| Qué | Dónde |
|---|---|
| Conexión PDO a Neon | `Core/Database` (la arma `App::pdo()`) |
| Libros desde la base | `Models/LibroDbRepository`, que extiende `LibroRepository`: solo cambia de dónde salen los libros, el filtrado, orden y paginación son los mismos. `App::libros()` elige cuál usar. |
| Sesión y token anti-CSRF | `Core/Auth` (cookie `HttpOnly`, `SameSite=Lax`, `Secure` con HTTPS). La sesión solo se abre si el visitante ya tiene cookie. |
| Usuarios | `Models/Usuario`, `Models/UsuarioRepository`, `Controllers/AuthController`, vistas en `views/auth/` |
| Alta de libros | `Controllers/AdminLibroController`, `Models/LibroValidator`, vista `views/admin/libro_nuevo.php` |
| Tablas | `database/schema.sql` (`users`, `libros`) y `database/seed.sql` (los 20 libros del JSON) |

URLs nuevas:

| Método | URL | Qué hace |
|---|---|---|
| GET / POST | `/registro` | Crea una cuenta (siempre con rol `usuario`) e inicia sesión. 422 con errores, 303 al inicio. |
| GET / POST | `/login` | Inicia sesión. Con datos incorrectos responde 422 sin revelar si el correo existe. |
| POST | `/logout` | Cierra la sesión (303 al inicio). |
| GET | `/admin/libros/nuevo` | Formulario de alta. Sin sesión redirige a `/login`; con un usuario que no es admin, 403. |
| POST | `/admin/libros` | Guarda el libro (422 con errores, 303 al detalle del libro nuevo). |

Todos los POST de cuentas y de alta llevan un token anti-CSRF (`_csrf`); sin él responden 403. El rol se consulta en la base en cada pedido. El `id` público de un libro sigue siendo el slug (`/catalogo/rayuela`): el de los libros nuevos se genera a partir del título (`El túnel` → `el-tunel`, y `-2`, `-3`... si ya existe).

### Poner la base en marcha (Neon)

1. En la consola de Neon abrir el **SQL Editor**, pegar `database/schema.sql` y ejecutarlo; después `database/seed.sql` (los 20 libros).
2. Botón **Connect** → desactivar **Connection pooling** → copiar host, base, usuario y contraseña al `.env` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). En Render van como variables de entorno.
3. En local hace falta la extensión `pdo_pgsql` de PHP (en XAMPP: descomentar `extension=pdo_pgsql` y `extension=pgsql` en `php.ini`). Con un Postgres local sin SSL, agregar `DB_SSLMODE=disable`.

### Crear el primer administrador

El registro público siempre crea usuarios con rol `usuario`. Para tener un admin, registrarse en `/registro` y ejecutar en el SQL Editor de Neon:

```sql
UPDATE users SET rol = 'admin' WHERE email = 'tu@email.com';
```

Después aparece **Cargar libro** en el menú.

### Despliegue en Render

El `Dockerfile` instala PHP 8.3 con Apache, `pdo_pgsql` y las dependencias de Composer, con `public/` como document root. En *Environment* cargar `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE=require`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` (la URL de Render) y, para que lleguen las reservas, `MAIL_TO` y las variables `SMTP_*`.

Las pruebas del alta de libros se ejecutan con `php tests/libro.php`.

## Manual de identidad

De las dos opciones que daba la consigna elegimos el manual Violeta.

Los colores del manual son el violeta `#5c068c` y el blanco `#ffffff`. El violeta lo usamos en el nombre del sitio, los títulos, los enlaces, los botones y el pie; el blanco es el fondo y el texto que va sobre violeta.

Como el manual solo trae esos dos colores, para el hover, los fondos suaves y los bordes usamos el mismo violeta más oscuro (`#400462`) o más claro (`#f2ebf6` y `#decde8`), así no agregamos colores que no sean de la marca. Para el texto usamos un gris oscuro porque el manual no fija uno.

La tipografía del manual es Argentum Sans y la equivalente que indica es Montserrat. Cargamos Montserrat desde Google Fonts y dejamos Argentum Sans primera en la lista por si está instalada.

Todo esto está definido una sola vez como variables en `:root`, al principio de `public/styles/style.css`.

## Responsive

Lo hicimos mobile first: el CSS base es el de celular, con todo en una columna, y las media queries agregan columnas cuando hay más ancho. A partir de 700px las fichas del catálogo, las promociones y los servicios pasan a dos columnas, y a partir de 1024px a tres. Elegimos esos anchos porque cada ficha necesita unos 300px para que la portada y el texto no queden apretados.

Usamos grid para las grillas de fichas y flex para lo que va en fila y tiene que poder bajar de renglón (el menú, los filtros del catálogo y el pie). Por eso el menú no necesita botón hamburguesa. El `body` tiene un ancho máximo de 1200px para que en monitores grandes no quede todo estirado.

## Impresión

`public/styles/print.css` se enlaza con `media="print"` en todas las páginas.

Al imprimir se oculta lo que en papel no sirve: el menú, las redes del pie, los botones y los filtros del catálogo. El nombre del sitio y todo el contenido se mantienen, incluido el formulario de reserva, que se puede imprimir para completar a mano. Se imprime en negro sobre blanco y sin fondos de color para no gastar tinta.

Con `@page` definimos hoja A4 y márgenes de 2cm. Con `break-inside: avoid` evitamos que una ficha del catálogo, un grupo de campos o una tabla queden cortados entre dos hojas.

## Cómo ejecutar

Se necesita PHP 8.1 o superior y Composer.

```
git clone https://github.com/MateoPonti/PAWPrintsBackend.git
cd PAWPrintsBackend
composer install
cp .env.example .env
php -S localhost:8000 -t public public/index.php
```

Y se entra a `http://localhost:8000`. En un servidor Apache el document root tiene que ser la carpeta `public/`.

Las pruebas del filtrado y ordenamiento compartidos por HTML y CSV se ejecutan con
`php tests/catalogo.php`, y las de las validaciones de la reserva con `php tests/reserva.php`,
después de instalar las dependencias.

Para ver la versión de celular alcanza con achicar la ventana o usar la vista adaptable del navegador (F12). Para ver la de impresión, la vista previa de impresión (Ctrl+P).

Se necesita internet para que cargue Montserrat. Sin conexión se ve con la fuente sans-serif del sistema.

## Versiones

Usamos versionado semántico en el archivo `VERSION` y un tag de git por entrega: `tp1` es la 0.1.0 y `tp2` la 0.2.0.
