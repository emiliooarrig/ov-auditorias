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
    <a class="btn btn-primario" href="<?= e(url('/actividades/nueva')) ?>">+ Nuevo taller</a>
</div>

<?= $vista->partial('actividades/_filtros', compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros')) ?>

<p class="texto-secundario" role="status">
    <?= e($resultado['total']) ?> <?= $resultado['total'] === 1 ? 'taller' : 'talleres' ?>
    <?= $hayFiltros ? 'coinciden con los filtros' : 'activos' ?>
</p>

<?php if ($resultado['filas'] === []) : ?>
    <section class="tarjeta vacio">
        <?php if ($hayFiltros) : ?>
            <p>Ningún taller coincide con los filtros. <a href="<?= e(url($ruta)) ?>">Quitar filtros</a></p>
        <?php else : ?>
            <p>Todavía no hay talleres. <a href="<?= e(url('/actividades/nueva')) ?>">Crea el primero</a>.</p>
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
