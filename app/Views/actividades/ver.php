<?php

/**
 * Detalle de un taller (RF-07, RF-09): datos, alumnos que lo auditan, historial y cambio de estado.
 * Mientras está programado se le pueden agregar o quitar alumnos.
 *
 * @var array<string, mixed> $actividad
 * @var list<array{id: int, alumno_id: int, nombre: string, apellidos: string, activo: int, origen: string,
 *     asignado_en: string, asignado_por: string, horario: ?string, horario_inicio: ?string,
 *     horario_fin: ?string, en_horario: ?int}> $auditores
 * @var list<array{id: int, nombre: string, apellidos: string, horario: string, talleres: int}> $disponibles
 * @var list<array{estado_anterior: ?string, estado_nuevo: string, motivo: ?string, creado_en: string,
 *     usuario: string, correo: string}> $historial
 */

$id = (int) $actividad['id'];
$estado = (string) $actividad['estado'];
$programado = $estado === 'programado';
$errorMotivo = error_de('motivo');
$ultimo = $historial[0] ?? null;
$detalleDesactivar = 'Dejará de aparecer en el panel. Su registro, asignaciones e historial se conservan.';
?>
<a class="back-link" href="<?= e(url('/')) ?>"><?= icono('izquierda') ?> Volver al panel</a>

<header class="card hero">
    <div>
        <h1><?= e($actividad['nombre']) ?></h1>
        <dl class="facts">
            <div><dt><?= icono('info') ?> Estado</dt><dd><?= estado_insignia($estado) ?></dd></div>
            <div>
                <dt><?= icono('calendario') ?> Fecha</dt>
                <dd class="nums">
                    <time datetime="<?= e(substr((string) $actividad['fecha'], 0, 10)) ?>">
                        <?= e(fecha_corta((string) $actividad['fecha'])) ?></time>,
                    <?= e(horario((string) $actividad['hora_inicio'], (string) $actividad['hora_fin'])) ?>
                </dd>
            </div>
            <div><dt><?= icono('mis-talleres') ?> Grupo</dt><dd><?= e($actividad['grupo_nombre']) ?></dd></div>
            <div>
                <dt><?= icono('edificio') ?> Edificio</dt>
                <dd><?= e(edificio_etiqueta($actividad['edificio'], $actividad['edificio_nombre'], false)) ?></dd>
            </div>
            <div><dt><?= icono('carrera') ?> Carrera</dt><dd><?= e($actividad['carrera']) ?></dd></div>
        </dl>
    </div>
    <a class="btn btn--secondary" href="<?= e(url('/actividades/' . $id . '/editar')) ?>">
        <?= icono('editar') ?> Editar
    </a>

    <?php if ($estado === 'no_realizado') : ?>
        <div class="alert alert--error alert--inline">
            <?= icono('x-circulo') ?>
            <div>
                <strong>No se realizó.</strong> Motivo: <?= e($actividad['motivo_no_realizado']) ?>
                <?php if ($ultimo !== null) : ?>
                    <div class="muted">
                        Lo registró <?= e($ultimo['usuario']) ?> el <?= e(fecha_hora($ultimo['creado_en'])) ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</header>

