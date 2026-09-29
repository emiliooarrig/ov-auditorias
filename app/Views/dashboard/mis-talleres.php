<?php

/**
 * "Mis talleres": los talleres asignados al auditor en sesión (RF-11), con filtros limitados a ellos.
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
<h1>Mis talleres</h1>

<?php if ($resultado['total'] === 0 && !$hayFiltros) : ?>
    <section class="tarjeta vacio">
        <p>Todavía no tienes talleres asignados. Cuando el administrador te asigne alguno, aparecerá aquí.</p>
    </section>
<?php else : ?>
    <?= $vista->partial('actividades/_filtros', compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros')) ?>

    <p class="texto-secundario" role="status">
        <?= e($resultado['total']) ?> <?= $resultado['total'] === 1 ? 'taller asignado' : 'talleres asignados' ?>
        <?= $hayFiltros ? 'coinciden con los filtros' : '' ?>
    </p>

    <?php if ($resultado['filas'] === []) : ?>
        <section class="tarjeta vacio">
            <p>Ninguno de tus talleres coincide con los filtros. <a href="<?= e(url($ruta)) ?>">Quitar filtros</a></p>
        </section>
    <?php else : ?>
        <?= $vista->partial('actividades/_tabla', ['filas' => $resultado['filas'], 'enlaceDetalle' => false]) ?>
        <?= $vista->partial('partials/paginacion', [
            'ruta' => $ruta,
            'query' => $filtros,
            'pagina' => $resultado['pagina'],
            'paginas' => $resultado['paginas'],
        ]) ?>
    <?php endif; ?>
<?php endif; ?>
