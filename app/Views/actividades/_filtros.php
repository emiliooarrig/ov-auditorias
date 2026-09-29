<?php

/**
 * Barra de filtros por nombre, carrera y edificio (RF-08). Se envía por GET y conserva los valores.
 *
 * @var string                                  $ruta
 * @var array{nombre: string, carrera: ?int, edificio: ?int} $filtros
 * @var list<array{id: int, nombre: string}>    $carreras
 * @var list<array{id: int, numero: int}>       $edificios
 * @var bool                                    $hayFiltros
 */
?>
<form class="tarjeta filtros" method="get" action="<?= e(url($ruta)) ?>" role="search" aria-label="Filtrar talleres">
    <div class="campo">
        <label for="f-nombre">Nombre</label>
        <input type="search" id="f-nombre" name="nombre" value="<?= e($filtros['nombre']) ?>" maxlength="150"
               class="<?= $filtros['nombre'] !== '' ? 'filtro-activo' : '' ?>" placeholder="Buscar por nombre">
    </div>
    <div class="campo">
        <label for="f-carrera">Carrera</label>
        <select id="f-carrera" name="carrera" class="<?= $filtros['carrera'] !== null ? 'filtro-activo' : '' ?>">
            <option value="">Todas</option>
            <?php foreach ($carreras as $c) : ?>
                <option value="<?= e($c['id']) ?>"<?= $filtros['carrera'] === (int) $c['id'] ? ' selected' : '' ?>>
                    <?= e($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="f-edificio">Edificio</label>
        <select id="f-edificio" name="edificio" class="<?= $filtros['edificio'] !== null ? 'filtro-activo' : '' ?>">
            <option value="">Todos</option>
            <?php foreach ($edificios as $ed) : ?>
                <option value="<?= e($ed['id']) ?>"<?= $filtros['edificio'] === (int) $ed['id'] ? ' selected' : '' ?>>
                    Edificio <?= e($ed['numero']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filtros__acciones">
        <button type="submit" class="btn btn-primario">Filtrar</button>
        <?php if ($hayFiltros) : ?>
            <a class="btn btn-secundario" href="<?= e(url($ruta)) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>
