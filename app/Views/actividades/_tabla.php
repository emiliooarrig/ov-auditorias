<?php

/**
 * Tabla de talleres; en pantallas angostas se convierte en tarjetas apiladas.
 *
 * @var list<array{id: int, nombre: string, carrera: string, edificio: int, fecha: string, hora_inicio: string,
 *     hora_fin: string, estado: string, motivo_no_realizado: ?string, auditores: ?string}> $filas
 * @var bool $enlaceDetalle
 */
?>
<table class="tabla tabla--responsiva">
    <thead>
        <tr>
            <th scope="col">Taller</th>
            <th scope="col">Carrera</th>
            <th scope="col">Edificio</th>
            <th scope="col">Horario</th>
            <th scope="col">Estado</th>
            <th scope="col">Auditores</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($filas as $fila) : ?>
            <tr>
                <td data-etiqueta="Taller">
                    <?php if ($enlaceDetalle) : ?>
                        <a href="<?= e(url('/actividades/' . $fila['id'])) ?>">
                            <strong><?= e($fila['nombre']) ?></strong>
                        </a>
                    <?php else : ?>
                        <strong><?= e($fila['nombre']) ?></strong>
                    <?php endif; ?>
                </td>
                <td data-etiqueta="Carrera"><?= e($fila['carrera']) ?></td>
                <td data-etiqueta="Edificio"><?= e($fila['edificio']) ?></td>
                <td data-etiqueta="Horario">
                    <?= e(fecha_corta($fila['fecha'])) ?><br>
                    <span class="texto-secundario"><?= e(horario($fila['hora_inicio'], $fila['hora_fin'])) ?></span>
                </td>
                <td data-etiqueta="Estado">
                    <?= estado_insignia($fila['estado']) ?>
                    <?php if ($fila['estado'] === 'no_realizado' && $fila['motivo_no_realizado'] !== null) : ?>
                        <div class="motivo"><?= e($fila['motivo_no_realizado']) ?></div>
                    <?php endif; ?>
                </td>
                <td data-etiqueta="Auditores">
                    <?php if ($fila['auditores'] === null) : ?>
                        <span class="texto-secundario">Sin asignar</span>
                    <?php else : ?>
                        <?= e($fila['auditores']) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
