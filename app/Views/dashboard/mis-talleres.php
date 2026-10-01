<?php

/**
 * "Mis talleres": los talleres asignados al auditor en sesión (RF-11), con filtros limitados a ellos.
 *
 * @var string $ruta
 * @var array{nombre: string, carrera: ?int, edificio: ?int} $filtros
 * @var bool $hayFiltros
 * @var array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int} $resultado
 * @var list<array{id: int, nombre: string}> $carreras
 * @var list<array{id: int, numero: int, nombre: string}> $edificios
 */

$vista = app()->view();
?>
<div class="page-header">
    <div>
        <h1>Mis talleres</h1>
        <p>Los talleres que te asignaron. Abre uno para ver sus datos o registrar que no se realizó.</p>
    </div>
</div>

<?php if ($resultado['total'] === 0 && !$hayFiltros) : ?>
    <section class="empty">
        <span class="icon-bubble"><?= icono('bandeja') ?></span>
        <p>
            <strong>Todavía no tienes talleres asignados.</strong>
            Cuando el administrador te asigne alguno, aparecerá aquí con su fecha, horario y edificio.
        </p>
    </section>
<?php else : ?>
    <?= $vista->partial('actividades/_filtros', compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros')) ?>

    <div data-resultados>
    <p class="summary" data-resumen>
        <span>
            <?php $unidad = $resultado['total'] === 1 ? 'taller asignado' : 'talleres asignados'; ?>
            <strong><?= e($resultado['total']) ?> <?= $unidad ?></strong>
            <?= $hayFiltros ? 'coinciden con los filtros' : '' ?>
        </span>
        <?php if ($resultado['paginas'] > 1) : ?>
            <span>Página <?= e($resultado['pagina']) ?> de <?= e($resultado['paginas']) ?></span>
        <?php endif; ?>
    </p>

    <?php if ($resultado['filas'] === []) : ?>
        <section class="empty">
            <span class="icon-bubble"><?= icono('sin-resultados') ?></span>
            <p><strong>Ninguno de tus talleres coincide con los filtros.</strong></p>
            <a class="btn btn--secondary" href="<?= e(url($ruta)) ?>" data-limpiar>
                <?= icono('cerrar') ?> Quitar filtros
            </a>
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
<?php endif; ?>
