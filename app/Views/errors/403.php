<?php

/** @var string $detalle */
?>
<section class="card error-page">
    <span class="icon-bubble"><?= icono('candado') ?></span>
    <span class="error-page__code" aria-hidden="true">403</span>
    <h1>Acceso denegado</h1>
    <p><?= e($detalle !== '' ? $detalle : 'No tienes permiso para realizar esta acción.') ?></p>
    <a class="btn btn--primary" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
