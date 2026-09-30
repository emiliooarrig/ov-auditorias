<?php

/**
 * Gestión de usuarios (RF-10): registrados con su rol y estado; cambiar rol y activar o desactivar.
 * El servidor entrega la lista completa y app.js la filtra en el navegador por texto, rol y estado.
 *
 * @var list<array{id: int, nombre: string, apellidos: string, correo: string, rol: string,
 *     activo: int, ultimo_acceso: ?string, creado_en: string, talleres: int}> $usuarios
 * @var array{texto: string, rol: string, estado: string} $query
 * @var int $actorId
 * @var int $passwordMin
 */

$total = count($usuarios);
// Cada formulario reenvía los filtros para volver a la misma vista; app.js mantiene estos valores al día.
$camposRegreso = static function () use ($query): string {
    $html = csrf_field();
    foreach (['texto' => 'texto', 'filtro_rol' => 'rol', 'estado' => 'estado'] as $nombre => $filtro) {
        $html .= '<input type="hidden" name="' . e($nombre) . '" value="' . e($query[$filtro]) . '"'
            . ' data-regreso="' . e($filtro) . '">';
    }

    return $html;
};
?>
<div class="page-header">
    <div>
        <h1>Usuarios</h1>
        <p>Los auditores se registran solos al ingresar con su correo. Aquí cambias su rol o su acceso.</p>
    </div>
</div>

<?php if ($usuarios !== []) : ?>
    <form class="card filters" role="search" aria-label="Buscar usuarios" data-filtro-local="#tabla-usuarios" hidden>
        <div class="field">
            <label for="u-texto"><?= icono('buscar') ?> Nombre o correo</label>
            <input type="search" id="u-texto" name="texto" value="<?= e($query['texto']) ?>" maxlength="150"
                   autocomplete="off">
        </div>
        <div class="field">
            <label for="u-rol"><?= icono('escudo') ?> Rol</label>
            <select id="u-rol" name="rol">
                <option value="">Todos</option>
                <option value="auditor"<?= $query['rol'] === 'auditor' ? ' selected' : '' ?>>Auditor</option>
                <option value="administrador"<?= $query['rol'] === 'administrador' ? ' selected' : '' ?>>
                    Administrador
                </option>
            </select>
        </div>
        <div class="field">
            <label for="u-estado"><?= icono('encender') ?> Estado</label>
            <select id="u-estado" name="estado">
                <option value="">Todos</option>
                <option value="activos"<?= $query['estado'] === 'activos' ? ' selected' : '' ?>>Activos</option>
                <option value="desactivados"<?= $query['estado'] === 'desactivados' ? ' selected' : '' ?>>
                    Desactivados
                </option>
            </select>
        </div>
        <div class="filters__actions">
            <button type="button" class="btn btn--secondary" data-limpiar hidden>
                <?= icono('cerrar') ?> Limpiar
            </button>
        </div>
    </form>
<?php endif; ?>

<p class="summary" role="status">
    <span><strong data-conteo-usuarios><?= e($total) ?> <?= $total === 1 ? 'usuario' : 'usuarios' ?></strong></span>
</p>

<section class="empty" data-sin-coincidencias hidden>
    <span class="icon-bubble"><?= icono('sin-resultados') ?></span>
    <p><strong>Ningún usuario coincide con la búsqueda.</strong></p>
    <button type="button" class="btn btn--secondary" data-limpiar><?= icono('cerrar') ?> Quitar filtros</button>
</section>

<?php if ($usuarios === []) : ?>
    <section class="empty">
        <span class="icon-bubble"><?= icono('usuarios') ?></span>
        <p><strong>Todavía no hay usuarios registrados.</strong></p>
    </section>