<div class="layout-detail">
    <div>
        <section class="card" aria-labelledby="t-auditores">
            <div class="card__header">
                <h2 class="card__title" id="t-auditores">
                    <?= icono('usuarios') ?> Alumnos auditores
                    <span class="count-pill nums"><?= e(count($auditores)) ?></span>
                </h2>
            </div>
            <?php if ($auditores === []) : ?>
                <p class="muted with-icon">
                    <?= icono('usuario-menos') ?>
                    <?= $programado
                        ? 'Todavía nadie audita este taller. Agrega un alumno abajo o usa la asignación al azar.'
                        : 'Nadie quedó registrado como auditor de este taller.' ?>
                </p>
            <?php else : ?>
                <ul class="people">
                    <?php foreach ($auditores as $a) : ?>
                        <li>
                            <div class="person">
                                <a href="<?= e(url('/alumnos/' . $a['alumno_id'])) ?>">
                                    <strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong>
                                </a>
                                <span class="person__meta">
                                    <?= icono($a['origen'] === 'aleatoria' ? 'aleatorio' : 'asignar') ?>
                                    <?= $a['origen'] === 'aleatoria' ? 'Al azar' : 'A mano' ?>,
                                    por <?= e($a['asignado_por']) ?> el <?= e(fecha_hora($a['asignado_en'])) ?>
                                </span>
                                <span class="person__meta nums">
                                    <?= icono('reloj') ?>
                                    <?= e(horario_alumno($a['horario'], $a['horario_inicio'], $a['horario_fin'])) ?>
                                </span>
                                <?php if ((int) $a['activo'] !== 1) : ?>
                                    <span>
                                        <span class="badge badge--inactive"><?= icono('prohibido') ?> Baja</span>
                                    </span>
                                <?php elseif ($programado && $a['en_horario'] === 0) : ?>
                                    <span>
                                        <span class="badge badge--warning">
                                            <?= icono('alerta') ?> Fuera de su horario
                                        </span>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($programado) : ?>
                                <form method="post" action="<?= e(url('/asignaciones/' . $a['id'] . '/quitar')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="volver" value="taller">
                                    <input type="hidden" name="actividad_id" value="<?= e($id) ?>">
                                    <button type="submit" class="btn btn--link btn--link-danger">
                                        <?= icono('usuario-menos') ?>
                                        Quitar<span class="sr-only">
                                            a <?= e($a['nombre'] . ' ' . $a['apellidos']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!$programado) : ?>
                <?php if ($auditores !== []) : ?>
                    <p class="muted field__hint">
                        <?= icono('info') ?>
                        Las asignaciones quedan fijas como registro porque el taller ya no está programado.
                    </p>
                <?php endif; ?>
            <?php elseif ($disponibles === []) : ?>
                <p class="muted field__hint">
                    <?= icono('info') ?>
                    No hay alumnos activos con horario que cubra este taller y libres a esta hora.
                </p>
            <?php else : ?>
                <form class="inline-form" method="post" action="<?= e(url('/asignaciones')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="volver" value="taller">
                    <input type="hidden" name="actividad_id" value="<?= e($id) ?>">
                    <div class="inline-form__row">
                        <div class="field">
                            <label for="agregar-alumno"><?= icono('usuario-mas') ?> Agregar alumno</label>
                            <select id="agregar-alumno" name="alumno_id" required aria-describedby="agregar-ayuda">
                                <option value="">Elige un alumno</option>
                                <?php foreach ($disponibles as $d) : ?>
                                    <option value="<?= e($d['id']) ?>">
                                        <?= e($d['apellidos'] . ', ' . $d['nombre'] . ' — ' . $d['horario'] . ' — '
                                            . ((int) $d['talleres'] === 0
                                                ? 'sin talleres'
                                                : cantidad((int) $d['talleres'], 'taller', 'talleres'))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn--primary"><?= icono('usuario-mas') ?> Agregar</button>
                    </div>
                    <span class="field__hint" id="agregar-ayuda">
                        <?= icono('info') ?> Solo aparecen los alumnos cuyo horario cubre este taller y que no
                        tienen otro taller a esta hora.
                    </span>
                </form>
            <?php endif; ?>
        </section>

        <section class="card" aria-labelledby="t-historial">
            <h2 class="card__title" id="t-historial"><?= icono('historial') ?> Historial de estados</h2>
            <?php if ($historial === []) : ?>
                <p class="muted">Sin movimientos.</p>
            <?php else : ?>
                <ol class="timeline">
                    <?php foreach ($historial as $h) : ?>
                        <li class="timeline__item">
                            <div class="timeline__change">
                                <?php if ($h['estado_anterior'] === null) : ?>
                                    Creado como <?= estado_insignia($h['estado_nuevo']) ?>
                                <?php else : ?>
                                    <?= estado_insignia($h['estado_anterior']) ?>
                                    <?= icono('flecha-derecha') ?><span class="sr-only">cambió a</span>
                                    <?= estado_insignia($h['estado_nuevo']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="timeline__meta">
                                <?= icono('usuario') ?> <?= e($h['usuario']) ?>, <?= e(fecha_hora($h['creado_en'])) ?>
                            </div>
                            <?php if ($h['motivo'] !== null) : ?>
                                <div class="reason">Motivo: <?= e($h['motivo']) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="card card--warm" aria-labelledby="t-estado">
            <h2 class="card__title" id="t-estado"><?= icono('editar') ?> Cambiar estado</h2>
            <form method="post" novalidate action="<?= e(url('/actividades/' . $id . '/estado')) ?>">
                <?= csrf_field() ?>
                <fieldset class="choices">
                    <legend>Nuevo estado</legend>
                    <?php $elegido = old('estado', $estado === 'programado' ? 'realizado' : 'programado'); ?>
                    <?php foreach (['programado', 'realizado', 'no_realizado'] as $valor) : ?>
                        <?php if ($valor === $estado) {
                            continue;
                        } ?>
                        <label class="choice">
                            <?php $marcado = $elegido === $valor ? ' checked' : ''; ?>
                            <input type="radio" name="estado" value="<?= e($valor) ?>"<?= $marcado ?>>
                            <?= estado_insignia($valor) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <div class="field<?= $errorMotivo !== null ? ' field--error' : '' ?>">
                    <label for="motivo">Motivo por el que no se realizó</label>
                    <textarea id="motivo" name="motivo" rows="3" maxlength="255"
                              <?= $errorMotivo !== null
                                  ? 'aria-invalid="true" aria-describedby="motivo-error"'
                                  : 'aria-describedby="motivo-ayuda"' ?>
                    ><?= e(old('motivo')) ?></textarea>
                    <?php if ($errorMotivo !== null) : ?>
                        <?= error_campo('motivo-error', $errorMotivo) ?>
                    <?php else : ?>
                        <span class="field__hint" id="motivo-ayuda">
                            <?= icono('info') ?> Solo si no se realizó. Hasta 255 caracteres; queda en el historial.
                        </span>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn--primary btn--block">
                    <?= icono('guardar') ?> Guardar estado
                </button>
            </form>
        </section>

        <section class="card card--danger" aria-labelledby="t-peligro">
            <h2 class="card__title" id="t-peligro"><?= icono('alerta') ?> Desactivar taller</h2>
            <p class="muted">
                El taller dejará de aparecer en el panel. No se borra: su registro, asignaciones e historial
                se conservan.
            </p>
            <form method="post" action="<?= e(url('/actividades/' . $id . '/desactivar')) ?>"
                  data-confirmar="<?= e('¿Desactivar «' . $actividad['nombre'] . '»?') ?>"
                  data-confirmar-texto="<?= e($detalleDesactivar) ?>"
                  data-confirmar-boton="Desactivar"
                  data-confirmar-tipo="peligro">
                <?= csrf_field() ?>
                <?php /* Sin JavaScript se confirma con la casilla; con él, la marca el diálogo. */ ?>
                <label class="check" data-confirmacion-manual>
                    <input type="checkbox" name="confirmar" value="1" required>
                    Confirmo que quiero desactivar este taller.
                </label>
                <button type="submit" class="btn btn--danger"><?= icono('prohibido') ?> Desactivar</button>
            </form>
        </section>
    </div>
</div>
