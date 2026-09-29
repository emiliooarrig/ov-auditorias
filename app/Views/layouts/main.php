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
        ? ['/' => 'Panel central']
        : ['/mis-talleres' => 'Mis talleres'];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($titulo) ? $titulo . ' · ' . $nombreApp : $nombreApp) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    <header class="encabezado">
        <div class="encabezado__interior">
            <a class="marca" href="<?= e(url('/')) ?>">
                <span class="marca__universidad">Universidad Anáhuac</span>
                <span class="marca__app"><?= e($nombreApp) ?></span>
            </a>
            <?php if ($usuario !== null) : ?>
                <nav aria-label="Principal">
                    <ul class="menu">
                        <?php foreach ($enlaces as $ruta => $etiqueta) : ?>
                            <li>
                                <?php $actual = $rutaActual === $ruta ? ' aria-current="page"' : ''; ?>
                                <a href="<?= e(url($ruta)) ?>"<?= $actual ?>>
                                    <?= e($etiqueta) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li class="menu__usuario" title="<?= e($usuario['correo']) ?>">
                            <?= e($usuario['nombre']) ?>
                        </li>
                        <li>
                            <form method="post" action="<?= e(url('/logout')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit">Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </header>

    <main id="contenido" class="contenedor">
        <?php foreach ($mensajes as $m) : ?>
            <div class="aviso aviso--<?= e($m['tipo']) ?>" role="status"><?= e($m['mensaje']) ?></div>
        <?php endforeach; ?>

        <?= $contenido /* ya renderizado y escapado por la vista */ ?>
    </main>

    <footer class="pie">
        <div class="contenedor">Registro de auditores de talleres · Universidad Anáhuac</div>
    </footer>
</body>
</html>
