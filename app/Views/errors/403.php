<?php

/** @var string $detalle */
?>
<section class="error">
    <h1>Acceso denegado</h1>
    <p><?= e($detalle !== '' ? $detalle : 'No tienes permiso para realizar esta acción.') ?></p>
    <a class="btn btn-primario" href="<?= e(url('/')) ?>">Ir al inicio</a>
</section>
