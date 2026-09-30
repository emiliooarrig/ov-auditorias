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
    <link rel="stylesheet" href="<?= e(asset('vendor/sweetalert2/sweetalert2.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/clay.css')) ?>">
    <script src="<?= e(asset('vendor/sweetalert2/sweetalert2.min.js')) ?>" defer></script>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
    <?= app()->view()->partial('partials/iconos') ?>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <header class="site-header">
        <div class="site-header__slab">
            <a class="brand" href="<?= e(url('/')) ?>">
                <span class="brand__org">Universidad Anáhuac</span>
                <span class="brand__app"><?= e($nombreApp) ?></span>
            </a>
            <?php if ($usuario !== null) : ?>
                <div class="site-header__nav">
                    <nav aria-label="Principal">
                        <ul class="nav__list">
                            <?php foreach ($enlaces as $ruta => [$etiqueta, $iconoEnlace]) : ?>
                                <li>
                                    <?php $actual = $rutaActual === $ruta ? ' aria-current="page"' : ''; ?>
                                    <a class="nav__link" href="<?= e(url($ruta)) ?>"<?= $actual ?>>
                                        <?= icono($iconoEnlace) ?> <?= e($etiqueta) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                    <div class="session">
                        <span class="session__user" title="<?= e($usuario['correo']) ?>">
                            <span class="session__name">
                                <?= icono(app()->auth()->esAdministrador() ? 'escudo' : 'usuario') ?>
                                <?= e($usuario['nombre']) ?>
                            </span>
                            <span class="session__role">
                                <?= app()->auth()->esAdministrador() ? 'Administrador' : 'Auditor' ?>
                            </span>
                        </span>
                        <form method="post" action="<?= e(url('/logout')) ?>"
                              data-confirmar="¿Cerrar sesión?"
                              data-confirmar-texto="Tendrás que ingresar de nuevo con tu correo institucional."
                              data-confirmar-boton="Cerrar sesión">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--small">
                                <?= icono('salir') ?> Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main id="contenido" class="container">
        <?php foreach ($mensajes as $m) : ?>
            <?php /* Con alerta, app.js lo muestra con SweetAlert; sin JavaScript se queda como aviso. */ ?>
            <?php $alerta = ($m['alerta'] ?? false) ? ' data-alerta' : ''; ?>
            <div class="alert alert--<?= e($m['tipo']) ?>" role="status"<?= $alerta ?>>
                <?= icono($iconoAviso[$m['tipo']] ?? 'info') ?>
                <span><?= e($m['mensaje']) ?></span>
            </div>
        <?php endforeach; ?>

        <?= $contenido /* ya renderizado y escapado por la vista */ ?>
    </main>

    <footer class="site-footer">
        <div class="container">Registro de auditores de talleres de la Universidad Anáhuac</div>
    </footer>
</body>
</html>
