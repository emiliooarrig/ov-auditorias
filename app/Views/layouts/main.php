<?php
/**
 * Layout principal.
 *
 * @var string      $contenido
 * @var string|null $titulo
 */
$nombreApp = (string) app()->config('name');
$mensajes = app()->session()->pullFlash();
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
