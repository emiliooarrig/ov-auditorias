<?php

/**
 * Tabla de talleres; en pantallas angostas se convierte en tarjetas apiladas, una por taller.
 *
 * @var list<array{id: int, nombre: string, carrera: string, edificio: int, edificio_nombre: string,
 *     grupo_nombre: string, fecha: string, hora_inicio: string,
 *     hora_fin: string, estado: string, motivo_no_realizado: ?string, registrado_por: ?string,
 *     auditores: ?string}> $filas
 * @var bool $enlaceDetalle
 */
?>
<div class="card table-wrap">
    <table class="table table--stack">
        <thead>
            <tr>
                <th scope="col">Taller</th>
                <th scope="col" class="col-date">Fecha</th>
                <th scope="col">Edificio</th>
                <th scope="col">Estado</th>
                <th scope="col">Auditores</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) : ?>
                <tr>
                    <td class="cell-title">
                        <span class="table__title">
                            <?php if ($enlaceDetalle) : ?>
                                <a href="<?= e(url('/actividades/' . $fila['id'])) ?>"><?= e($fila['nombre']) ?></a>
                            <?php else : ?>
                                <?= e($fila['nombre']) ?>
                            <?php endif; ?>
                        </span>
                        <span class="table__sub"><?= icono('carrera') ?> <?= e($fila['carrera']) ?></span>
                    </td>
                    <td data-label="Fecha">
                        <?= app()->view()->partial('partials/fecha', [
                            'fecha' => $fila['fecha'],
                            'inicio' => $fila['hora_inicio'],
                            'fin' => $fila['hora_fin'],
                            'grupo' => $fila['grupo_nombre'],
                        ]) ?>
                    </td>
                    <td data-label="Edificio">
                        <span class="building">
                            <?= icono('edificio') ?>
                            <?= e(edificio_etiqueta($fila['edificio'], $fila['edificio_nombre'])) ?>
                        </span>
                    </td>
                    <td data-label="Estado">
                        <?= estado_insignia($fila['estado']) ?>
                        <?php if ($fila['estado'] === 'no_realizado' && $fila['motivo_no_realizado'] !== null) : ?>
                            <div class="reason">
                                <?= e($fila['motivo_no_realizado']) ?>
                                <?php if ($fila['registrado_por'] !== null) : ?>
                                    <br><span class="with-icon">
                                        <?= icono('usuario') ?> Registró: <?= e($fila['registrado_por']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Auditores">
                        <?php if ($fila['auditores'] === null) : ?>
                            <span class="muted with-icon"><?= icono('usuario-menos') ?> Sin asignar</span>
                        <?php else : ?>
                            <span class="with-icon"><?= icono('usuarios') ?> <?= e($fila['auditores']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
