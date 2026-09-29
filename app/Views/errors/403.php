<?php

/** @var string $detalle */
?>
<section class="error">
    <?= icono('candado', 'error__icono') ?>
    <span class="error__codigo" aria-hidden="true">403</span>
    <h1>Acceso denegado</h1>
    <p><?= e($detalle !== '' ? $detalle : 'No tienes permiso para realizar esta acción.') ?></p>
    <a class="btn btn-primario" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
