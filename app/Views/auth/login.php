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
<section class="acceso">
    <div class="acceso__marca">
        <?= icono('mis-talleres', 'acceso__icono') ?>
        <p class="acceso__titulo">Tus talleres, dónde y cuándo.</p>
        <p>Consulta los talleres que te asignaron y registra si alguno no se realizó.</p>
    </div>
    <div class="acceso__formulario">
        <?php if ($correoAdministrador === null) : ?>
            <h1><?= icono('entrar') ?> Ingresar</h1>
            <p class="texto-secundario">
                Escribe tu correo institucional.
                Si es tu primera vez, te pediremos tu nombre para crear tu cuenta.
            </p>

            <form method="post" action="<?= e(url('/login')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="campo<?= $errorCorreo !== null ? ' campo--error' : '' ?>">
                    <label for="correo"><?= icono('correo') ?> Correo institucional</label>
                    <input type="email" id="correo" name="correo" value="<?= e(old('correo')) ?>"
                           autocomplete="email" inputmode="email" required autofocus
                           placeholder="nombre.apellido@<?= e($dominios[0] ?? '') ?>"
                           <?= $errorCorreo !== null ? 'aria-invalid="true" aria-describedby="correo-error"' : '' ?>>
                    <?php if ($errorCorreo !== null) : ?>
                        <?= error_campo('correo-error', $errorCorreo) ?>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primario btn-bloque">
                    Continuar <?= icono('flecha-derecha') ?>
                </button>
            </form>
        <?php else : ?>
            <h1><?= icono('escudo') ?> Contraseña de administrador</h1>
            <p class="texto-secundario">
                Ingresando como <strong><?= e($correoAdministrador) ?></strong>.
                <a href="<?= e(url('/login', ['cambiar' => 1])) ?>">Usar otro correo</a>
            </p>

            <form method="post" action="<?= e(url('/login')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="campo<?= $errorPassword !== null ? ' campo--error' : '' ?>">
                    <label for="password"><?= icono('candado') ?> Contraseña</label>
                    <div class="campo-clave">
                        <input type="password" id="password" name="password" autocomplete="current-password"
                               required autofocus
                               <?= $errorPassword !== null
                                   ? 'aria-invalid="true" aria-describedby="password-error"'
                                   : '' ?>>
                        <?php /* app.js lo muestra; sin JavaScript no serviría de nada. */ ?>
                        <button type="button" class="campo-clave__ver" data-ver-clave="password"
                                aria-controls="password" aria-pressed="false" hidden>
                            <?= icono('ojo', 'campo-clave__mostrar') ?>
                            <?= icono('ojo-tachado', 'campo-clave__ocultar') ?>
                            <span class="solo-lector">Mostrar contraseña</span>
                        </button>
                    </div>
                    <?php if ($errorPassword !== null) : ?>
                        <?= error_campo('password-error', $errorPassword) ?>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primario btn-bloque"><?= icono('entrar') ?> Ingresar</button>
            </form>
        <?php endif; ?>
    </div>
</section>
