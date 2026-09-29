<section class="error">
    <?= icono('sin-resultados', 'error__icono') ?>
    <span class="error__codigo" aria-hidden="true">404</span>
    <h1>Página no encontrada</h1>
    <p>La página que buscas no existe o no tienes acceso a ella.</p>
    <a class="btn btn-primario" href="<?= e(url('/')) ?>"><?= icono('inicio') ?> Ir a la página de inicio</a>
</section>
