<?php

/**
 * Asignaciones: qué falta por cubrir (alumnos sin taller y talleres sin auditor), cómo están repartidos
 * los talleres y la asignación aleatoria, que completa lo que falta para que cada taller tenga los
 * auditores que se indiquen, sin tocar lo ya asignado y respetando el horario de cada alumno.
 * También avisa de los alumnos sin horario y de las asignaciones que quedaron fuera del horario del
 * alumno, con un botón para quitarlas. La asignación manual se hace desde el panel del alumno o el
 * detalle del taller.
 *
 * @phpstan-import-type FueraDeHorario from \App\Models\Asignacion
 * @var array{
 *     alumnos: int, talleres: int,
 *     alumnosSinTaller: list<array{id: int, nombre: string, apellidos: string}>,
 *     talleresSinAuditor: list<array{id: int, nombre: string, fecha: string, hora_inicio: string,
 *         hora_fin: string, grupo_nombre: string, edificio: int, edificio_nombre: string}>,
 *     alumnosSinHorario: list<array{id: int, nombre: string, apellidos: string}>,
 *     fueraDeHorario: list<FueraDeHorario>,
 *     auditoresPorTaller: list<array{auditores: int, talleres: int}>,
 *     talleresPorAlumno: list<array{talleres: int, alumnos: int}>,
 *     asignacionesProgramadas: int
 * } $cobertura
 * @var int $auditoresPorTaller Valor del campo «Auditores por taller».
 * @var int $maxAuditoresPorTaller
 */

$sinTaller = count($cobertura['alumnosSinTaller']);
$sinAuditor = count($cobertura['talleresSinAuditor']);
$sinHorario = count($cobertura['alumnosSinHorario']);
$fuera = count($cobertura['fueraDeHorario']);
$confirmarQuitar = '¿Quitar ' . ($fuera === 1 ? 'la asignación' : "las {$fuera} asignaciones") . ' fuera de horario?';
$detalleQuitar = 'Esos talleres quedarán libres para la asignación al azar. '
    . 'Los talleres realizados o no realizados no se tocan.';
$programadas = $cobertura['asignacionesProgramadas'];
$todas = $programadas === 1 ? 'la asignación' : "las {$programadas} asignaciones";
$detalleQuitarTodas = 'No se puede deshacer: los talleres programados quedarán sin auditores hasta que vuelvas '
    . 'a asignar. Los talleres realizados o no realizados no se tocan.';
// Aunque todo esté cubierto no se bloquea: se pueden pedir más auditores por taller.
$motivoBloqueo = match (true) {
    $cobertura['alumnos'] === 0 => 'Primero agrega alumnos.',
    $sinHorario === $cobertura['alumnos'] => 'Ningún alumno tiene horario: regístralo en su panel.',
    $cobertura['talleres'] === 0 => 'No hay talleres programados que asignar.',
    default => null,
};
$detalleAzar = 'Se crearán las asignaciones que falten para llegar a esa cantidad de auditores por taller. '
    . 'Las que ya existen no cambian.';
// Talleres programados con menos auditores de los que pide el formulario.
$bajoObjetivo = array_sum(array_map(
    static fn (array $f): int => (int) $f['auditores'] < $auditoresPorTaller ? (int) $f['talleres'] : 0,
    $cobertura['auditoresPorTaller']
));
$objetivo = cantidad($auditoresPorTaller, 'auditor', 'auditores');
$avisoObjetivo = $bajoObjetivo === 0
    ? "Todos los talleres programados tienen {$objetivo} o más."
    : ($bajoObjetivo === 1 ? '1 taller tiene' : "{$bajoObjetivo} talleres tienen") . " menos de {$objetivo}. "
        . 'La asignación al azar los completa si hay alumnos libres en su horario.';
