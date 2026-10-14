<?php

/**
 * Ingreso de administradores: correo institucional y contraseña.
 *
 * @var list<string> $dominios
 */

$errorCorreo = error_de('correo');
$errorPassword = error_de('password');
?>
<section class="auth">
    <div class="auth__intro">
        <span class="icon-bubble"><?= icono('escudo') ?></span>
        <p class="auth__tagline">Quién audita qué taller.</p>
        <p>Control interno de las auditorías: alumnos, talleres y asignaciones. Solo para administradores.</p>
    </div>
    <div class="auth__form">
        <h1><?= icono('entrar') ?> Ingresar</h1>
        <p class="muted">Usa tu correo institucional y tu contraseña de administrador.</p>

        <form method="post" action="<?= e(url('/login')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field<?= $errorCorreo !== null ? ' field--error' : '' ?>">
                <label for="correo"><?= icono('correo') ?> Correo institucional</label>
                <input type="email" id="correo" name="correo" value="<?= e(old('correo')) ?>"
                       autocomplete="username" inputmode="email" required
                       <?= old('correo') === '' || $errorCorreo !== null ? 'autofocus' : '' ?>
                       placeholder="nombre.apellido@<?= e($dominios[0] ?? '') ?>"
                       <?= $errorCorreo !== null ? 'aria-invalid="true" aria-describedby="correo-error"' : '' ?>>
                <?php if ($errorCorreo !== null) : ?>
                    <?= error_campo('correo-error', $errorCorreo) ?>
                <?php endif; ?>
            </div>
            <div class="field<?= $errorPassword !== null ? ' field--error' : '' ?>">
                <label for="password"><?= icono('candado') ?> Contraseña</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" autocomplete="current-password" required
                           <?= old('correo') !== '' && $errorCorreo === null ? 'autofocus' : '' ?>
                           <?= $errorPassword !== null
                               ? 'aria-invalid="true" aria-describedby="password-error"'
                               : '' ?>>
                    <?php /* app.js lo muestra; sin JavaScript no serviría de nada. */ ?>
                    <button type="button" class="password-field__toggle" data-ver-clave="password"
                            aria-controls="password" aria-pressed="false" hidden>
                        <?= icono('ojo', 'password-field__show') ?>
                        <?= icono('ojo-tachado', 'password-field__hide') ?>
                        <span class="sr-only">Mostrar contraseña</span>
                    </button>
                </div>
                <?php if ($errorPassword !== null) : ?>
                    <?= error_campo('password-error', $errorPassword) ?>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn--primary btn--block"><?= icono('entrar') ?> Ingresar</button>
        </form>
    </div>
</section>
