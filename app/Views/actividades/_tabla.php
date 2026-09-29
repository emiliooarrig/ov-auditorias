<?php

/**
 * Tabla de talleres; en pantallas angostas se convierte en tarjetas apiladas.
 *
 * @var list<array{id: int, nombre: string, carrera: string, edificio: int, fecha: string, hora_inicio: string,
 *     hora_fin: string, estado: string, motivo_no_realizado: ?string, registrado_por: ?string,
 *     auditores: ?string}> $filas
 * @var bool $enlaceDetalle
 */
?>
<table class="tabla tabla--responsiva">
    <thead>
        <tr>
            <th scope="col" class="col-fecha">Fecha</th>
            <th scope="col">Taller</th>
            <th scope="col" class="col-edificio">Edificio</th>
            <th scope="col">Estado</th>
            <th scope="col">Auditores</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($filas as $fila) : ?>
            <tr class="fila fila--<?= e(estado_clase($fila['estado'])) ?>">
                <td class="celda-lateral">
                    <?= app()->view()->partial('partials/hoja', [
                        'fecha' => $fila['fecha'],
                        'inicio' => $fila['hora_inicio'],
                        'fin' => $fila['hora_fin'],
                    ]) ?>
                </td>
                <td>
                    <span class="tabla__nombre">
                        <?php if ($enlaceDetalle) : ?>
                            <a href="<?= e(url('/actividades/' . $fila['id'])) ?>">
                                <strong><?= e($fila['nombre']) ?></strong>
                            </a>
                        <?php else : ?>
                            <strong><?= e($fila['nombre']) ?></strong>
                        <?php endif; ?>
                    </span>
                    <span class="tabla__sub"><?= icono('carrera') ?> <?= e($fila['carrera']) ?></span>
                </td>
                <td data-etiqueta="Edificio" class="celda-en-linea">
                    <span class="placa"><?= e($fila['edificio']) ?></span>
                </td>
                <td>
                    <?= estado_insignia($fila['estado']) ?>
                    <?php if ($fila['estado'] === 'no_realizado' && $fila['motivo_no_realizado'] !== null) : ?>
                        <div class="motivo">
                            <?= e($fila['motivo_no_realizado']) ?>
                            <?php if ($fila['registrado_por'] !== null) : ?>
                                <br><?= icono('usuario') ?> Registró: <?= e($fila['registrado_por']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td data-etiqueta="Auditores">
                    <?php if ($fila['auditores'] === null) : ?>
                        <span class="texto-secundario con-icono"><?= icono('usuario-menos') ?> Sin asignar</span>
                    <?php else : ?>
                        <span class="con-icono"><?= icono('usuarios') ?> <?= e($fila['auditores']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
