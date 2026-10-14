<?php

/**
 * Administradores, las únicas cuentas del sistema: alta, activar o desactivar y cambio de contraseña.
 *
 * @var list<array{id: int, nombre: string, apellidos: string, correo: string, activo: int,
 *     ultimo_acceso: ?string, creado_en: string}> $administradores
 * @var int $actorId
 * @var int $passwordMin
 * @var list<string> $dominios
 */

$errores = [
    'nombre' => error_de('nombre'),
    'apellidos' => error_de('apellidos'),
    'correo' => error_de('correo'),
    'password' => error_de('password'),
];
$campoError = static fn (string $campo): string => $errores[$campo] !== null
    ? ' aria-invalid="true" aria-describedby="admin-' . $campo . '-error"'
    : '';
?>
<div class="page-header">
    <div>
        <h1>Administradores</h1>
        <p>
            Las únicas cuentas del sistema. Cada administrador ingresa con su correo institucional y su
            contraseña; los alumnos no tienen cuenta.
        </p>
    </div>
</div>

<div class="layout-aside">
    <div class="card table-wrap">
        <table class="table table--stack">
            <thead>
                <tr>
                    <th scope="col">Administrador</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Último acceso</th>
                    <th scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($administradores as $u) : ?>
                    <?php
                    $esYo = $u['id'] === $actorId;
                    $activo = (int) $u['activo'] === 1;
                    $nombreCompleto = $u['nombre'] . ' ' . $u['apellidos'];
                    $urlCuenta = '/administradores/' . $u['id'];
                    // Textos del diálogo de confirmación (app.js + SweetAlert).
                    $confirmar = ($activo ? '¿Desactivar a ' : '¿Activar a ') . $nombreCompleto . '?';
                    $detalle = $activo
                        ? 'No podrá ingresar. Su historial se conserva.'
                        : 'Podrá volver a ingresar con su correo y su contraseña.';
                    $claseBoton = 'btn btn--link' . ($activo ? ' btn--link-danger' : '');
                    ?>
                    <tr>
                        <td class="cell-title">
                            <strong class="with-icon"><?= icono('escudo') ?> <?= e($nombreCompleto) ?></strong>
                            <?php if ($esYo) : ?>
                                <span class="muted">(tú)</span>
                            <?php endif; ?>
                            <div class="table__sub"><?= icono('correo') ?> <?= e($u['correo']) ?></div>
                        </td>
                        <td data-label="Estado">
                            <?php if ($activo) : ?>
                                <span class="badge badge--active"><?= icono('check-circulo') ?> Activo</span>
                            <?php else : ?>
                                <span class="badge badge--inactive"><?= icono('prohibido') ?> Desactivado</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Último acceso">
                            <?php if ($u['ultimo_acceso'] !== null) : ?>
                                <span class="with-icon">
                                    <?= icono('reloj') ?> <?= e(fecha_hora($u['ultimo_acceso'])) ?>
                                </span>
                            <?php else : ?>
                                <span class="muted">Nunca</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Acciones">
                            <div class="row-actions">
                                <?php if (!$esYo) : ?>
                                    <form method="post" action="<?= e(url($urlCuenta . '/estado')) ?>"
                                          data-confirmar="<?= e($confirmar) ?>"
                                          data-confirmar-texto="<?= e($detalle) ?>"
                                          data-confirmar-boton="<?= $activo ? 'Desactivar' : 'Activar' ?>"
                                          data-confirmar-tipo="<?= $activo ? 'peligro' : 'primario' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="activo" value="<?= $activo ? '0' : '1' ?>">
                                        <button type="submit" class="<?= $claseBoton ?>">
                                            <?= icono($activo ? 'prohibido' : 'encender') ?>
                                            <?= $activo ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <details class="disclosure">
                                    <summary>
                                        <?= icono('candado') ?>
                                        <?= $esYo ? 'Cambiar mi contraseña' : 'Cambiar contraseña' ?>
                                    </summary>
                                    <form method="post" action="<?= e(url($urlCuenta . '/password')) ?>">
                                        <?= csrf_field() ?>
                                        <?php if ($esYo) : ?>
                                            <div class="field">
                                                <label for="pw-actual">Contraseña actual</label>
                                                <input type="password" id="pw-actual" name="password_actual"
                                                       autocomplete="current-password" required>
                                            </div>
                                        <?php else : ?>
                                            <p class="muted">
                                                Compártela con <?= e($u['nombre']) ?> por un medio seguro.
                                            </p>
                                        <?php endif; ?>
                                        <div class="field">
                                            <label for="pw-<?= e($u['id']) ?>">Contraseña nueva</label>
                                            <input type="password" id="pw-<?= e($u['id']) ?>" name="password"
                                                   minlength="<?= e($passwordMin) ?>" required
                                                   autocomplete="new-password">
                                        </div>
                                        <div class="field">
                                            <label for="pw2-<?= e($u['id']) ?>">Repite la contraseña</label>
                                            <input type="password" id="pw2-<?= e($u['id']) ?>"
                                                   name="password_confirmacion" minlength="<?= e($passwordMin) ?>"
                                                   required autocomplete="new-password">
                                        </div>
                                        <button type="submit" class="btn btn--primary btn--small">
                                            <?= icono('guardar') ?> Guardar contraseña
                                        </button>
                                    </form>
                                </details>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <aside class="card" aria-labelledby="t-nuevo-admin">
        <h2 class="card__title" id="t-nuevo-admin"><?= icono('usuario-mas') ?> Agregar administrador</h2>
        <form method="post" action="<?= e(url('/administradores')) ?>" novalidate>
            <?= csrf_field() ?>
            <?php
            $campos = [
                'nombre' => ['Nombre(s)', 'text', 80, ''],
                'apellidos' => ['Apellidos', 'text', 120, ''],
                'correo' => ['Correo institucional', 'email', 150, 'nombre.apellido@' . ($dominios[0] ?? '')],
            ];
            ?>
            <?php foreach ($campos as $campo => [$etiqueta, $tipo, $maximo, $ejemplo]) : ?>
                <div class="field<?= $errores[$campo] !== null ? ' field--error' : '' ?>">
                    <label for="admin-<?= e($campo) ?>"><?= e($etiqueta) ?></label>
                    <input type="<?= e($tipo) ?>" id="admin-<?= e($campo) ?>" name="<?= e($campo) ?>"
                           value="<?= e(old($campo)) ?>" maxlength="<?= e($maximo) ?>" autocomplete="off" required
                           <?= $ejemplo !== '' ? 'placeholder="' . e($ejemplo) . '"' : '' ?>
                           <?= $campoError($campo) ?>>
                    <?php if ($errores[$campo] !== null) : ?>
                        <?= error_campo('admin-' . $campo . '-error', $errores[$campo]) ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="field<?= $errores['password'] !== null ? ' field--error' : '' ?>">
                <label for="admin-password">Contraseña</label>
                <input type="password" id="admin-password" name="password" minlength="<?= e($passwordMin) ?>"
                       autocomplete="new-password" required
                       <?= $errores['password'] !== null
                           ? 'aria-invalid="true" aria-describedby="admin-password-error"'
                           : 'aria-describedby="admin-password-ayuda"' ?>>
                <?php if ($errores['password'] !== null) : ?>
                    <?= error_campo('admin-password-error', $errores['password']) ?>
                <?php else : ?>
                    <span class="field__hint" id="admin-password-ayuda">
                        <?= icono('info') ?> Al menos <?= e($passwordMin) ?> caracteres. Compártela por un medio seguro.
                    </span>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="admin-password2">Repite la contraseña</label>
                <input type="password" id="admin-password2" name="password_confirmacion"
                       minlength="<?= e($passwordMin) ?>" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn--primary btn--block">
                <?= icono('usuario-mas') ?> Agregar administrador
            </button>
        </form>
    </aside>
</div>
