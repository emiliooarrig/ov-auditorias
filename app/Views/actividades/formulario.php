<?php

/**
 * Alta y edición de un taller (RF-06).
 *
 * @var array<string, mixed>|null              $actividad
 * @var list<array{id: int, nombre: string}>   $carreras
 * @var list<array{id: int, numero: int}>      $edificios
 */

$nuevo = $actividad === null;
$accion = $nuevo ? url('/actividades') : url('/actividades/' . $actividad['id']);
$cancelar = $nuevo ? url('/') : url('/actividades/' . $actividad['id']);

// Tras un error se muestra lo enviado; si no, los datos guardados.
$valor = static function (string $campo, int $largo = 0) use ($actividad): string {
    $enviado = old($campo, "\0");
    if ($enviado !== "\0") {
        return $enviado;
    }
    $guardado = (string) ($actividad[$campo] ?? '');

    return $largo > 0 ? substr($guardado, 0, $largo) : $guardado;
};
$error = static fn (string $campo): ?string => error_de($campo);
$claseCampo = static fn (string $campo): string => 'field' . ($error($campo) !== null ? ' field--error' : '');
$aria = static fn (string $campo): string => $error($campo) !== null
    ? 'aria-invalid="true" aria-describedby="' . $campo . '-error"'
    : '';
$mensaje = static fn (string $campo): string => $error($campo) !== null
    ? error_campo($campo . '-error', (string) $error($campo))
    : '';
?>
<a class="back-link" href="<?= e($cancelar) ?>">
    <?= icono('izquierda') ?> <?= $nuevo ? 'Volver al panel' : 'Volver al taller' ?>
</a>
<div class="page-header">
    <div>
        <h1><?= $nuevo ? 'Nuevo taller' : 'Editar taller' ?></h1>
        <p>Todos los campos son obligatorios.</p>
    </div>
</div>

<form class="card form-narrow" method="post" action="<?= e($accion) ?>" novalidate>
    <?= csrf_field() ?>

    <fieldset class="fieldset">
        <legend class="fieldset__legend"><?= icono('calendario') ?> Taller</legend>
        <div class="<?= $claseCampo('nombre') ?>">
            <label for="nombre"><?= icono('editar') ?> Nombre del taller</label>
            <input type="text" id="nombre" name="nombre" value="<?= e($valor('nombre')) ?>" maxlength="150" required
                   <?= $aria('nombre') ?>>
            <?= $mensaje('nombre') ?>
        </div>

        <div class="<?= $claseCampo('carrera_id') ?>">
            <label for="carrera_id"><?= icono('carrera') ?> Carrera</label>
            <select id="carrera_id" name="carrera_id" required <?= $aria('carrera_id') ?>>
                <option value="">Elige una carrera</option>
                <?php foreach ($carreras as $c) : ?>
                    <?php $sel = $valor('carrera_id') === (string) $c['id'] ? ' selected' : ''; ?>
                    <option value="<?= e($c['id']) ?>"<?= $sel ?>>
                        <?= e($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $mensaje('carrera_id') ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend"><?= icono('reloj') ?> Cuándo y dónde</legend>
        <div class="form-grid">
            <div class="<?= $claseCampo('edificio_id') ?>">
                <label for="edificio_id"><?= icono('edificio') ?> Edificio</label>
                <select id="edificio_id" name="edificio_id" required <?= $aria('edificio_id') ?>>
                    <option value="">Elige un edificio</option>
                    <?php foreach ($edificios as $ed) : ?>
                        <?php $sel = $valor('edificio_id') === (string) $ed['id'] ? ' selected' : ''; ?>
                        <option value="<?= e($ed['id']) ?>"<?= $sel ?>>
                            Edificio <?= e($ed['numero']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $mensaje('edificio_id') ?>
            </div>
            <div class="<?= $claseCampo('fecha') ?>">
                <label for="fecha"><?= icono('calendario') ?> Fecha</label>
                <input type="date" id="fecha" name="fecha" value="<?= e($valor('fecha')) ?>" required
                       <?= $aria('fecha') ?>>
                <?= $mensaje('fecha') ?>
            </div>
            <div class="<?= $claseCampo('hora_inicio') ?>">
                <label for="hora_inicio"><?= icono('reloj') ?> Hora de inicio</label>
                <input type="time" id="hora_inicio" name="hora_inicio" required
                       value="<?= e($valor('hora_inicio', 5)) ?>" <?= $aria('hora_inicio') ?>>
                <?= $mensaje('hora_inicio') ?>
            </div>
            <div class="<?= $claseCampo('hora_fin') ?>">
                <label for="hora_fin"><?= icono('reloj') ?> Hora de fin</label>
                <input type="time" id="hora_fin" name="hora_fin" required
                       value="<?= e($valor('hora_fin', 5)) ?>" <?= $aria('hora_fin') ?>>
                <?= $mensaje('hora_fin') ?>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary">
            <?= icono($nuevo ? 'mas' : 'guardar') ?> <?= $nuevo ? 'Crear taller' : 'Guardar cambios' ?>
        </button>
        <a class="btn btn--secondary" href="<?= e($cancelar) ?>"><?= icono('cerrar') ?> Cancelar</a>
    </div>
</form>
