<?php

declare(strict_types=1);

/*
 * Front controller: único punto de entrada de la aplicación.
 */

// Con el servidor integrado de PHP, los archivos estáticos se sirven directamente.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Request;

$app = App::boot(dirname(__DIR__));
$app->handle(Request::fromGlobals($app->urlPath()))->send();
