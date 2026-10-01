<?php

/**
 * Fecha y horario de un taller: "lun 5 oct 2026" y "Taller 3, 12:00–13:00", cada uno con su ícono.
 * El grupo (Taller 1 … 6) es el que fija el horario; se omite si no se pasa.
 *
 * @var string      $fecha   AAAA-MM-DD
 * @var string      $inicio  HH:MM:SS
 * @var string      $fin     HH:MM:SS
 * @var string|null $grupo   Nombre del grupo, p. ej. "Taller 3"
 */

$grupo ??= null;
$horario = ($grupo !== null && $grupo !== '' ? $grupo . ', ' : '') . horario($inicio, $fin);
?>
<span class="when">
    <time class="when__day" datetime="<?= e(substr($fecha, 0, 10)) ?>">
        <?= icono('calendario') ?> <?= e(fecha_corta($fecha)) ?>
    </time>
    <span class="when__time"><?= icono('reloj') ?> <?= e($horario) ?></span>
</span>
