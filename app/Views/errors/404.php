<section class="card error-page">
    <span class="icon-bubble"><?= icono('sin-resultados') ?></span>
    <span class="error-page__code" aria-hidden="true">404</span>
    <h1>Página no encontrada</h1>
    <p>La página que buscas no existe o no tienes acceso a ella.</p>
    <a class="btn btn--primary" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
