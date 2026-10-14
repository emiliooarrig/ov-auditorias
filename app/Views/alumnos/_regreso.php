<?php

/**
 * Token CSRF y filtros de la lista de alumnos para los formularios del panel: así, tras guardar, se
 * vuelve a la lista como estaba. app.js mantiene estos valores al día mientras se filtra.
 *
 * @var array{texto: string, estado: string, talleres: string} $filtros
 */
?>
<?= csrf_field() ?>
<?php foreach ($filtros as $clave => $valor) : ?>
    <input type="hidden" name="<?= e($clave) ?>" value="<?= e($valor) ?>" data-regreso="<?= e($clave) ?>">
<?php endforeach; ?>
