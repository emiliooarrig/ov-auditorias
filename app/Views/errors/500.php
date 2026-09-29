<?php
/** @var string $detalle */
?>
<section class="error">
    <h1>Ocurrió un error</h1>
    <p>No pudimos completar la operación. Inténtalo de nuevo en unos minutos.</p>
    <?php if ($detalle !== '') : ?>
        <pre class="error__detalle"><?= e($detalle) ?></pre>
    <?php endif; ?>
    <a class="btn btn-primario" href="<?= e(url('/')) ?>">Ir al inicio</a>
</section>
