<?php

/**
 * Fecha y horario de un taller: "lun 5 oct 2026" y "10:00–12:00", cada uno con su ícono.
 *
 * @var string $fecha   AAAA-MM-DD
 * @var string $inicio  HH:MM:SS
 * @var string $fin     HH:MM:SS
 */
?>
<span class="when">
    <time class="when__day" datetime="<?= e(substr($fecha, 0, 10)) ?>">
        <?= icono('calendario') ?> <?= e(fecha_corta($fecha)) ?>
    </time>
    <span class="when__time"><?= icono('reloj') ?> <?= e(horario($inicio, $fin)) ?></span>
</span>
