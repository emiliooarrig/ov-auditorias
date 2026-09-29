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
$claseCampo = static fn (string $campo): string => 'campo' . ($error($campo) !== null ? ' campo--error' : '');
$aria = static fn (string $campo): string => $error($campo) !== null
    ? 'aria-invalid="true" aria-describedby="' . $campo . '-error"'
    : '';
$mensaje = static fn (string $campo): string => $error($campo) !== null
    ? '<span class="error-campo" id="' . $campo . '-error">' . e($error($campo)) . '</span>'
    : '';
?>
<h1><?= $nuevo ? 'Nuevo taller' : 'Editar taller' ?></h1>

<form class="tarjeta formulario" method="post" action="<?= e($accion) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="<?= $claseCampo('nombre') ?>">
        <label for="nombre">Nombre del taller</label>
        <input type="text" id="nombre" name="nombre" value="<?= e($valor('nombre')) ?>" maxlength="150" required
               <?= $aria('nombre') ?>>
        <?= $mensaje('nombre') ?>
    </div>

    <div class="formulario__fila">
        <div class="<?= $claseCampo('carrera_id') ?>">
            <label for="carrera_id">Carrera</label>
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

        <div class="<?= $claseCampo('edificio_id') ?>">
            <label for="edificio_id">Edificio</label>
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
    </div>

    <div class="formulario__fila">
        <div class="<?= $claseCampo('fecha') ?>">
            <label for="fecha">Fecha</label>
            <input type="date" id="fecha" name="fecha" value="<?= e($valor('fecha')) ?>" required <?= $aria('fecha') ?>>
            <?= $mensaje('fecha') ?>
        </div>
        <div class="<?= $claseCampo('hora_inicio') ?>">
            <label for="hora_inicio">Hora de inicio</label>
            <input type="time" id="hora_inicio" name="hora_inicio" value="<?= e($valor('hora_inicio', 5)) ?>" required
                   <?= $aria('hora_inicio') ?>>
            <?= $mensaje('hora_inicio') ?>
        </div>
        <div class="<?= $claseCampo('hora_fin') ?>">
            <label for="hora_fin">Hora de fin</label>
            <input type="time" id="hora_fin" name="hora_fin" value="<?= e($valor('hora_fin', 5)) ?>" required
                   <?= $aria('hora_fin') ?>>
            <?= $mensaje('hora_fin') ?>
        </div>
    </div>

    <div class="formulario__acciones">
        <button type="submit" class="btn btn-primario"><?= $nuevo ? 'Crear taller' : 'Guardar cambios' ?></button>
        <a class="btn btn-secundario" href="<?= e($cancelar) ?>">Cancelar</a>
    </div>
</form>
