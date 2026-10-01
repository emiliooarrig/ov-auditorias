<?php

declare(strict_types=1);

/*
 * Aplica a la base configurada en .env los cambios de database/migraciones (en orden de nombre).
 * Las migraciones son idempotentes: ejecutarlo de nuevo no cambia nada. Las bases nuevas no lo
 * necesitan, porque schema.sql y seeds.sql ya incluyen esos cambios.
 *
 *   php bin/migrar-bd.php
 *   php bin/migrar-bd.php --bd=otra_base
 */

use App\Core\App;
use App\Core\Console;
use App\Core\SchemaInstaller;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::boot(dirname(__DIR__));
$consola = new Console();
$opciones = getopt('', ['bd:']);

/** @var array<string, mixed> $db */
$db = (array) $app->config('db', []);
$nombre = isset($opciones['bd']) && is_string($opciones['bd']) ? $opciones['bd'] : (string) $db['name'];

try {
    $aplicadas = (new SchemaInstaller($db, $app->basePath('database')))->migrate($nombre);
} catch (Throwable $e) {
    $consola->line('No se pudo migrar: ' . $e->getMessage());
    exit(1);
}

$consola->line($aplicadas === []
    ? "No hay migraciones en database/migraciones. La base '{$nombre}' quedó igual."
    : "Base '{$nombre}' al día. Migraciones aplicadas: " . implode(', ', $aplicadas));