<?php else : ?>
    <div class="card table-wrap">
    <table class="table table--stack" id="tabla-usuarios">
        <thead>
            <tr>
                <th scope="col">Usuario</th>
                <th scope="col">Rol</th>
                <th scope="col">Estado</th>
                <th scope="col">Talleres</th>
                <th scope="col">Último acceso</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u) : ?>
                <?php
                $esYo = $u['id'] === $actorId;
                $activo = (int) $u['activo'] === 1;
                $esAdmin = $u['rol'] === 'administrador';
                $nombreCompleto = $u['nombre'] . ' ' . $u['apellidos'];
                // Textos del diálogo de confirmación (app.js + SweetAlert).
                $confirmarEstado = ($activo ? '¿Desactivar a ' : '¿Activar a ') . $nombreCompleto . '?';
                $detalleEstado = $activo
                    ? 'No podrá ingresar. Su historial y sus asignaciones se conservan.'
                    : 'Podrá volver a ingresar con su correo institucional.';
                $confirmarRol = '¿Cambiar a ' . $nombreCompleto . ' a auditor?';
                $detalleRol = 'Perderá el acceso a la administración y su contraseña se borrará.';
                ?>
                <tr data-buscar="<?= e($u['nombre'] . ' ' . $u['apellidos'] . ' ' . $u['correo']) ?>"
                    data-rol="<?= e($u['rol']) ?>" data-estado="<?= $activo ? 'activos' : 'desactivados' ?>">
                    <td class="cell-title">
                        <strong class="with-icon">
                            <?= icono($esAdmin ? 'escudo' : 'usuario') ?>
                            <?= e($u['nombre'] . ' ' . $u['apellidos']) ?>
                        </strong>
                        <?php if ($esYo) : ?>
                            <span class="muted">(tú)</span>
                        <?php endif; ?>
                        <div class="table__sub"><?= icono('correo') ?> <?= e($u['correo']) ?></div>
                    </td>
                    <td data-label="Rol">
                        <span class="badge <?= $esAdmin ? 'badge--admin' : 'badge--role' ?>">
                            <?= icono($esAdmin ? 'escudo' : 'usuario') ?>
                            <?= $esAdmin ? 'Administrador' : 'Auditor' ?>
                        </span>
                    </td>
                    <td data-label="Estado">
                        <?php if ($activo) : ?>
                            <span class="badge badge--active"><?= icono('check-circulo') ?> Activo</span>
                        <?php else : ?>
                            <span class="badge badge--inactive">
                                <?= icono('prohibido') ?> Desactivado
                            </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Talleres" class="nums">
                        <span class="with-icon"><?= icono('calendario') ?> <?= e($u['talleres']) ?></span>
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
                        <?php if ($esYo) : ?>
                            <span class="muted">Es tu cuenta</span>
                        <?php else : ?>
                            <div class="row-actions">
                            <form method="post" action="<?= e(url('/usuarios/' . $u['id'] . '/estado')) ?>"
                                  data-confirmar="<?= e($confirmarEstado) ?>"
                                  data-confirmar-texto="<?= e($detalleEstado) ?>"
                                  data-confirmar-boton="<?= $activo ? 'Desactivar' : 'Activar' ?>"
                                  data-confirmar-tipo="<?= $activo ? 'peligro' : 'primario' ?>">
                                <?= $camposRegreso() ?>
                                <input type="hidden" name="activo" value="<?= $activo ? '0' : '1' ?>">
                                <?php $claseBoton = 'btn btn--link' . ($activo ? ' btn--link-danger' : ''); ?>
                                <button type="submit" class="<?= $claseBoton ?>">
                                    <?= icono($activo ? 'prohibido' : 'encender') ?>
                                    <?= $activo ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>

                            <details class="disclosure">
                                <summary>
                                    <?= icono($esAdmin ? 'usuario' : 'escudo') ?>
                                    <?= $esAdmin ? 'Cambiar a auditor' : 'Hacer administrador' ?>
                                </summary>
                                <form method="post" action="<?= e(url('/usuarios/' . $u['id'] . '/rol')) ?>"
                                    <?php if ($esAdmin) : ?>
                                      data-confirmar="<?= e($confirmarRol) ?>"
                                      data-confirmar-texto="<?= e($detalleRol) ?>"
                                      data-confirmar-boton="Cambiar a auditor"
                                      data-confirmar-tipo="peligro"
                                    <?php endif; ?>>
                                    <?= $camposRegreso() ?>
                                    <?php if ($esAdmin) : ?>
                                        <input type="hidden" name="rol" value="auditor">
                                        <p class="muted">
                                            Perderá el acceso a la administración y su contraseña se borrará.
                                            Ingresará solo con su correo.
                                        </p>
                                        <button type="submit" class="btn btn--secondary btn--small">
                                            <?= icono('usuario') ?> Cambiar a auditor
                                        </button>
                                    <?php else : ?>
                                        <input type="hidden" name="rol" value="administrador">
                                        <p class="muted">
                                            Asígnale una contraseña de al menos <?= e($passwordMin) ?> caracteres
                                            y compártela por un medio seguro.
                                        </p>
                                        <div class="field">
                                            <label for="pw-<?= e($u['id']) ?>">
                                                <?= icono('candado') ?> Contraseña
                                            </label>
                                            <input type="password" id="pw-<?= e($u['id']) ?>" name="password"
                                                   minlength="<?= e($passwordMin) ?>"
                                                   autocomplete="new-password" required>
                                        </div>
                                        <div class="field">
                                            <label for="pw2-<?= e($u['id']) ?>">
                                                <?= icono('candado') ?> Repite la contraseña
                                            </label>
                                            <input type="password" id="pw2-<?= e($u['id']) ?>"
                                                   name="password_confirmacion"
                                                   minlength="<?= e($passwordMin) ?>"
                                                   autocomplete="new-password" required>
                                        </div>
                                        <button type="submit" class="btn btn--primary btn--small">
                                            <?= icono('escudo') ?> Hacer administrador
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </details>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
