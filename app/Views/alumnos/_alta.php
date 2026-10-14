<?php

/**
 * Panel de alta de alumnos: uno por uno, o pegando una lista (un alumno por renglón). Cada alumno lleva
 * su horario; los de una lista comparten el que se elija.
 *
 * @var array{texto: string, estado: string, talleres: string} $filtros
 * @var int $listaMax
 * @var list<array{id: int, nombre: string, hora_inicio: string, hora_fin: string}> $horarios
 */

$vista = app()->view();
$errorNombre = error_de('nombre');
$errorApellidos = error_de('apellidos');
$errorHorario = error_de('horario_id');
$errorLista = error_de('lista');
$errorListaHorario = error_de('lista_horario_id');
$renglonesConError = array_filter(explode("\n", (string) error_de('lista_renglones')));
$orden = old('orden', 'apellidos');
$descripcion = static fn (?string $error, string $id, string $ayuda = ''): string => $error !== null
    ? 'aria-invalid="true" aria-describedby="' . e($id) . '-error"'
    : ($ayuda !== '' ? 'aria-describedby="' . e($ayuda) . '"' : '');
$selectorHorario = static fn (string $id, string $elegido, ?string $error, string $ayuda): string => $vista->partial(
    'alumnos/_horario',
    ['id' => $id, 'elegido' => $elegido, 'error' => $error, 'ayuda' => $ayuda, 'horarios' => $horarios]
);
?>
<section class="card panel" id="alta" aria-labelledby="t-alta">
    <h2 class="card__title" id="t-alta" tabindex="-1" data-panel-titulo><?= icono('usuario-mas') ?> Agregar alumnos</h2>
    <p class="muted">
        Se pide su nombre, sus apellidos y el horario en el que puede auditar: los alumnos no tienen cuenta
        ni entran al sistema.
    </p>

    <form class="fieldset" method="post" action="<?= e(url('/alumnos')) ?>" novalidate>
        <?= $vista->partial('alumnos/_regreso', compact('filtros')) ?>
        <h3 class="fieldset__legend">Uno por uno</h3>
        <div class="form-grid">
            <div class="field<?= $errorNombre !== null ? ' field--error' : '' ?>">
                <label for="alta-nombre">Nombre(s)</label>
                <input type="text" id="alta-nombre" name="nombre" value="<?= e(old('nombre')) ?>" maxlength="80"
                       autocomplete="off" required <?= $descripcion($errorNombre, 'alta-nombre') ?>>
                <?php if ($errorNombre !== null) : ?>
                    <?= error_campo('alta-nombre-error', $errorNombre) ?>
                <?php endif; ?>
            </div>
            <div class="field<?= $errorApellidos !== null ? ' field--error' : '' ?>">
                <label for="alta-apellidos">Apellidos</label>
                <input type="text" id="alta-apellidos" name="apellidos" value="<?= e(old('apellidos')) ?>"
                       maxlength="120" autocomplete="off" required
                       <?= $descripcion($errorApellidos, 'alta-apellidos') ?>>
                <?php if ($errorApellidos !== null) : ?>
                    <?= error_campo('alta-apellidos-error', $errorApellidos) ?>
                <?php endif; ?>
            </div>
        </div>
        <?= $selectorHorario('alta-horario', old('horario_id'), $errorHorario, '') ?>
        <button type="submit" class="btn btn--primary"><?= icono('usuario-mas') ?> Agregar alumno</button>
    </form>

    <form class="fieldset" method="post" action="<?= e(url('/alumnos/lista')) ?>" novalidate>
        <?= $vista->partial('alumnos/_regreso', compact('filtros')) ?>
        <h3 class="fieldset__legend"><?= icono('lista') ?> Pegar una lista</h3>
        <fieldset class="choices">
            <legend>¿En qué orden vienen?</legend>
            <label class="choice">
                <input type="radio" name="orden" value="apellidos"<?= $orden !== 'nombre' ? ' checked' : '' ?>>
                <span>Apellidos y luego nombre <span class="muted">— López Ruiz, Ana</span></span>
            </label>
            <label class="choice">
                <input type="radio" name="orden" value="nombre"<?= $orden === 'nombre' ? ' checked' : '' ?>>
                <span>Nombre y luego apellidos <span class="muted">— Ana, López Ruiz</span></span>
            </label>
        </fieldset>
        <div class="field<?= $errorLista !== null ? ' field--error' : '' ?>">
            <label for="alta-lista">Un alumno por renglón</label>
            <textarea id="alta-lista" name="lista" rows="8" spellcheck="false"
                      placeholder="López Ruiz, Ana&#10;Pérez Gil, Luis"
                      <?= $descripcion($errorLista, 'alta-lista', 'alta-lista-ayuda') ?>
            ><?= e(old('lista')) ?></textarea>
            <?php if ($errorLista !== null) : ?>
                <div class="field__error-group" id="alta-lista-error">
                    <?= error_campo('alta-lista-resumen', $errorLista) ?>
                    <?php if ($renglonesConError !== []) : ?>
                        <ul class="error-list">
                            <?php foreach ($renglonesConError as $renglon) : ?>
                                <li><?= e($renglon) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <span class="field__hint" id="alta-lista-ayuda">
                    <?= icono('info') ?>
                    Separa el nombre de los apellidos con una coma, o copia las columnas directo de Excel.
                    Hasta <?= e($listaMax) ?> alumnos a la vez; los repetidos se omiten.
                </span>
            <?php endif; ?>
        </div>
        <?= $selectorHorario(
            'alta-lista-horario',
            old('lista_horario_id'),
            $errorListaHorario,
            'Todos los alumnos de la lista quedan con este horario. '
                . 'Si tienen horarios distintos, pega una lista por horario.'
        ) ?>
        <button type="submit" class="btn btn--primary"><?= icono('lista') ?> Agregar lista</button>
    </form>
</section>