// Las tarjetas de pendientes se marcan en naranja si hay algo por cubrir y en verde (con ✓) si no;
// sin alumnos o sin talleres no se marcan, porque todavía no hay nada que cubrir.
$tarjeta = static fn (string $etiqueta, int $valor, string $icono, ?bool $pendiente = null): array => [
    'etiqueta' => $etiqueta,
    'valor' => $valor,
    'icono' => $pendiente === false ? 'check-circulo' : $icono,
    'clase' => match ($pendiente) {
        true => ' stat--attention',
        false => ' stat--ok',
        null => '',
    },
];
$tarjetas = [
    $tarjeta('Alumnos activos', $cobertura['alumnos'], 'usuarios'),
    $tarjeta('Alumnos sin taller', $sinTaller, 'usuario-menos', $cobertura['alumnos'] > 0 ? $sinTaller > 0 : null),
    $tarjeta('Talleres programados', $cobertura['talleres'], 'calendario'),
    $tarjeta('Talleres sin auditor', $sinAuditor, 'alerta', $cobertura['talleres'] > 0 ? $sinAuditor > 0 : null),
];
?>
<div class="page-header">
    <div>
        <h1>Asignaciones</h1>
        <p>
            Revisa qué falta por cubrir y reparte talleres al azar. Para asignar a mano, abre un alumno en
            Alumnos o un taller en el Panel central.
        </p>
    </div>
</div>

<ul class="stats" aria-label="Cobertura de las auditorías">
    <?php foreach ($tarjetas as $t) : ?>
        <li class="stat<?= $t['clase'] ?>">
            <span class="stat__label"><?= icono($t['icono']) ?> <?= e($t['etiqueta']) ?></span>
            <span class="stat__value nums"><?= e($t['valor']) ?></span>
        </li>
    <?php endforeach; ?>
</ul>

