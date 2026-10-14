<?php

/**
 * Lista de alumnos con búsqueda por nombre y filtros de estado y talleres. El servidor entrega a todos
 * y oculta los que no coinciden con los filtros de la URL, así funciona sin JavaScript; con él,
 * app.js filtra en vivo y mantiene los filtros en los enlaces y formularios.
 *
 * @phpstan-import-type AlumnoListado from \App\Models\Alumno
 * @var list<AlumnoListado> $alumnos
 * @var array{texto: string, estado: string, talleres: string} $filtros
 * @var int|null $seleccionadoId
 */

$buscado = texto_busqueda($filtros['texto']);
$hayFiltros = $filtros['texto'] !== '' || $filtros['estado'] !== '' || $filtros['talleres'] !== '';
$visibles = 0;
$filas = [];
foreach ($alumnos as $al) {
    $estado = (int) $al['activo'] === 1 ? 'activos' : 'bajas';
    $conTalleres = (int) $al['talleres'] > 0 ? 'con' : 'sin';
    $nombre = $al['nombre'] . ' ' . $al['apellidos'];
    $coincide = ($buscado === '' || str_contains(texto_busqueda($nombre), $buscado))
        && ($filtros['estado'] === '' || $filtros['estado'] === $estado)
        && ($filtros['talleres'] === '' || $filtros['talleres'] === $conTalleres);
    $visibles += $coincide ? 1 : 0;
    $filas[] = $al + [
        'estado' => $estado,
        'conTalleres' => $conTalleres,
        'busqueda' => $nombre,
        'visible' => $coincide,
    ];
}
$total = cantidad(count($alumnos), 'alumno', 'alumnos');
$opcionesEstado = ['' => 'Todos', 'activos' => 'Activos', 'bajas' => 'Dados de baja'];
$opcionesTalleres = ['' => 'Todos', 'sin' => 'Sin taller', 'con' => 'Con taller'];
$ruta = $seleccionadoId === null ? '/alumnos' : '/alumnos/' . $seleccionadoId;
$opciones = static fn (array $valores, string $actual): string => implode('', array_map(
    static fn (string $valor, string $texto): string => '<option value="' . e($valor) . '"'
        . ($valor === $actual ? ' selected' : '') . '>' . e($texto) . '</option>',
    array_keys($valores),
    $valores
));
?>
<?php if ($alumnos !== []) : ?>
    <form class="card filters filters--compact" method="get" action="<?= e(url($ruta)) ?>" role="search"
          aria-label="Buscar alumnos" data-filtro-local="#lista-alumnos">
        <div class="field">
            <label for="a-texto"><?= icono('buscar') ?> Nombre</label>
            <input type="search" id="a-texto" name="texto" value="<?= e($filtros['texto']) ?>" maxlength="150"
                   class="<?= $filtros['texto'] !== '' ? 'is-active' : '' ?>" placeholder="Ej. López"
                   autocomplete="off">
        </div>
        <div class="field">
            <label for="a-estado"><?= icono('encender') ?> Estado</label>
            <select id="a-estado" name="estado" class="<?= $filtros['estado'] !== '' ? 'is-active' : '' ?>">
                <?= $opciones($opcionesEstado, $filtros['estado']) ?>
            </select>
        </div>
        <div class="field">
            <label for="a-talleres"><?= icono('calendario') ?> Talleres</label>
            <select id="a-talleres" name="talleres" class="<?= $filtros['talleres'] !== '' ? 'is-active' : '' ?>">
                <?= $opciones($opcionesTalleres, $filtros['talleres']) ?>
            </select>
        </div>
        <div class="filters__actions">
            <button type="submit" class="btn btn--primary filters__submit"><?= icono('buscar') ?> Filtrar</button>
            <a class="btn btn--secondary" href="<?= e(url($ruta)) ?>" data-limpiar<?= $hayFiltros ? '' : ' hidden' ?>>
                <?= icono('cerrar') ?> Limpiar
            </a>
        </div>
    </form>

    <p class="summary" role="status">
        <strong data-conteo-filtro data-singular="alumno" data-plural="alumnos">
            <?= e($hayFiltros ? $visibles . ' de ' . $total : $total) ?>
        </strong>
    </p>

    <section class="empty" data-sin-coincidencias<?= $visibles === 0 ? '' : ' hidden' ?>>
        <span class="icon-bubble"><?= icono('sin-resultados') ?></span>
        <p><strong>Ningún alumno coincide con la búsqueda.</strong></p>
        <a class="btn btn--secondary" href="<?= e(url($ruta)) ?>" data-limpiar><?= icono('cerrar') ?> Quitar filtros</a>
    </section>

    <ul class="roster" id="lista-alumnos"<?= $visibles === 0 ? ' hidden' : '' ?>>
        <?php foreach ($filas as $al) : ?>
            <?php
            $activo = $al['estado'] === 'activos';
            $actual = $al['id'] === $seleccionadoId ? ' aria-current="true"' : '';
            ?>
            <li data-buscar="<?= e($al['busqueda']) ?>" data-estado="<?= e($al['estado']) ?>"
                data-talleres="<?= e($al['conTalleres']) ?>"<?= $al['visible'] ? '' : ' hidden' ?>>
                <a class="roster__item" href="<?= e(url('/alumnos/' . $al['id'], $filtros)) ?>"
                   data-panel-enlace data-conserva-filtros<?= $actual ?>>
                    <span class="roster__name"><?= e($al['apellidos'] . ', ' . $al['nombre']) ?></span>
                    <span class="roster__meta">
                        <?php if (!$activo) : ?>
                            <span class="badge badge--inactive"><?= icono('prohibido') ?> Baja</span>
                        <?php elseif ($al['horario'] === null) : ?>
                            <span class="badge badge--warning"><?= icono('alerta') ?> Sin horario</span>
                        <?php elseif ((int) $al['talleres'] === 0) : ?>
                            <span class="badge badge--warning"><?= icono('alerta') ?> Sin taller</span>
                        <?php endif; ?>
                        <?php if ($al['horario'] !== null) : ?>
                            <span class="with-icon"><?= icono('reloj') ?> <?= e($al['horario']) ?></span>
                        <?php endif; ?>
                        <?php if ((int) $al['talleres'] > 0) : ?>
                            <span class="with-icon nums">
                                <?= icono('calendario') ?>
                                <?= e(cantidad((int) $al['talleres'], 'taller', 'talleres')) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else : ?>
    <section class="empty">
        <span class="icon-bubble"><?= icono('usuarios') ?></span>
        <p>
            <strong>Todavía no hay alumnos.</strong>
            Agrégalos con el formulario: uno por uno o pegando una lista.
        </p>
    </section>
<?php endif; ?>
