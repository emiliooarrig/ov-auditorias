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
<div class="page-header">
    <div>
        <h1>Panel central</h1>
        <p>Todos los talleres activos. Filtra por nombre, carrera o edificio y abre uno para ver su detalle.</p>
    </div>
    <a class="btn btn--primary" href="<?= e(url('/actividades/nueva')) ?>"><?= icono('mas') ?> Nuevo taller</a>
</div>

<?= $vista->partial('actividades/_filtros', compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros')) ?>

<div data-resultados>
<p class="summary" data-resumen>
    <span>
        <strong><?= e($resultado['total']) ?> <?= $resultado['total'] === 1 ? 'taller' : 'talleres' ?></strong>
        <?= $hayFiltros ? 'coinciden con los filtros' : 'activos' ?>
    </span>
    <?php if ($resultado['paginas'] > 1) : ?>
        <span>Página <?= e($resultado['pagina']) ?> de <?= e($resultado['paginas']) ?></span>
    <?php endif; ?>
</p>

<?php if ($resultado['filas'] === []) : ?>
    <section class="empty">
        <?php if ($hayFiltros) : ?>
            <span class="icon-bubble"><?= icono('sin-resultados') ?></span>
            <p>
                <strong>Ningún taller coincide con los filtros.</strong>
                Prueba con otro nombre o quita alguno de los filtros.
            </p>
            <a class="btn btn--secondary" href="<?= e(url($ruta)) ?>" data-limpiar>
                <?= icono('cerrar') ?> Quitar filtros
            </a>
        <?php else : ?>
            <span class="icon-bubble"><?= icono('calendario') ?></span>
            <p><strong>Todavía no hay talleres.</strong>Crea el primero para poder asignarle auditores.</p>
            <a class="btn btn--primary" href="<?= e(url('/actividades/nueva')) ?>"><?= icono('mas') ?> Crear taller</a>
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
