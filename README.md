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
`php tests/catalogo.php`, después de instalar las dependencias.

Para ver la versión de celular alcanza con achicar la ventana o usar la vista adaptable del navegador (F12). Para ver la de impresión, la vista previa de impresión (Ctrl+P).

Se necesita internet para que cargue Montserrat. Sin conexión se ve con la fuente sans-serif del sistema.

## Versiones

Usamos versionado semántico en el archivo `VERSION` y un tag de git por entrega: `tp1` es la 0.1.0 y `tp2` la 0.2.0.
