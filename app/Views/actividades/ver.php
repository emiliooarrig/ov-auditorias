<?php

/**
 * Detalle de un taller (RF-09). En la fase 4 se agregan los auditores asignados y el formulario de estado.
 *
 * @var array<string, mixed> $actividad
 * @var list<array{estado_anterior: ?string, estado_nuevo: string, motivo: ?string, creado_en: string,
 *     usuario: string, correo: string}> $historial
 */

$id = (int) $actividad['id'];
?>
<p><a href="<?= e(url('/')) ?>">‹ Volver al panel</a></p>

<div class="titulo-acciones">
    <h1><?= e($actividad['nombre']) ?></h1>
    <a class="btn btn-secundario" href="<?= e(url('/actividades/' . $id . '/editar')) ?>">Editar</a>
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
        <div>
            <dt>Estado</dt>
            <dd>
                <?= estado_insignia((string) $actividad['estado']) ?>
                <?php if ($actividad['motivo_no_realizado'] !== null) : ?>
                    <div class="motivo"><?= e($actividad['motivo_no_realizado']) ?></div>
                <?php endif; ?>
            </dd>
        </div>
    </dl>
</section>

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
