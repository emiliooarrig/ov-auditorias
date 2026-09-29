<?php

/**
 * Ingreso: paso 1 (correo) o paso 2 (contraseña del administrador).
 *
 * @var string|null  $correoAdministrador
 * @var list<string> $dominios
 */

$errorCorreo = error_de('correo');
$errorPassword = error_de('password');
?>
<section class="acceso tarjeta">
    <?php if ($correoAdministrador === null) : ?>
        <h1>Ingresar</h1>
        <p class="texto-secundario">
            Escribe tu correo institucional. Si es tu primera vez, te pediremos tu nombre para registrarte.
        </p>

        <form method="post" action="<?= e(url('/login')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="campo<?= $errorCorreo !== null ? ' campo--error' : '' ?>">
                <label for="correo">Correo institucional</label>
                <input type="email" id="correo" name="correo" value="<?= e(old('correo')) ?>"
                       autocomplete="email" inputmode="email" required autofocus
                       placeholder="nombre.apellido@<?= e($dominios[0] ?? '') ?>"
                       <?= $errorCorreo !== null ? 'aria-invalid="true" aria-describedby="correo-error"' : '' ?>>
                <?php if ($errorCorreo !== null) : ?>
                    <span class="error-campo" id="correo-error"><?= e($errorCorreo) ?></span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primario btn-bloque">Continuar</button>
        </form>
    <?php else : ?>
        <h1>Contraseña de administrador</h1>
        <p class="texto-secundario">
            Ingresando como <strong><?= e($correoAdministrador) ?></strong>.
            <a href="<?= e(url('/login', ['cambiar' => 1])) ?>">Usar otro correo</a>
        </p>

        <form method="post" action="<?= e(url('/login')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="campo<?= $errorPassword !== null ? ' campo--error' : '' ?>">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required autofocus
                       <?= $errorPassword !== null ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                <?php if ($errorPassword !== null) : ?>
                    <span class="error-campo" id="password-error"><?= e($errorPassword) ?></span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primario btn-bloque">Ingresar</button>
        </form>
    <?php endif; ?>
</section>
