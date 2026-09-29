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
?>
<a class="volver" href="<?= e(url($esAdministrador ? '/' : '/mis-talleres')) ?>">
    <?= icono('izquierda') ?> <?= $esAdministrador ? 'Volver al panel' : 'Volver a mis talleres' ?>
</a>

<header class="ficha fila--<?= e(estado_clase($estado)) ?>">
    <?= app()->view()->partial('partials/hoja', [
        'fecha' => (string) $actividad['fecha'],
        'inicio' => (string) $actividad['hora_inicio'],
        'fin' => (string) $actividad['hora_fin'],
    ]) ?>
    <div>
        <h1><?= e($actividad['nombre']) ?></h1>
        <dl class="ficha__datos">
            <div><dt><?= icono('info') ?> Estado</dt><dd><?= estado_insignia($estado) ?></dd></div>
            <div>
                <dt><?= icono('edificio') ?> Edificio</dt>
                <dd><span class="placa placa--chica"><?= e($actividad['edificio']) ?></span></dd>
            </div>
            <div><dt><?= icono('carrera') ?> Carrera</dt><dd><?= e($actividad['carrera']) ?></dd></div>
        </dl>
    </div>
    <?php if ($esAdministrador) : ?>
        <div class="ficha__accion">
            <a class="btn btn-secundario" href="<?= e(url('/actividades/' . $id . '/editar')) ?>">
                <?= icono('editar') ?> Editar
            </a>
        </div>
    <?php endif; ?>

    <?php if ($estado === 'no_realizado') : ?>
        <div class="aviso aviso--error aviso--dentro">
            <?= icono('x-circulo') ?>
            <div>
                <strong>No se realizó.</strong> Motivo: <?= e($actividad['motivo_no_realizado']) ?>
                <?php if ($ultimo !== null) : ?>
                    <div class="texto-secundario">
                        Lo registró <?= e($ultimo['usuario']) ?> el <?= e(fecha_hora($ultimo['creado_en'])) ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</header>

<div class="detalle">
    <div class="detalle__principal">
        <section class="tarjeta detalle__auditores" aria-labelledby="t-auditores">
            <div class="titulo-acciones">
                <h2 id="t-auditores"><?= icono('usuarios') ?> Auditores asignados</h2>
                <?php if ($esAdministrador) : ?>
                    <a class="btn btn-secundario btn-chico" href="<?= e(url('/asignaciones')) ?>">
                        <?= icono('usuario-mas') ?> Asignar auditores
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($auditores === []) : ?>
                <p class="texto-secundario">Todavía no hay auditores asignados a este taller.</p>
            <?php else : ?>
                <ul class="lista-auditores">
                    <?php foreach ($auditores as $a) : ?>
                        <li>
                            <div class="persona">
                                <strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong>
                                <span class="persona__meta"><?= icono('correo') ?> <?= e($a['correo']) ?></span>
                                <?php if ($esAdministrador) : ?>
                                    <span class="persona__meta">
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
                                    <button type="submit" class="btn btn-enlace btn-enlace--peligro">
                                        <?= icono('usuario-menos') ?>
                                        Quitar<span class="solo-lector"> a <?= e($a['nombre']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($esAdministrador && !$programado) : ?>
                    <p class="texto-secundario aviso--dentro">
                        Las asignaciones quedan fijas como registro porque el taller ya no está programado.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="tarjeta detalle__historial" aria-labelledby="t-historial">
            <h2 id="t-historial"><?= icono('historial') ?> Historial de estados</h2>
            <?php if ($historial === []) : ?>
                <p class="texto-secundario">Sin movimientos.</p>
            <?php else : ?>
                <ol class="historial">
                    <?php foreach ($historial as $h) : ?>
                        <li class="fila--<?= e(estado_clase($h['estado_nuevo'])) ?>">
                            <div class="historial__cambio">
                                <?php if ($h['estado_anterior'] === null) : ?>
                                    Creado como <?= estado_insignia($h['estado_nuevo']) ?>
                                <?php else : ?>
                                    <?= estado_insignia($h['estado_anterior']) ?>
                                    <?= icono('flecha-derecha') ?><span class="solo-lector">cambió a</span>
                                    <?= estado_insignia($h['estado_nuevo']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="historial__meta">
                                <?= icono('usuario') ?> <?= e($h['usuario']) ?>, <?= e(fecha_hora($h['creado_en'])) ?>
                            </div>
                            <?php if ($h['motivo'] !== null) : ?>
                                <div class="motivo">Motivo: <?= e($h['motivo']) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <div class="detalle__lateral">
        <?php if ($puedeMarcarNoRealizado) : ?>
            <section class="tarjeta detalle__estado" aria-labelledby="t-estado">
                <h2 id="t-estado">
                    <?= icono('editar') ?>
                    <?= $esAdministrador ? 'Cambiar estado' : 'Registrar que no se realizó' ?>
                </h2>
                <form class="form-estado" method="post" novalidate
                      action="<?= e(url('/actividades/' . $id . '/estado')) ?>">
                    <?= csrf_field() ?>
                    <?php if ($esAdministrador) : ?>
                        <fieldset class="opciones-estado">
                            <legend>Nuevo estado</legend>
                            <?php
                            $opciones = ['programado', 'realizado', 'no_realizado'];
                            $elegido = old('estado', $estado === 'programado' ? 'realizado' : 'programado');
                            ?>
                            <?php foreach ($opciones as $valor) : ?>
                                <?php if ($valor === $estado) {
                                    continue;
                                } ?>
                                <label class="opcion-estado">
                                    <?php $marcado = $elegido === $valor ? ' checked' : ''; ?>
                                    <input type="radio" name="estado" value="<?= e($valor) ?>"<?= $marcado ?>>
                                    <?= estado_insignia($valor) ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php else : ?>
                        <input type="hidden" name="estado" value="no_realizado">
                        <p class="texto-secundario">
                            Si el taller no se llevó a cabo, escribe el motivo. Solo el administrador podrá revertirlo.
                        </p>
                    <?php endif; ?>

                    <div class="campo campo-motivo<?= $errorMotivo !== null ? ' campo--error' : '' ?>">
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
                            <span class="campo__ayuda" id="motivo-ayuda">
                                <?= icono('info') ?> Hasta 255 caracteres. Queda en el historial.
                            </span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primario btn-bloque">
                        <?= icono($esAdministrador ? 'guardar' : 'x-circulo') ?>
                        <?= $esAdministrador ? 'Guardar estado' : 'Marcar como no realizado' ?>
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($esAdministrador) : ?>
            <section class="tarjeta zona-peligro detalle__peligro" aria-labelledby="t-peligro">
                <h2 id="t-peligro"><?= icono('alerta') ?> Desactivar taller</h2>
                <p class="texto-secundario">
                    El taller dejará de aparecer en el panel. No se borra: su registro, asignaciones e historial
                    se conservan.
                </p>
                <form method="post" action="<?= e(url('/actividades/' . $id . '/desactivar')) ?>">
                    <?= csrf_field() ?>
                    <label class="casilla">
                        <input type="checkbox" name="confirmar" value="1" required>
                        Confirmo que quiero desactivar este taller.
                    </label>
                    <button type="submit" class="btn btn-peligro"><?= icono('prohibido') ?> Desactivar</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
