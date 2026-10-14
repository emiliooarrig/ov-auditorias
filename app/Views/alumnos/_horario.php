<?php

/**
 * Lista desplegable del horario en el que un alumno puede auditar (alta, alta por lista y edición).
 *
 * @var string $id Id del campo.
 * @var string $elegido Id del horario elegido; vacío si no hay ninguno.
 * @var string|null $error
 * @var string $ayuda Texto de ayuda; vacío si no lleva.
 * @var list<array{id: int, nombre: string, hora_inicio: string, hora_fin: string}> $horarios
 */

$describe = match (true) {
    $error !== null => 'aria-invalid="true" aria-describedby="' . e($id) . '-error"',
    $ayuda !== '' => 'aria-describedby="' . e($id) . '-ayuda"',
    default => '',
};
?>
<div class="field<?= $error !== null ? ' field--error' : '' ?>">
    <label for="<?= e($id) ?>">Horario</label>
    <select id="<?= e($id) ?>" name="horario_id" required <?= $describe ?>>
        <option value="">Elige el horario en el que puede auditar</option>
        <?php foreach ($horarios as $h) : ?>
            <option value="<?= e($h['id']) ?>"<?= (string) $h['id'] === $elegido ? ' selected' : '' ?>>
                <?= e(horario_alumno($h['nombre'], $h['hora_inicio'], $h['hora_fin'])) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($error !== null) : ?>
        <?= error_campo($id . '-error', $error) ?>
    <?php elseif ($ayuda !== '') : ?>
        <span class="field__hint" id="<?= e($id) ?>-ayuda"><?= icono('info') ?> <?= e($ayuda) ?></span>
    <?php endif; ?>
</div>
