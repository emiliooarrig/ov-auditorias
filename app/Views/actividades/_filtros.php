<?php

/**
 * Barra de filtros por nombre, carrera y edificio (RF-08). Se envía por GET y conserva los valores.
 * Con JavaScript filtra en tiempo real: app.js pide la misma URL y reemplaza el bloque [data-resultados].
 *
 * @var string                                  $ruta
 * @var array{nombre: string, carrera: ?int, edificio: ?int} $filtros
 * @var list<array{id: int, nombre: string}>    $carreras
 * @var list<array{id: int, numero: int, nombre: string}> $edificios
 * @var bool                                    $hayFiltros
 * @var array<string, int|string>|null          $ocultos  Parámetros que se conservan al filtrar (p. ej. usuario).
 */

$ocultos ??= [];
?>
<form class="card filters" method="get" action="<?= e(url($ruta)) ?>" role="search" aria-label="Filtrar talleres"
      data-filtro-vivo>
    <?php foreach ($ocultos as $nombreOculto => $valorOculto) : ?>
        <input type="hidden" name="<?= e($nombreOculto) ?>" value="<?= e($valorOculto) ?>">
    <?php endforeach; ?>
    <div class="field">
        <label for="f-nombre"><?= icono('buscar') ?> Nombre del taller</label>
        <input type="search" id="f-nombre" name="nombre" value="<?= e($filtros['nombre']) ?>" maxlength="150"
               class="<?= $filtros['nombre'] !== '' ? 'is-active' : '' ?>" placeholder="Ej. Liderazgo"
               autocomplete="off">
    </div>
    <div class="field">
        <label for="f-carrera"><?= icono('carrera') ?> Carrera</label>
        <select id="f-carrera" name="carrera" class="<?= $filtros['carrera'] !== null ? 'is-active' : '' ?>">
            <option value="">Todas</option>
            <?php foreach ($carreras as $c) : ?>
                <option value="<?= e($c['id']) ?>"<?= $filtros['carrera'] === (int) $c['id'] ? ' selected' : '' ?>>
                    <?= e($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-edificio"><?= icono('edificio') ?> Edificio</label>
        <select id="f-edificio" name="edificio" class="<?= $filtros['edificio'] !== null ? 'is-active' : '' ?>">
            <option value="">Todos</option>
            <?php foreach ($edificios as $ed) : ?>
                <option value="<?= e($ed['id']) ?>"<?= $filtros['edificio'] === (int) $ed['id'] ? ' selected' : '' ?>>
                    <?= e(edificio_etiqueta($ed['numero'], $ed['nombre'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filters__actions">
        <button type="submit" class="btn btn--primary filters__submit"><?= icono('buscar') ?> Filtrar</button>
        <a class="btn btn--secondary" href="<?= e(url($ruta, $ocultos)) ?>" data-limpiar
           <?= $hayFiltros ? '' : 'hidden' ?>><?= icono('cerrar') ?> Limpiar</a>
    </div>
    <p class="sr-only" aria-live="polite" data-anuncio></p>
</form>
