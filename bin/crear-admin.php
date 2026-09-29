<?php

declare(strict_types=1);

/*
 * Crea un administrador, o convierte en administrador a un usuario existente,
 * pidiendo su contraseña. Solo se guarda el hash; la contraseña nunca queda en archivos.
 *
 *   php bin/crear-admin.php
 *   php bin/crear-admin.php --correo=nombre.apellido@anahuac.mx --nombre="Nombre" --apellidos="Apellidos"
 *
 * También sirve para restablecer la contraseña de un administrador.
 */

use App\Core\App;
use App\Core\Console;
use App\Core\Password;
use App\Models\Usuario;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::boot(dirname(__DIR__));
$consola = new Console();
$opciones = getopt('', ['correo:', 'nombre:', 'apellidos:']);
$opcion = static fn (string $clave): string => isset($opciones[$clave]) && is_string($opciones[$clave])
    ? $opciones[$clave]
    : '';

/** @var list<string> $dominios */
$dominios = (array) $app->config('auth.allowed_domains', []);

try {
    $usuarios = new Usuario($app->db());

    $correo = Usuario::normalizarCorreo($opcion('correo') !== '' ? $opcion('correo') : $consola->ask('Correo'));
    if (!Usuario::correoPermitido($correo, $dominios)) {
        throw new RuntimeException('El correo debe ser del dominio @' . implode(' o @', $dominios) . '.');
    }

    $existente = $usuarios->buscarPorCorreo($correo);
    if ($existente !== null) {
        $consola->line(sprintf(
            'Ya existe: %s %s (%s, %s).',
            $existente['nombre'],
            $existente['apellidos'],
            $existente['rol'],
            (int) $existente['activo'] === 1 ? 'activo' : 'desactivado'
        ));
        $accion = $existente['rol'] === Usuario::ADMINISTRADOR
            ? '¿Cambiar su contraseña?'
            : '¿Convertirlo en administrador y asignarle contraseña?';
        if (!$consola->confirm($accion)) {
            $consola->line('Cancelado. No se modificó nada.');
            exit(1);
        }
    } else {
        $nombre = Usuario::normalizarNombre($opcion('nombre') !== '' ? $opcion('nombre') : $consola->ask('Nombre(s)'));
        $apellidos = Usuario::normalizarNombre(
            $opcion('apellidos') !== '' ? $opcion('apellidos') : $consola->ask('Apellidos')
        );
        foreach (['Nombre' => [$nombre, 80], 'Apellidos' => [$apellidos, 120]] as $campo => [$valor, $maximo]) {
            $error = Usuario::validarNombre($valor, $maximo);
            if ($error !== null) {
                throw new RuntimeException("{$campo}: {$error}");
            }
        }
    }

    $password = $consola->askHidden('Contraseña (mínimo ' . Password::MIN_LENGTH . ' caracteres)');
    $confirmacion = $consola->askHidden('Repite la contraseña');
    $error = Password::validate($password, $confirmacion);
    if ($error !== null) {
        throw new RuntimeException($error);
    }
    $hash = Password::hash($password);

    if ($existente !== null) {
        $usuarios->convertirEnAdministrador($existente['id'], $hash);
        $consola->line("Listo: {$correo} es administrador con la nueva contraseña.");
    } else {
        $usuarios->crear(Usuario::ADMINISTRADOR, $nombre, $apellidos, $correo, $hash);
        $consola->line("Listo: administrador {$correo} creado.");
    }
} catch (Throwable $e) {
    $consola->error($e->getMessage());
    exit(1);
}
