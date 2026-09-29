<section class="error">
    <?= icono('prohibido', 'error__icono') ?>
    <span class="error__codigo" aria-hidden="true">405</span>
    <h1>Método no permitido</h1>
    <p>Esta dirección no acepta ese tipo de petición.</p>
    <a class="btn btn-primario" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
