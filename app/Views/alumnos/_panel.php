<?php

/**
 * Panel de un alumno: sus datos y su horario, los talleres que audita (con quién y cómo se asignaron),
 * asignarle un taller a mano, corregir su nombre o su horario y darlo de baja o reactivarlo.
 *
 * @phpstan-import-type AlumnoFila from \App\Models\Alumno
 * @var AlumnoFila $alumno
 * @var list<array{id: int, actividad_id: int, nombre: string, fecha: string, hora_inicio: string,
 *     hora_fin: string, grupo_nombre: string, edificio: int, edificio_nombre: string, carrera: string,
 *     estado: string, origen: string, asignado_en: string, asignado_por: string, en_horario: ?int}> $talleres
 * @var list<array{id: int, nombre: string, fecha: string, hora_inicio: string, hora_fin: string,
 *     grupo_nombre: string, edificio: int, edificio_nombre: string, auditores: int}> $disponibles
 * @var array{texto: string, estado: string, talleres: string} $filtros
 * @var list<array{id: int, nombre: string, hora_inicio: string, hora_fin: string}> $horarios
 */

$vista = app()->view();
$id = (int) $alumno['id'];
$activo = (int) $alumno['activo'] === 1;
$nombreCompleto = $alumno['nombre'] . ' ' . $alumno['apellidos'];
$errorNombre = error_de('nombre');
$errorApellidos = error_de('apellidos');
$errorHorario = error_de('horario_id');
$conHorario = $alumno['horario'] !== null;
$etiquetaHorario = horario_alumno($alumno['horario'], $alumno['horario_inicio'], $alumno['horario_fin']);
$programados = count(array_filter($talleres, static fn (array $t): bool => $t['estado'] === 'programado'));

// Talleres disponibles agrupados por franja (fecha y grupo): una lista desplegable con encabezados.
$franjas = [];
foreach ($disponibles as $t) {
    $etiqueta = fecha_corta($t['fecha']) . ' · '
        . grupo_etiqueta($t['grupo_nombre'], $t['hora_inicio'], $t['hora_fin']);
    $franjas[$etiqueta][] = $t;
}
$detalleBaja = match ($programados) {
    0 => 'No podrá recibir talleres hasta que lo reactives.',
    1 => 'Se le quitará el taller programado; los realizados se conservan.',
    default => "Se le quitarán los {$programados} talleres programados; los realizados se conservan.",
};
// Tras un error al corregir los datos se muestra lo que se escribió; si no, lo guardado.
$hayErrorDatos = $errorNombre !== null || $errorApellidos !== null || $errorHorario !== null;
$valorNombre = $hayErrorDatos ? old('nombre') : $alumno['nombre'];
$valorApellidos = $hayErrorDatos ? old('apellidos') : $alumno['apellidos'];
$valorHorario = $hayErrorDatos ? old('horario_id') : (string) ($alumno['horario_id'] ?? '');
$describe = static fn (?string $error, string $id): string => $error !== null
    ? 'aria-invalid="true" aria-describedby="' . $id . '-error"'
    : '';
