<?php

/**
 * Registro de un auditor nuevo (RF-02). El correo ya se validó en el paso de ingreso.
 *
 * @var string $correo
 */

$campos = [
    'nombre' => ['Nombre(s)', 'given-name', 80, 'usuario'],
    'apellidos' => ['Apellidos', 'family-name', 120, 'usuario'],
];
?>
<section class="auth">
    <div class="auth__intro">
        <span class="icon-bubble"><?= icono('mis-talleres') ?></span>
        <p class="auth__tagline">Tus talleres, dónde y cuándo.</p>
        <p>Consulta los talleres que te asignaron y registra si alguno no se realizó.</p>
    </div>
    <div class="auth__form">
        <h1><?= icono('usuario-mas') ?> Crea tu cuenta</h1>
        <p class="muted">
            Es la primera vez que ingresas con <strong><?= e($correo) ?></strong>.
            Completa tus datos para crear tu cuenta de auditor.
            <a href="<?= e(url('/login')) ?>">Usar otro correo</a>
        </p>

        <form method="post" action="<?= e(url('/registro')) ?>" novalidate>
            <?= csrf_field() ?>
            <?php foreach ($campos as $campo => [$etiqueta, $autocompletar, $maximo, $iconoCampo]) : ?>
                <?php $error = error_de($campo); ?>
                <div class="field<?= $error !== null ? ' field--error' : '' ?>">
                    <label for="<?= e($campo) ?>"><?= icono($iconoCampo) ?> <?= e($etiqueta) ?></label>
                    <input type="text" id="<?= e($campo) ?>" name="<?= e($campo) ?>" value="<?= e(old($campo)) ?>"
                           autocomplete="<?= e($autocompletar) ?>" maxlength="<?= e($maximo) ?>" required
                           <?= $error !== null
                               ? 'aria-invalid="true" aria-describedby="' . e($campo) . '-error"'
                               : '' ?>>
                    <?php if ($error !== null) : ?>
                        <?= error_campo($campo . '-error', $error) ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn--primary btn--block"><?= icono('check') ?> Crear mi cuenta</button>
        </form>
    </div>
</section>