<div class="layout-detail layout-detail--aside-first">
    <div>
        <?php if ($fuera > 0) : ?>
            <section class="card" aria-labelledby="t-fuera-horario">
                <h2 class="card__title" id="t-fuera-horario">
                    <?= icono('reloj') ?> Asignaciones fuera de horario
                    <span class="count-pill nums"><?= e($fuera) ?></span>
                </h2>
                <p>
                    Estos alumnos auditan talleres programados que no caben en su horario: se asignaron antes de
                    registrar su horario o el taller cambió de grupo. Quítalas y vuelve a asignar al azar para que
                    esos talleres queden con alumnos en horario.
                </p>
                <details class="disclosure">
                    <summary>
                        <?= icono('lista') ?> Ver <?= e(cantidad($fuera, 'asignación', 'asignaciones')) ?>
                    </summary>
                    <ul class="people">
                        <?php foreach ($cobertura['fueraDeHorario'] as $f) : ?>
                            <li>
                                <div class="person">
                                    <a href="<?= e(url('/alumnos/' . $f['alumno_id'])) ?>">
                                        <strong><?= e($f['alumno_apellidos'] . ', ' . $f['alumno_nombre']) ?></strong>
                                    </a>
                                    <span class="person__meta nums">
                                        <?= icono('reloj') ?>
                                        <?= e(horario_alumno($f['horario'], $f['horario_inicio'], $f['horario_fin'])) ?>
                                    </span>
                                    <span class="person__meta nums">
                                        <?= icono('calendario') ?>
                                        <a href="<?= e(url('/actividades/' . $f['actividad_id'])) ?>">
                                            <?= e($f['nombre']) ?></a>,
                                        <?= e(fecha_corta($f['fecha'])) ?>,
                                        <?= e(grupo_etiqueta($f['grupo_nombre'], $f['hora_inicio'], $f['hora_fin'])) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <form class="form-actions" method="post"
                      action="<?= e(url('/asignaciones/fuera-de-horario/quitar')) ?>"
                      data-confirmar="<?= e($confirmarQuitar) ?>"
                      data-confirmar-texto="<?= e($detalleQuitar) ?>"
                      data-confirmar-boton="Quitar"
                      data-confirmar-tipo="peligro">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger">
                        <?= icono('usuario-menos') ?>
                        <?= $fuera === 1 ? 'Quitar la asignación' : 'Quitar las ' . e($fuera) . ' asignaciones' ?>
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($sinHorario > 0) : ?>
            <section class="card" aria-labelledby="t-sin-horario">
                <h2 class="card__title" id="t-sin-horario">
                    <?= icono('reloj') ?> Alumnos sin horario
                    <span class="count-pill nums"><?= e($sinHorario) ?></span>
                </h2>
                <p class="muted">
                    No reciben talleres, ni a mano ni al azar, hasta que registres su horario en su panel
                    («Corregir datos»).
                </p>
                <ul class="tag-list">
                    <?php foreach ($cobertura['alumnosSinHorario'] as $a) : ?>
                        <li>
                            <a class="tag" href="<?= e(url('/alumnos/' . $a['id'])) ?>">
                                <?= icono('usuario') ?> <?= e($a['apellidos'] . ', ' . $a['nombre']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($cobertura['talleres'] > 0 || $cobertura['alumnos'] > 0) : ?>
            <section class="card" aria-labelledby="t-reparto">
                <h2 class="card__title" id="t-reparto"><?= icono('panel') ?> Reparto actual</h2>
                <?php if ($cobertura['talleres'] > 0) : ?>
                    <section class="panel__section" aria-labelledby="t-auditores-por-taller">
                        <h3 class="panel__subtitle" id="t-auditores-por-taller">Auditores por taller programado</h3>
                        <dl class="facts">
                            <?php foreach ($cobertura['auditoresPorTaller'] as $f) : ?>
                                <div>
                                    <dt>
                                        <?= icono('usuarios') ?>
                                        <?= e((int) $f['auditores'] === 0
                                            ? 'Sin auditor'
                                            : cantidad((int) $f['auditores'], 'auditor', 'auditores')) ?>
                                    </dt>
                                    <dd class="nums"><?= e(cantidad((int) $f['talleres'], 'taller', 'talleres')) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endif; ?>
                <?php if ($cobertura['alumnos'] > 0) : ?>
                    <section class="panel__section" aria-labelledby="t-talleres-por-alumno">
                        <h3 class="panel__subtitle" id="t-talleres-por-alumno">Talleres por alumno activo</h3>
                        <dl class="facts">
                            <?php foreach ($cobertura['talleresPorAlumno'] as $f) : ?>
                                <div>
                                    <dt>
                                        <?= icono('mis-talleres') ?>
                                        <?= e((int) $f['talleres'] === 0
                                            ? 'Sin taller'
                                            : cantidad((int) $f['talleres'], 'taller', 'talleres')) ?>
                                    </dt>
                                    <dd class="nums"><?= e(cantidad((int) $f['alumnos'], 'alumno', 'alumnos')) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endif; ?>
                <?php if ($cobertura['talleres'] > 0) : ?>
                    <p class="muted with-icon">
                        <?= icono($bajoObjetivo === 0 ? 'check-circulo' : 'info') ?> <?= e($avisoObjetivo) ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="t-sin-auditor">
            <h2 class="card__title" id="t-sin-auditor"><?= icono('alerta') ?> Talleres sin auditor</h2>
            <?php if ($cobertura['talleresSinAuditor'] === []) : ?>
                <p class="muted with-icon">
                    <?= icono('check-circulo') ?> Todos los talleres programados tienen al menos un auditor.
                </p>
            <?php else : ?>
                <ul class="people">
                    <?php foreach ($cobertura['talleresSinAuditor'] as $t) : ?>
                        <li>
                            <div class="person">
                                <a href="<?= e(url('/actividades/' . $t['id'])) ?>">
                                    <strong><?= e($t['nombre']) ?></strong>
                                </a>
                                <span class="person__meta nums">
                                    <?= icono('calendario') ?> <?= e(fecha_corta($t['fecha'])) ?>,
                                    <?= e(grupo_etiqueta($t['grupo_nombre'], $t['hora_inicio'], $t['hora_fin'])) ?>
                                </span>
                                <span class="person__meta">
                                    <?= icono('edificio') ?>
                                    <?= e(edificio_etiqueta($t['edificio'], $t['edificio_nombre'])) ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card" aria-labelledby="t-sin-taller">
            <h2 class="card__title" id="t-sin-taller"><?= icono('usuario-menos') ?> Alumnos sin taller</h2>
            <?php if ($cobertura['alumnosSinTaller'] === []) : ?>
                <p class="muted with-icon">
                    <?= icono('check-circulo') ?>
                    <?= $cobertura['alumnos'] === 0
                        ? 'Todavía no hay alumnos activos.'
                        : 'Todos los alumnos activos auditan al menos un taller.' ?>
                </p>
            <?php else : ?>
                <ul class="tag-list">
                    <?php foreach ($cobertura['alumnosSinTaller'] as $a) : ?>
                        <li>
                            <a class="tag" href="<?= e(url('/alumnos/' . $a['id'])) ?>">
                                <?= icono('usuario') ?> <?= e($a['apellidos'] . ', ' . $a['nombre']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <aside>
        <section class="card card--warm" aria-labelledby="t-azar">
            <h2 class="card__title" id="t-azar"><?= icono('aleatorio') ?> Asignación al azar</h2>
            <p>Completa lo que falta y respeta lo que ya está asignado:</p>
            <ol class="rule-list">
                <li>
                    Cada taller recibe auditores hasta tener los que indiques: primero todos reciben uno, luego el
                    segundo, y así.
                </li>
                <li>Cada lugar va al alumno con menos talleres.</li>
                <li>Cada alumno sin taller recibe el taller con menos auditores.</li>
                <li>Nadie queda en dos talleres a la misma hora.</li>
                <li>Cada alumno solo audita talleres dentro de su horario (matutino, vespertino o todo el día).</li>
            </ol>
            <form method="post" action="<?= e(url('/asignaciones/aleatoria')) ?>"
                  data-confirmar="¿Asignar al azar?"
                  data-confirmar-texto="<?= e($detalleAzar) ?>"
                  data-confirmar-boton="Asignar al azar">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="auditores-por-taller"><?= icono('usuarios') ?> Auditores por taller</label>
                    <input type="number" id="auditores-por-taller" name="auditores_por_taller"
                           value="<?= e($auditoresPorTaller) ?>" min="1" max="<?= e($maxAuditoresPorTaller) ?>"
                           step="1" inputmode="numeric" required aria-describedby="auditores-ayuda"
                           <?= $motivoBloqueo !== null ? 'disabled' : '' ?>>
                    <span class="field__hint" id="auditores-ayuda">
                        <?= icono('info') ?> De 1 a <?= e($maxAuditoresPorTaller) ?>. Si a esa hora no hay
                        suficientes alumnos libres en su horario, el taller se queda con menos.
                    </span>
                </div>
                <button type="submit" class="btn btn--primary btn--block"
                    <?= $motivoBloqueo !== null ? 'disabled aria-describedby="azar-motivo"' : '' ?>>
                    <?= icono('aleatorio') ?> Asignar al azar
                </button>
            </form>
            <?php if ($motivoBloqueo !== null) : ?>
                <p class="field__hint" id="azar-motivo"><?= icono('info') ?> <?= e($motivoBloqueo) ?></p>
            <?php endif; ?>
        </section>

        <?php if ($programadas > 0) : ?>
            <section class="card card--danger" aria-labelledby="t-quitar-todas">
                <h2 class="card__title" id="t-quitar-todas"><?= icono('alerta') ?> Quitar todas las asignaciones</h2>
                <p class="muted">
                    Quita a los auditores de todos los talleres programados, asignados a mano o al azar, para
                    volver a repartir desde cero. Los talleres realizados o no realizados conservan sus auditores
                    como registro.
                </p>
                <form method="post" action="<?= e(url('/asignaciones/quitar-todas')) ?>"
                      data-confirmar="<?= e('¿Quitar ' . $todas . '?') ?>"
                      data-confirmar-texto="<?= e($detalleQuitarTodas) ?>"
                      data-confirmar-boton="Quitar todas"
                      data-confirmar-tipo="peligro">
                    <?= csrf_field() ?>
                    <?php /* Sin JavaScript se confirma con la casilla; con él, la marca el diálogo. */ ?>
                    <label class="check" data-confirmacion-manual>
                        <input type="checkbox" name="confirmar" value="1" required>
                        Confirmo que quiero quitar todas las asignaciones.
                    </label>
                    <button type="submit" class="btn btn--danger btn--block">
                        <?= icono('usuario-menos') ?> Quitar <?= e($todas) ?>
                    </button>
                </form>
            </section>
        <?php endif; ?>
    </aside>
</div>
