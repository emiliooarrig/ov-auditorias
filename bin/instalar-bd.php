<?php

declare(strict_types=1);

/*
 * Crea la base de datos configurada en .env con database/schema.sql (estructura) y database/datos.sql
 * (catálogos, administrador, alumnos, talleres y asignaciones).
 *
 *   php bin/instalar-bd.php                 crea la base (falla si ya existe)
 *   php bin/instalar-bd.php --reiniciar     BORRA la base existente y la vuelve a crear (pide confirmación)
 *   php bin/instalar-bd.php --bd=otra_base  usa otro nombre de base
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
$opciones = getopt('', ['reiniciar', 'bd:', 'si']);

/** @var array<string, mixed> $db */
$db = (array) $app->config('db', []);
$nombre = isset($opciones['bd']) && is_string($opciones['bd']) ? $opciones['bd'] : (string) $db['name'];
$reiniciar = isset($opciones['reiniciar']);
$instalador = new SchemaInstaller($db, $app->basePath('database'));

try {
    if ($reiniciar && $instalador->databaseExists($nombre)) {
        $consola->line("Se BORRARÁ la base '{$nombre}' con todos sus datos.");
        $confirmado = isset($opciones['si'])
            || ($consola->interactive() && $consola->ask('Escribe el nombre de la base para confirmar') === $nombre);
        if (!$confirmado) {
            $consola->line('Cancelado. No se modificó nada.');
            exit(1);
        }
    }

    $instalador->install($nombre, $reiniciar);
    $consola->line("Base '{$nombre}' instalada.");
    // datos.sql trae a los administradores sin contraseña: nunca se guarda en un archivo.
    $consola->line('Siguiente paso: php bin/crear-admin.php para asignar la contraseña del administrador.');
} catch (Throwable $e) {
    $consola->error($e->getMessage());
    exit(1);
}
