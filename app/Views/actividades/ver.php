<?php

/**
 * Detalle de un taller (RF-07, RF-09): datos, auditores asignados, historial y cambio de estado.
 * El administrador ve todas las acciones; el auditor asignado solo puede marcarlo no realizado.
 *
 * @var array<string, mixed> $actividad
 * @var list<array{id: int, usuario_id: int, nombre: string, apellidos: string, correo: string,
 *     asignado_en: string, asignado_por: string}> $auditores
 * @var list<array{estado_anterior: ?string, estado_nuevo: string, motivo: ?string, creado_en: string,
 *     usuario: string, correo: string}> $historial
 * @var bool $esAdministrador
 * @var bool $puedeMarcarNoRealizado
 */

$id = (int) $actividad['id'];
$estado = (string) $actividad['estado'];
$programado = $estado === 'programado';
$errorMotivo = error_de('motivo');
$ultimo = $historial[0] ?? null;
$detalleDesactivar = 'Dejará de aparecer en el panel. Su registro, asignaciones e historial se conservan.';
?>
<a class="back-link" href="<?= e(url($esAdministrador ? '/' : '/mis-talleres')) ?>">
    <?= icono('izquierda') ?> <?= $esAdministrador ? 'Volver al panel' : 'Volver a mis talleres' ?>
</a>

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
            <div>
                <dt><?= icono('edificio') ?> Edificio</dt>
                <dd><?= e(edificio_etiqueta($actividad['edificio'], $actividad['edificio_nombre'], false)) ?></dd>
            </div>
            <div><dt><?= icono('carrera') ?> Carrera</dt><dd><?= e($actividad['carrera']) ?></dd></div>
        </dl>
    </div>
    <?php if ($esAdministrador) : ?>
        <a class="btn btn--secondary" href="<?= e(url('/actividades/' . $id . '/editar')) ?>">
            <?= icono('editar') ?> Editar
        </a>
    <?php endif; ?>

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
                <h2 class="card__title" id="t-auditores"><?= icono('usuarios') ?> Auditores asignados</h2>
                <?php if ($esAdministrador) : ?>
                    <a class="btn btn--secondary btn--small" href="<?= e(url('/asignaciones')) ?>">
                        <?= icono('usuario-mas') ?> Asignar auditores
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($auditores === []) : ?>
                <p class="muted">Todavía no hay auditores asignados a este taller.</p>
            <?php else : ?>
                <ul class="people">
                    <?php foreach ($auditores as $a) : ?>
                        <li>
                            <div class="person">
                                <strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong>
                                <span class="person__meta"><?= icono('correo') ?> <?= e($a['correo']) ?></span>
                                <?php if ($esAdministrador) : ?>
                                    <span class="person__meta">
                                        <?= icono('asignar') ?> Lo asignó <?= e($a['asignado_por']) ?>
                                        el <?= e(fecha_hora($a['asignado_en'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($esAdministrador && $programado) : ?>
                                <form method="post" action="<?= e(url('/asignaciones/' . $a['id'] . '/quitar')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="volver" value="detalle">
                                    <input type="hidden" name="actividad_id" value="<?= e($id) ?>">
                                    <button type="submit" class="btn btn--link btn--link-danger">
                                        <?= icono('usuario-menos') ?>
                                        Quitar<span class="sr-only"> a <?= e($a['nombre']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($esAdministrador && !$programado) : ?>
                    <p class="muted field__hint">
                        <?= icono('info') ?>
                        Las asignaciones quedan fijas como registro porque el taller ya no está programado.
                    </p>
                <?php endif; ?>
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
        <?php if ($puedeMarcarNoRealizado) : ?>
            <section class="card card--warm" aria-labelledby="t-estado">
                <h2 class="card__title" id="t-estado">
                    <?= icono('editar') ?>
                    <?= $esAdministrador ? 'Cambiar estado' : 'Registrar que no se realizó' ?>
                </h2>
                <form method="post" novalidate
                      action="<?= e(url('/actividades/' . $id . '/estado')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($esAdministrador) : ?>
                        <fieldset class="choices">
                            <legend>Nuevo estado</legend>
                            <?php
                            $opciones = ['programado', 'realizado', 'no_realizado'];
                            $elegido = old('estado', $estado === 'programado' ? 'realizado' : 'programado');
                            ?>
                            <?php foreach ($opciones as $valor) : ?>
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
                    <?php else : ?>
                        <input type="hidden" name="estado" value="no_realizado">
                        <p class="muted">
                            Si el taller no se llevó a cabo, escribe el motivo. Solo el administrador podrá revertirlo.
                        </p>
                    <?php endif; ?>

                    <div class="field<?= $errorMotivo !== null ? ' field--error' : '' ?>">
                        <label for="motivo">
                            <?= $esAdministrador ? 'Motivo por el que no se realizó' : 'Motivo' ?>
                        </label>
                        <textarea id="motivo" name="motivo" rows="3" maxlength="255"
                                  <?= $esAdministrador ? '' : 'required' ?>
                                  <?= $errorMotivo !== null
                                      ? 'aria-invalid="true" aria-describedby="motivo-error"'
                                      : 'aria-describedby="motivo-ayuda"' ?>
                        ><?= e(old('motivo')) ?></textarea>
                        <?php if ($errorMotivo !== null) : ?>
                            <?= error_campo('motivo-error', $errorMotivo) ?>
                        <?php else : ?>
                            <span class="field__hint" id="motivo-ayuda">
                                <?= icono('info') ?> Hasta 255 caracteres. Queda en el historial.
                            </span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn--primary btn--block">
                        <?= icono($esAdministrador ? 'guardar' : 'x-circulo') ?>
                        <?= $esAdministrador ? 'Guardar estado' : 'Marcar como no realizado' ?>
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($esAdministrador) : ?>
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
        <?php endif; ?>
    </div>
</div>
