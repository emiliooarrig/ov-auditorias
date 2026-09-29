<?php
/** @var string $detalle */
?>
<section class="card error-page">
    <span class="icon-bubble"><?= icono('alerta') ?></span>
    <span class="error-page__code" aria-hidden="true">500</span>
    <h1>Ocurrió un error</h1>
    <p>No pudimos completar la operación. Inténtalo de nuevo en unos minutos.</p>
    <?php if ($detalle !== '') : ?>
        <pre class="error-page__detail"><?= e($detalle) ?></pre>
    <?php endif; ?>
    <a class="btn btn--primary" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
