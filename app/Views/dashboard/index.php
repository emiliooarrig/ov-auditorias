<?php

/**
 * Panel central del administrador (RF-03, RF-08).
 *
 * @var string $ruta
 * @var array{nombre: string, carrera: ?int, edificio: ?int} $filtros
 * @var bool $hayFiltros
 * @var array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int} $resultado
 * @var list<array{id: int, nombre: string}> $carreras
 * @var list<array{id: int, numero: int}> $edificios
 */

$vista = app()->view();
?>
<div class="titulo-acciones">
    <h1>Panel central</h1>
    <a class="btn btn-primario" href="<?= e(url('/actividades/nueva')) ?>"><?= icono('mas') ?> Nuevo taller</a>
</div>

<?= $vista->partial('actividades/_filtros', compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros')) ?>

<div data-resultados>
<p class="resumen" data-resumen>
    <span>
        <strong><?= e($resultado['total']) ?> <?= $resultado['total'] === 1 ? 'taller' : 'talleres' ?></strong>
        <?= $hayFiltros ? 'coinciden con los filtros' : 'activos' ?>
    </span>
    <?php if ($resultado['paginas'] > 1) : ?>
        <span>Página <?= e($resultado['pagina']) ?> de <?= e($resultado['paginas']) ?></span>
    <?php endif; ?>
</p>

<?php if ($resultado['filas'] === []) : ?>
    <section class="vacio">
        <?php if ($hayFiltros) : ?>
            <?= icono('sin-resultados', 'vacio__icono') ?>
            <p>
                <strong>Ningún taller coincide con los filtros.</strong>
                Prueba con otro nombre o quita alguno de los filtros.
            </p>
            <a class="btn btn-secundario" href="<?= e(url($ruta)) ?>" data-limpiar>
                <?= icono('cerrar') ?> Quitar filtros
            </a>
        <?php else : ?>
            <?= icono('calendario', 'vacio__icono') ?>
            <p><strong>Todavía no hay talleres.</strong>Crea el primero para poder asignarle auditores.</p>
            <a class="btn btn-primario" href="<?= e(url('/actividades/nueva')) ?>"><?= icono('mas') ?> Crear taller</a>
        <?php endif; ?>
    </section>
<?php else : ?>
    <?= $vista->partial('actividades/_tabla', ['filas' => $resultado['filas'], 'enlaceDetalle' => true]) ?>
    <?= $vista->partial('partials/paginacion', [
        'ruta' => $ruta,
        'query' => $filtros,
        'pagina' => $resultado['pagina'],
        'paginas' => $resultado['paginas'],
    ]) ?>
<?php endif; ?>
</div>
