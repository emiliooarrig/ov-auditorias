<?php

/**
 * Layout principal.
 *
 * @var string      $contenido
 * @var string|null $titulo
 */

$nombreApp = (string) app()->config('name');
$mensajes = app()->session()->pullFlash();
$usuario = app()->auth()->user();
$rutaActual = app()->request()?->path ?? '';

$enlaces = [];
if ($usuario !== null) {
    $enlaces = app()->auth()->esAdministrador()
        ? [
            '/' => ['Panel central', 'panel'],
            '/asignaciones' => ['Asignaciones', 'asignar'],
            '/usuarios' => ['Usuarios', 'usuarios'],
        ]
        : ['/mis-talleres' => ['Mis talleres', 'mis-talleres']];
}
$iconoAviso = ['exito' => 'check-circulo', 'error' => 'x-circulo', 'aviso' => 'alerta'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($titulo) ? $titulo . ' · ' . $nombreApp : $nombreApp) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
    <?= app()->view()->partial('partials/iconos') ?>
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    <header class="encabezado">
        <div class="encabezado__interior">
            <a class="marca" href="<?= e(url('/')) ?>">
                <span class="marca__universidad">Universidad Anáhuac</span>
                <span class="marca__app"><?= e($nombreApp) ?></span>
            </a>
            <?php if ($usuario !== null) : ?>
                <div class="navegacion">
                    <nav aria-label="Principal">
                        <ul class="menu">
                            <?php foreach ($enlaces as $ruta => [$etiqueta, $iconoEnlace]) : ?>
                                <li>
                                    <?php $actual = $rutaActual === $ruta ? ' aria-current="page"' : ''; ?>
                                    <a href="<?= e(url($ruta)) ?>"<?= $actual ?>>
                                        <?= icono($iconoEnlace) ?> <?= e($etiqueta) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                    <div class="sesion">
                        <span class="sesion__usuario" title="<?= e($usuario['correo']) ?>">
                            <span class="sesion__nombre">
                                <?= icono(app()->auth()->esAdministrador() ? 'escudo' : 'usuario') ?>
                                <?= e($usuario['nombre']) ?>
                            </span>
                            <span class="sesion__rol">
                                <?= app()->auth()->esAdministrador() ? 'Administrador' : 'Auditor' ?>
                            </span>
                        </span>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit"><?= icono('salir') ?> Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main id="contenido" class="contenedor">
        <?php foreach ($mensajes as $m) : ?>
            <div class="aviso aviso--<?= e($m['tipo']) ?>" role="status">
                <?= icono($iconoAviso[$m['tipo']] ?? 'info') ?>
                <span><?= e($m['mensaje']) ?></span>
            </div>
        <?php endforeach; ?>

        <?= $contenido /* ya renderizado y escapado por la vista */ ?>
    </main>

    <footer class="pie">
        <div class="contenedor">Registro de auditores de talleres de la Universidad Anáhuac</div>
    </footer>
</body>
</html>
