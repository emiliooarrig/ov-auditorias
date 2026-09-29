<?php

/**
 * Hoja de calendario de un taller: día de la semana, número, mes y horario.
 * Los lectores de pantalla oyen la fecha completa; las partes visuales se ocultan para no repetirla.
 *
 * @var string $fecha   AAAA-MM-DD
 * @var string $inicio  HH:MM:SS
 * @var string $fin     HH:MM:SS
 */

$partes = fecha_partes($fecha);
?>
<time class="hoja" datetime="<?= e(substr($fecha, 0, 10)) ?>">
    <span class="solo-lector"><?= e(fecha_corta($fecha)) ?></span>
    <span class="hoja__dia" aria-hidden="true"><?= e($partes['dia']) ?></span>
    <span class="hoja__numero" aria-hidden="true"><?= e($partes['numero']) ?></span>
    <span class="hoja__mes" aria-hidden="true"><?= e($partes['mes']) ?></span>
    <span class="hoja__hora"><?= e(horario($inicio, $fin)) ?></span>
</time>
