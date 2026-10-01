<?php

/**
 * Asignación de talleres a un auditor (CU-01): elegir auditor, filtrar talleres, marcar y confirmar.
 *
 * @var list<array{id: int, nombre: string, apellidos: string, correo: string}> $auditores
 * @var array{id: int, nombre: string, apellidos: string, correo: string}|null $seleccionado
 * @var array{nombre: string, carrera: ?int, edificio: ?int} $filtros
 * @var bool $hayFiltros
 * @var array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int} $resultado
 * @var list<array{id: int, nombre: string}> $carreras
 * @var list<array{id: int, numero: int, nombre: string}> $edificios
 * @var list<array{id: int, actividad_id: int, nombre: string, fecha: string, hora_inicio: string,
 *     hora_fin: string, grupo_nombre: string, estado: string}> $asignados
 * @var list<int> $idsAsignados
 */

$vista = app()->view();
$ruta = '/asignaciones';
?>
<div class="page-header">
    <div>
        <h1>Asignaciones</h1>
        <p>Elige un auditor y marca los talleres que le tocan. Puedes asignar varios a la vez.</p>
    </div>
</div>

<form class="card" method="get" action="<?= e(url($ruta)) ?>">
    <h2 class="step-title"><span class="step-number" aria-hidden="true">1</span>Elige al auditor</h2>
    <?php if ($auditores === []) : ?>
        <p class="muted with-icon">
            <?= icono('info') ?>
            Todavía no hay auditores registrados. Aparecerán aquí cuando ingresen por primera vez con su correo.
        </p>
    <?php else : ?>
        <div class="picker">
            <div class="field">
                <label for="usuario"><?= icono('usuario') ?> Auditor</label>
                <select id="usuario" name="usuario" required>
                    <option value="">Elige un auditor</option>
                    <?php foreach ($auditores as $a) : ?>
                        <?php $sel = $seleccionado !== null && $seleccionado['id'] === $a['id'] ? ' selected' : ''; ?>
                        <option value="<?= e($a['id']) ?>"<?= $sel ?>>
                            <?= e($a['apellidos'] . ', ' . $a['nombre'] . ' (' . $a['correo'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn--primary"><?= icono('flecha-derecha') ?> Ver sus talleres</button>
        </div>
    <?php endif; ?>
</form>

<?php if ($seleccionado !== null) : ?>
    <?php
    $nombreAuditor = $seleccionado['nombre'] . ' ' . $seleccionado['apellidos'];
    // Filtros y página actuales: se reenvían para volver a la misma lista tras cada acción.
    $regreso = array_filter(
        $filtros + ['pagina' => $resultado['pagina'] > 1 ? $resultado['pagina'] : null],
        static fn (mixed $v): bool => $v !== null && $v !== ''
    );
    ?>
    <div class="layout-assign">
        <section aria-labelledby="t-asignar">
            <h2 class="step-title" id="t-asignar">
                <span class="step-number" aria-hidden="true">2</span>Marca los talleres para <?= e($nombreAuditor) ?>
            </h2>
            <?= $vista->partial(
                'actividades/_filtros',
                compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros')
                    + ['ocultos' => ['usuario' => $seleccionado['id']]]
            ) ?>

            <div data-resultados>
            <?php if ($resultado['filas'] === []) : ?>
                <section class="empty">
                    <?php if ($hayFiltros) : ?>
                        <span class="icon-bubble"><?= icono('sin-resultados') ?></span>
                        <p>
                            <strong>Ningún taller coincide con los filtros.</strong>
                            Prueba con otro nombre o quita alguno de los filtros.
                        </p>
                    <?php else : ?>
                        <span class="icon-bubble"><?= icono('calendario') ?></span>
                        <p><strong>No hay talleres activos.</strong>Crea talleres desde el panel central.</p>
                    <?php endif; ?>
                </section>
            <?php else : ?>
                <form method="post" action="<?= e(url($ruta)) ?>" data-conteo-seleccion>
                    <?= csrf_field() ?>
                    <input type="hidden" name="usuario_id" value="<?= e($seleccionado['id']) ?>">
                    <?php foreach ($regreso as $clave => $valor) : ?>
                        <input type="hidden" name="<?= e($clave) ?>" value="<?= e($valor) ?>">
                    <?php endforeach; ?>

                    <div class="card table-wrap">
                    <table class="table table--stack">
                        <caption class="sr-only">
                            Talleres activos. Marca los que quieres asignar a <?= e($nombreAuditor) ?>.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-check"><span class="sr-only">Asignar</span></th>
                                <th scope="col">Taller</th>
                                <th scope="col" class="col-date">Fecha</th>
                                <th scope="col">Edificio</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultado['filas'] as $fila) : ?>
                                <?php $yaAsignado = in_array($fila['id'], $idsAsignados, true); ?>
                                <tr>
                                    <td class="cell-check">
                                        <?php if ($yaAsignado) : ?>
                                            <span class="already"><?= icono('check') ?> Ya asignado</span>
                                        <?php else : ?>
                                            <?php /* Etiqueta sin texto: da 44 × 44 px de área táctil. */ ?>
                                            <label class="check-target">
                                                <input type="checkbox" name="actividades[]"
                                                       value="<?= e($fila['id']) ?>" id="taller-<?= e($fila['id']) ?>">
                                            </label>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-title">
                                        <span class="table__title">
                                            <?php if ($yaAsignado) : ?>
                                                <?= e($fila['nombre']) ?>
                                            <?php else : ?>
                                                <label for="taller-<?= e($fila['id']) ?>">
                                                    <?= e($fila['nombre']) ?>
                                                </label>
                                            <?php endif; ?>
                                        </span>
                                        <span class="table__sub">
                                            <?= icono('carrera') ?> <?= e($fila['carrera']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Fecha">
                                        <?= $vista->partial('partials/fecha', [
                                            'fecha' => (string) $fila['fecha'],
                                            'inicio' => (string) $fila['hora_inicio'],
                                            'fin' => (string) $fila['hora_fin'],
                                            'grupo' => (string) $fila['grupo_nombre'],
                                        ]) ?>
                                    </td>
                                    <td data-label="Edificio">
                                        <span class="building">
                                            <?= icono('edificio') ?>
                                            <?= e(edificio_etiqueta($fila['edificio'], $fila['edificio_nombre'])) ?>
                                        </span>
                                    </td>
                                    <td data-label="Estado"><?= estado_insignia((string) $fila['estado']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="action-bar">
                        <span class="action-bar__count" data-conteo aria-live="polite"></span>
                        <button type="submit" class="btn btn--primary">
                            <?= icono('asignar') ?> Asignar talleres seleccionados
                        </button>
                    </div>
                </form>
                <?= $vista->partial('partials/paginacion', [
                    'ruta' => $ruta,
                    'query' => ['usuario' => $seleccionado['id']] + $filtros,
                    'pagina' => $resultado['pagina'],
                    'paginas' => $resultado['paginas'],
                ]) ?>
            <?php endif; ?>
            </div>
        </section>

        <aside class="card" aria-labelledby="t-actuales">
            <h2 class="card__title" id="t-actuales">
                <?= icono('mis-talleres') ?> Talleres de <?= e($nombreAuditor) ?>
            </h2>
            <?php if ($asignados === []) : ?>
                <p class="muted">Todavía no tiene talleres asignados.</p>
            <?php else : ?>
                <ul class="people">
                    <?php foreach ($asignados as $t) : ?>
                        <li>
                            <div class="person">
                                <a href="<?= e(url('/actividades/' . $t['actividad_id'])) ?>">
                                    <strong><?= e($t['nombre']) ?></strong>
                                </a>
                                <span class="person__meta nums">
                                    <?= icono('calendario') ?> <?= e(fecha_corta($t['fecha'])) ?>,
                                    <?= e(grupo_etiqueta($t['grupo_nombre'], $t['hora_inicio'], $t['hora_fin'])) ?>
                                </span>
                                <span><?= estado_insignia($t['estado']) ?></span>
                            </div>
                            <?php if ($t['estado'] === 'programado') : ?>
                                <form method="post" action="<?= e(url('/asignaciones/' . $t['id'] . '/quitar')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="volver" value="asignaciones">
                                    <input type="hidden" name="usuario_id" value="<?= e($seleccionado['id']) ?>">
                                    <?php foreach ($regreso as $clave => $valor) : ?>
                                        <input type="hidden" name="<?= e($clave) ?>" value="<?= e($valor) ?>">
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn btn--link btn--link-danger">
                                        <?= icono('usuario-menos') ?>
                                        Quitar<span class="sr-only"> <?= e($t['nombre']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>
<?php endif; ?>
