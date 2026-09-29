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
<h1>Asignaciones</h1>

<form class="tarjeta selector-auditor" method="get" action="<?= e(url($ruta)) ?>">
    <div class="campo">
        <label for="usuario">Auditor</label>
        <?php if ($auditores === []) : ?>
            <p class="texto-secundario">
                Todavía no hay auditores registrados. Aparecerán aquí cuando ingresen por primera vez con su correo.
            </p>
        <?php else : ?>
            <select id="usuario" name="usuario" required>
                <option value="">Elige un auditor</option>
                <?php foreach ($auditores as $a) : ?>
                    <?php $sel = $seleccionado !== null && $seleccionado['id'] === $a['id'] ? ' selected' : ''; ?>
                    <option value="<?= e($a['id']) ?>"<?= $sel ?>>
                        <?= e($a['apellidos'] . ', ' . $a['nombre'] . ' — ' . $a['correo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
    <?php if ($auditores !== []) : ?>
        <button type="submit" class="btn btn-primario">Elegir</button>
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
    <section class="tarjeta">
        <h2>Talleres de <?= e($nombreAuditor) ?></h2>
        <?php if ($asignados === []) : ?>
            <p class="texto-secundario">Todavía no tiene talleres asignados.</p>
        <?php else : ?>
            <ul class="lista-auditores">
                <?php foreach ($asignados as $t) : ?>
                    <li>
                        <div>
                            <a href="<?= e(url('/actividades/' . $t['actividad_id'])) ?>">
                                <strong><?= e($t['nombre']) ?></strong>
                            </a>
                            <span class="texto-secundario">
                                <?= e(fecha_corta($t['fecha'])) ?>, <?= e(horario($t['hora_inicio'], $t['hora_fin'])) ?>
                            </span>
                            <?= estado_insignia($t['estado']) ?>
                        </div>
                        <?php if ($t['estado'] === 'programado') : ?>
                            <form method="post" action="<?= e(url('/asignaciones/' . $t['id'] . '/quitar')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="volver" value="asignaciones">
                                <input type="hidden" name="usuario_id" value="<?= e($seleccionado['id']) ?>">
                                <?php foreach ($regreso as $clave => $valor) : ?>
                                    <input type="hidden" name="<?= e($clave) ?>" value="<?= e($valor) ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-enlace">Quitar</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <h2>Asignar talleres</h2>
    <?= $vista->partial('actividades/_filtros', compact('ruta', 'filtros', 'carreras', 'edificios', 'hayFiltros') + [
        'ocultos' => ['usuario' => $seleccionado['id']],
    ]) ?>

    <?php if ($resultado['filas'] === []) : ?>
        <section class="tarjeta vacio">
            <p><?= $hayFiltros ? 'Ningún taller coincide con los filtros.' : 'No hay talleres activos.' ?></p>
        </section>
    <?php else : ?>
        <form method="post" action="<?= e(url($ruta)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="usuario_id" value="<?= e($seleccionado['id']) ?>">
            <?php foreach ($regreso as $clave => $valor) : ?>
                <input type="hidden" name="<?= e($clave) ?>" value="<?= e($valor) ?>">
            <?php endforeach; ?>

            <table class="tabla tabla--responsiva">
                <caption class="texto-secundario">
                    Marca los talleres que quieres asignar a <?= e($nombreAuditor) ?>.
                </caption>
                <thead>
                    <tr>
                        <th scope="col"><span class="solo-lector">Asignar</span></th>
                        <th scope="col">Taller</th>
                        <th scope="col">Carrera</th>
                        <th scope="col">Edificio</th>
                        <th scope="col">Horario</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultado['filas'] as $fila) : ?>
                        <?php $yaAsignado = in_array($fila['id'], $idsAsignados, true); ?>
                        <tr>
                            <td data-etiqueta="Asignar">
                                <?php if ($yaAsignado) : ?>
                                    <span class="texto-secundario">✓ Ya asignado</span>
                                <?php else : ?>
                                    <input type="checkbox" name="actividades[]" value="<?= e($fila['id']) ?>"
                                           id="taller-<?= e($fila['id']) ?>">
                                <?php endif; ?>
                            </td>
                            <td data-etiqueta="Taller">
                                <?php if ($yaAsignado) : ?>
                                    <strong><?= e($fila['nombre']) ?></strong>
                                <?php else : ?>
                                    <label for="taller-<?= e($fila['id']) ?>">
                                        <strong><?= e($fila['nombre']) ?></strong>
                                    </label>
                                <?php endif; ?>
                            </td>
                            <td data-etiqueta="Carrera"><?= e($fila['carrera']) ?></td>
                            <td data-etiqueta="Edificio"><?= e($fila['edificio']) ?></td>
                            <td data-etiqueta="Horario">
                                <?= e(fecha_corta((string) $fila['fecha'])) ?><br>
                                <span class="texto-secundario">
                                    <?= e(horario((string) $fila['hora_inicio'], (string) $fila['hora_fin'])) ?>
                                </span>
                            </td>
                            <td data-etiqueta="Estado"><?= estado_insignia((string) $fila['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="formulario__acciones">
                <button type="submit" class="btn btn-primario">Asignar talleres seleccionados</button>
            </div>
        </form>
        <?= $vista->partial('partials/paginacion', [
            'ruta' => $ruta,
            'query' => ['usuario' => $seleccionado['id']] + $filtros,
            'pagina' => $resultado['pagina'],
            'paginas' => $resultado['paginas'],
        ]) ?>
    <?php endif; ?>
<?php endif; ?>