?>
<section class="card panel" aria-labelledby="t-alumno">
    <header class="panel__header">
        <h2 class="panel__title" id="t-alumno" tabindex="-1" data-panel-titulo>
            <?= icono('usuario') ?> <?= e($nombreCompleto) ?>
        </h2>
        <div class="panel__badges">
            <?php if ($activo) : ?>
                <span class="badge badge--active"><?= icono('check-circulo') ?> Activo</span>
            <?php else : ?>
                <span class="badge badge--inactive"><?= icono('prohibido') ?> Dado de baja</span>
            <?php endif; ?>
            <?php if ($conHorario) : ?>
                <span class="badge badge--role nums"><?= icono('reloj') ?> <?= e($etiquetaHorario) ?></span>
            <?php else : ?>
                <span class="badge badge--warning"><?= icono('alerta') ?> Sin horario</span>
            <?php endif; ?>
            <span class="badge badge--role nums">
                <?= icono('calendario') ?> <?= e(cantidad(count($talleres), 'taller', 'talleres')) ?>
            </span>
        </div>
        <p class="person__meta">
            <?= icono('historial') ?>
            <?php if ($alumno['registrado_por'] !== null) : ?>
                Lo registró <?= e($alumno['registrado_por']) ?> el <?= e(fecha_hora($alumno['creado_en'])) ?>.
            <?php else : ?>
                Viene de una cuenta de auditor creada el <?= e(fecha_hora($alumno['creado_en'])) ?>.
            <?php endif; ?>
        </p>
    </header>

    <section class="panel__section" aria-labelledby="t-sus-talleres">
        <h3 class="panel__subtitle" id="t-sus-talleres"><?= icono('mis-talleres') ?> Talleres que audita</h3>
        <?php if ($talleres === []) : ?>
            <p class="muted with-icon"><?= icono('info') ?> Todavía no audita ningún taller.</p>
        <?php else : ?>
            <ul class="people">
                <?php foreach ($talleres as $t) : ?>
                    <li>
                        <div class="person">
                            <a href="<?= e(url('/actividades/' . $t['actividad_id'])) ?>">
                                <strong><?= e($t['nombre']) ?></strong>
                            </a>
                            <span class="person__meta nums">
                                <?= icono('calendario') ?> <?= e(fecha_corta($t['fecha'])) ?>,
                                <?= e(grupo_etiqueta($t['grupo_nombre'], $t['hora_inicio'], $t['hora_fin'])) ?>
                            </span>
                            <span class="person__meta">
                                <?= icono('edificio') ?>
                                <?= e(edificio_etiqueta($t['edificio'], $t['edificio_nombre'])) ?>
                            </span>
                            <span class="person__meta">
                                <?= icono($t['origen'] === 'aleatoria' ? 'aleatorio' : 'asignar') ?>
                                <?= $t['origen'] === 'aleatoria' ? 'Al azar' : 'A mano' ?>,
                                por <?= e($t['asignado_por']) ?> el <?= e(fecha_hora($t['asignado_en'])) ?>
                            </span>
                            <span><?= estado_insignia($t['estado']) ?></span>
                            <?php if ($t['estado'] === 'programado' && $t['en_horario'] === 0) : ?>
                                <span>
                                    <span class="badge badge--warning">
                                        <?= icono('alerta') ?> Fuera de su horario
                                    </span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($t['estado'] === 'programado') : ?>
                            <form method="post" action="<?= e(url('/asignaciones/' . $t['id'] . '/quitar')) ?>">
                                <?= $vista->partial('alumnos/_regreso', compact('filtros')) ?>
                                <input type="hidden" name="volver" value="alumno">
                                <input type="hidden" name="alumno_id" value="<?= e($id) ?>">
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

        <?php if (!$activo) : ?>
            <p class="muted field__hint"><?= icono('info') ?> Está dado de baja. Reactívalo para asignarle talleres.</p>
        <?php elseif (!$conHorario) : ?>
            <p class="muted field__hint">
                <?= icono('info') ?> Registra su horario en «Corregir datos» para asignarle talleres.
            </p>
        <?php elseif ($disponibles === []) : ?>
            <p class="muted field__hint">
                <?= icono('info') ?> No hay talleres programados dentro de su horario a horas que tenga libres.
            </p>
        <?php else : ?>
            <form class="inline-form" method="post" action="<?= e(url('/asignaciones')) ?>">
                <?= $vista->partial('alumnos/_regreso', compact('filtros')) ?>
                <input type="hidden" name="volver" value="alumno">
                <input type="hidden" name="alumno_id" value="<?= e($id) ?>">
                <div class="inline-form__row">
                    <div class="field">
                        <label for="asignar-taller"><?= icono('asignar') ?> Asignar un taller</label>
                        <select id="asignar-taller" name="actividad_id" required aria-describedby="asignar-ayuda">
                            <option value="">Elige un taller</option>
                            <?php foreach ($franjas as $franja => $opciones) : ?>
                                <optgroup label="<?= e($franja) ?>">
                                    <?php foreach ($opciones as $t) : ?>
                                        <option value="<?= e($t['id']) ?>">
                                            <?= e($t['nombre']
                                                . ' — ' . edificio_etiqueta($t['edificio'], $t['edificio_nombre'])
                                                . ' — ' . ((int) $t['auditores'] === 0
                                                    ? 'sin auditor'
                                                    : cantidad((int) $t['auditores'], 'auditor', 'auditores'))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn--primary"><?= icono('asignar') ?> Asignar</button>
                </div>
                <span class="field__hint" id="asignar-ayuda">
                    <?= icono('info') ?> Solo aparecen talleres programados dentro de su horario
                    (<?= e($etiquetaHorario) ?>) a horas que tiene libres.
                </span>
            </form>
        <?php endif; ?>
    </section>

    <div class="panel__actions row-actions">
        <details class="disclosure"<?= $hayErrorDatos || !$conHorario ? ' open' : '' ?>>
            <summary><?= icono('editar') ?> Corregir datos</summary>
            <form method="post" action="<?= e(url('/alumnos/' . $id)) ?>" novalidate>
                <?= $vista->partial('alumnos/_regreso', compact('filtros')) ?>
                <div class="field<?= $errorNombre !== null ? ' field--error' : '' ?>">
                    <label for="editar-nombre">Nombre(s)</label>
                    <input type="text" id="editar-nombre" name="nombre" maxlength="80" required autocomplete="off"
                           value="<?= e($valorNombre) ?>" <?= $describe($errorNombre, 'editar-nombre') ?>>
                    <?php if ($errorNombre !== null) : ?>
                        <?= error_campo('editar-nombre-error', $errorNombre) ?>
                    <?php endif; ?>
                </div>
                <div class="field<?= $errorApellidos !== null ? ' field--error' : '' ?>">
                    <label for="editar-apellidos">Apellidos</label>
                    <input type="text" id="editar-apellidos" name="apellidos" maxlength="120" required
                           autocomplete="off" value="<?= e($valorApellidos) ?>"
                           <?= $describe($errorApellidos, 'editar-apellidos') ?>>
                    <?php if ($errorApellidos !== null) : ?>
                        <?= error_campo('editar-apellidos-error', $errorApellidos) ?>
                    <?php endif; ?>
                </div>
                <?= $vista->partial('alumnos/_horario', [
                    'id' => 'editar-horario',
                    'elegido' => $valorHorario,
                    'error' => $errorHorario,
                    'ayuda' => '',
                    'horarios' => $horarios,
                ]) ?>
                <button type="submit" class="btn btn--primary btn--small">
                    <?= icono('guardar') ?> Guardar cambios
                </button>
            </form>
        </details>

        <form method="post" action="<?= e(url('/alumnos/' . $id . '/estado')) ?>"
              data-confirmar="<?= e(($activo ? '¿Dar de baja a ' : '¿Reactivar a ') . $nombreCompleto . '?') ?>"
              data-confirmar-texto="<?= e($activo ? $detalleBaja : 'Volverá a recibir talleres, a mano o al azar.') ?>"
              data-confirmar-boton="<?= $activo ? 'Dar de baja' : 'Reactivar' ?>"
              data-confirmar-tipo="<?= $activo ? 'peligro' : 'primario' ?>">
            <?= $vista->partial('alumnos/_regreso', compact('filtros')) ?>
            <input type="hidden" name="activo" value="<?= $activo ? '0' : '1' ?>">
            <button type="submit" class="btn btn--link<?= $activo ? ' btn--link-danger' : '' ?>">
                <?= icono($activo ? 'prohibido' : 'encender') ?> <?= $activo ? 'Dar de baja' : 'Reactivar' ?>
            </button>
        </form>
    </div>
</section>
