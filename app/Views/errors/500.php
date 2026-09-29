<?php
/** @var string $detalle */
?>
<section class="error">
    <?= icono('alerta', 'error__icono') ?>
    <span class="error__codigo" aria-hidden="true">500</span>
    <h1>Ocurrió un error</h1>
    <p>No pudimos completar la operación. Inténtalo de nuevo en unos minutos.</p>
    <?php if ($detalle !== '') : ?>
        <pre class="error__detalle"><?= e($detalle) ?></pre>
    <?php endif; ?>
    <a class="btn btn-primario" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
