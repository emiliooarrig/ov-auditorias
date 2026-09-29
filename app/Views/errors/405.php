<section class="card error-page">
    <span class="icon-bubble"><?= icono('prohibido') ?></span>
    <span class="error-page__code" aria-hidden="true">405</span>
    <h1>Método no permitido</h1>
    <p>Esta dirección no acepta ese tipo de petición.</p>
    <a class="btn btn--primary" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
