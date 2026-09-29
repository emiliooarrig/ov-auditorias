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
 * @var list<array{id: int, numero: int}> $edificios
 * @var list<array{id: int, actividad_id: int, nombre: string, fecha: string, hora_inicio: string,
 *     hora_fin: string, estado: string}> $asignados
 * @var list<int> $idsAsignados
 */

$vista = app()->view();
$ruta = '/asignaciones';
?>
<div class="cabecera-pagina">
    <h1>Asignaciones</h1>
    <p>Elige un auditor y marca los talleres que le tocan. Puedes asignar varios a la vez.</p>
</div>

<form class="tarjeta" method="get" action="<?= e(url($ruta)) ?>">
    <h2 class="paso__titulo"><span class="paso__numero" aria-hidden="true">1</span>Elige al auditor</h2>
    <?php if ($auditores === []) : ?>
        <p class="texto-secundario con-icono">
            <?= icono('info') ?>
            Todavía no hay auditores registrados. Aparecerán aquí cuando ingresen por primera vez con su correo.
        </p>
    <?php else : ?>
        <div class="selector-auditor">
            <div class="campo">
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
            <button type="submit" class="btn btn-primario"><?= icono('flecha-derecha') ?> Ver sus talleres</button>
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
    <div class="asignar">
        <section aria-labelledby="t-asignar">
            <h2 class="paso__titulo" id="t-asignar">
                <span class="paso__numero" aria-hidden="true">2</span>Marca los talleres para <?= e($nombreAuditor) ?>
            </h2>
            <?= $vista->partial(
                'actividades/_filtros',
                compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros')
                    + ['ocultos' => ['usuario' => $seleccionado['id']]]
            ) ?>

            <div data-resultados>
            <?php if ($resultado['filas'] === []) : ?>
                <section class="vacio">
                    <?php if ($hayFiltros) : ?>
                        <?= icono('sin-resultados', 'vacio__icono') ?>
                        <p>
                            <strong>Ningún taller coincide con los filtros.</strong>
                            Prueba con otro nombre o quita alguno de los filtros.
                        </p>
                    <?php else : ?>
                        <?= icono('calendario', 'vacio__icono') ?>
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

                    <table class="tabla tabla--responsiva">
                        <caption class="solo-lector">
                            Talleres activos. Marca los que quieres asignar a <?= e($nombreAuditor) ?>.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-casilla"><span class="solo-lector">Asignar</span></th>
                                <th scope="col" class="col-fecha">Fecha</th>
                                <th scope="col">Taller</th>
                                <th scope="col" class="col-edificio">Edificio</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultado['filas'] as $fila) : ?>
                                <?php $yaAsignado = in_array($fila['id'], $idsAsignados, true); ?>
                                <?php $claseFila = 'fila fila--' . estado_clase((string) $fila['estado']); ?>
                                <tr class="<?= e($claseFila) ?>">
                                    <td class="celda-casilla">
                                        <?php if ($yaAsignado) : ?>
                                            <span class="ya-asignado"><?= icono('check') ?> Ya asignado</span>
                                        <?php else : ?>
                                            <input type="checkbox" name="actividades[]" value="<?= e($fila['id']) ?>"
                                                   id="taller-<?= e($fila['id']) ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="celda-lateral">
                                        <?= $vista->partial('partials/hoja', [
                                            'fecha' => (string) $fila['fecha'],
                                            'inicio' => (string) $fila['hora_inicio'],
                                            'fin' => (string) $fila['hora_fin'],
                                        ]) ?>
                                    </td>
                                    <td>
                                        <span class="tabla__nombre">
                                            <?php if ($yaAsignado) : ?>
                                                <strong><?= e($fila['nombre']) ?></strong>
                                            <?php else : ?>
                                                <label for="taller-<?= e($fila['id']) ?>">
                                                    <strong><?= e($fila['nombre']) ?></strong>
                                                </label>
                                            <?php endif; ?>
                                        </span>
                                        <span class="tabla__sub">
                                            <?= icono('carrera') ?> <?= e($fila['carrera']) ?>
                                        </span>
                                    </td>
                                    <td data-etiqueta="Edificio" class="celda-en-linea">
                                        <span class="placa"><?= e($fila['edificio']) ?></span>
                                    </td>
                                    <td><?= estado_insignia((string) $fila['estado']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="barra-accion">
                        <span class="barra-accion__conteo" data-conteo aria-live="polite"></span>
                        <button type="submit" class="btn btn-primario">
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

        <aside class="tarjeta asignar__lateral" aria-labelledby="t-actuales">
            <h2 id="t-actuales"><?= icono('mis-talleres') ?> Talleres de <?= e($nombreAuditor) ?></h2>
            <?php if ($asignados === []) : ?>
                <p class="texto-secundario">Todavía no tiene talleres asignados.</p>
            <?php else : ?>
                <ul class="lista-auditores">
                    <?php foreach ($asignados as $t) : ?>
                        <li>
                            <div class="persona">
                                <a href="<?= e(url('/actividades/' . $t['actividad_id'])) ?>">
                                    <strong><?= e($t['nombre']) ?></strong>
                                </a>
                                <span class="persona__meta numeros">
                                    <?= icono('calendario') ?> <?= e(fecha_corta($t['fecha'])) ?>,
                                    <?= e(horario($t['hora_inicio'], $t['hora_fin'])) ?>
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
                                    <button type="submit" class="btn btn-enlace btn-enlace--peligro">
                                        <?= icono('usuario-menos') ?>
                                        Quitar<span class="solo-lector"> <?= e($t['nombre']) ?></span>
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
