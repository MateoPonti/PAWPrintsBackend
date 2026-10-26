<?php

// Front controller: todas las peticiones entran por acá (ver .htaccess).

use Paw\Core\App;
use Paw\Core\Request;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = new App($root, Request::fromGlobals());
$app->handle()->send();
