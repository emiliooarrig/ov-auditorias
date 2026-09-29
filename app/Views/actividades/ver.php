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
<p>
    <a href="<?= e(url($esAdministrador ? '/' : '/mis-talleres')) ?>">
        ‹ <?= $esAdministrador ? 'Volver al panel' : 'Volver a mis talleres' ?>
    </a>
</p>

<div class="titulo-acciones">
    <h1><?= e($actividad['nombre']) ?></h1>
    <?php if ($esAdministrador) : ?>
        <a class="btn btn-secundario" href="<?= e(url('/actividades/' . $id . '/editar')) ?>">Editar</a>
    <?php endif; ?>
</div>

<section class="tarjeta">
    <dl class="datos">
        <div><dt>Carrera</dt><dd><?= e($actividad['carrera']) ?></dd></div>
        <div><dt>Edificio</dt><dd><?= e($actividad['edificio']) ?></dd></div>
        <div><dt>Fecha</dt><dd><?= e(fecha_corta((string) $actividad['fecha'])) ?></dd></div>
        <div>
            <dt>Horario</dt>
            <dd><?= e(horario((string) $actividad['hora_inicio'], (string) $actividad['hora_fin'])) ?></dd>
        </div>
        <div><dt>Estado</dt><dd><?= estado_insignia($estado) ?></dd></div>
    </dl>

    <?php if ($estado === 'no_realizado') : ?>
        <div class="aviso aviso--error aviso--dentro">
            <strong>No se realizó.</strong> Motivo: <?= e($actividad['motivo_no_realizado']) ?>
            <?php if ($ultimo !== null) : ?>
                <div class="texto-secundario">
                    Registrado por <?= e($ultimo['usuario']) ?> · <?= e(fecha_hora($ultimo['creado_en'])) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="tarjeta">
    <div class="titulo-acciones">
        <h2>Auditores asignados</h2>
        <?php if ($esAdministrador) : ?>
            <a class="btn btn-secundario" href="<?= e(url('/asignaciones')) ?>">Asignar auditores</a>
        <?php endif; ?>
    </div>
    <?php if ($auditores === []) : ?>
        <p class="texto-secundario">Sin auditores asignados.</p>
    <?php else : ?>
        <ul class="lista-auditores">
            <?php foreach ($auditores as $a) : ?>
                <li>
                    <div>
                        <strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong>
                        <span class="texto-secundario"><?= e($a['correo']) ?></span>
                        <?php if ($esAdministrador) : ?>
                            <div class="texto-secundario">
                                Asignado por <?= e($a['asignado_por']) ?> · <?= e(fecha_hora($a['asignado_en'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($esAdministrador && $programado) : ?>
                        <form method="post" action="<?= e(url('/asignaciones/' . $a['id'] . '/quitar')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="volver" value="detalle">
                            <input type="hidden" name="actividad_id" value="<?= e($id) ?>">
                            <button type="submit" class="btn btn-enlace">Quitar</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($esAdministrador && !$programado) : ?>
            <p class="texto-secundario">
                Las asignaciones quedan fijas como registro porque el taller ya no está programado.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($puedeMarcarNoRealizado) : ?>
    <section class="tarjeta">
        <h2><?= $esAdministrador ? 'Cambiar estado' : 'Registrar que no se realizó' ?></h2>
        <form method="post" action="<?= e(url('/actividades/' . $id . '/estado')) ?>" novalidate>
            <?= csrf_field() ?>
            <?php if ($esAdministrador) : ?>
                <fieldset class="opciones-estado">
                    <legend>Nuevo estado</legend>
                    <?php
                    $opciones = [
                        'programado' => 'Programado',
                        'realizado' => 'Realizado',
                        'no_realizado' => 'No realizado',
                    ];
                    $elegido = old('estado', $estado === 'programado' ? 'realizado' : 'programado');
                    ?>
                    <?php foreach ($opciones as $valor => $etiqueta) : ?>
                        <?php if ($valor === $estado) {
                            continue;
                        } ?>
                        <label class="casilla">
                            <?php $marcado = $elegido === $valor ? ' checked' : ''; ?>
                            <input type="radio" name="estado" value="<?= e($valor) ?>"<?= $marcado ?>>
                            <?= e($etiqueta) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php else : ?>
                <input type="hidden" name="estado" value="no_realizado">
                <p class="texto-secundario">
                    Si el taller no se llevó a cabo, escribe el motivo. Solo el administrador podrá revertirlo.
                </p>
            <?php endif; ?>

            <div class="campo<?= $errorMotivo !== null ? ' campo--error' : '' ?>">
                <label for="motivo">
                    Motivo<?= $esAdministrador ? ' (obligatorio si no se realizó)' : '' ?>
                </label>
                <textarea id="motivo" name="motivo" rows="3" maxlength="255"
                          <?= $esAdministrador ? '' : 'required' ?>
                          <?= $errorMotivo !== null ? 'aria-invalid="true" aria-describedby="motivo-error"' : '' ?>
                ><?= e(old('motivo')) ?></textarea>
                <?php if ($errorMotivo !== null) : ?>
                    <span class="error-campo" id="motivo-error"><?= e($errorMotivo) ?></span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primario">
                <?= $esAdministrador ? 'Guardar estado' : 'Marcar como no realizado' ?>
            </button>
        </form>
    </section>
<?php endif; ?>

<section class="tarjeta">
    <h2>Historial de estados</h2>
    <?php if ($historial === []) : ?>
        <p class="texto-secundario">Sin movimientos.</p>
    <?php else : ?>
        <ol class="historial">
            <?php foreach ($historial as $h) : ?>
                <li>
                    <?php if ($h['estado_anterior'] === null) : ?>
                        Creado como <?= estado_insignia($h['estado_nuevo']) ?>
                    <?php else : ?>
                        <?= estado_insignia($h['estado_anterior']) ?> <span aria-label="a">→</span>
                        <?= estado_insignia($h['estado_nuevo']) ?>
                    <?php endif; ?>
                    <div class="texto-secundario">
                        <?= e($h['usuario']) ?> · <?= e(fecha_hora($h['creado_en'])) ?>
                    </div>
                    <?php if ($h['motivo'] !== null) : ?>
                        <div class="motivo">Motivo: <?= e($h['motivo']) ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<?php if ($esAdministrador) : ?>
    <section class="tarjeta zona-peligro">
        <h2>Desactivar taller</h2>
        <p class="texto-secundario">
            El taller dejará de aparecer en el panel. No se borra: su registro, asignaciones e historial se conservan.
        </p>
        <form method="post" action="<?= e(url('/actividades/' . $id . '/desactivar')) ?>">
            <?= csrf_field() ?>
            <label class="casilla">
                <input type="checkbox" name="confirmar" value="1" required>
                Confirmo que quiero desactivar este taller.
            </label>
            <button type="submit" class="btn btn-peligro">Desactivar</button>
        </form>
    </section>
<?php endif; ?>
