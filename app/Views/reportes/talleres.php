<?php

/**
 * Reporte de talleres y auditores para PDF (Dompdf): tabla horizontal en carta apaisada con todos los
 * talleres activos, sus datos y sus alumnos auditores, agrupados por franja (fecha y grupo).
 * Documento independiente, sin el layout de la aplicación: Dompdf no carga recursos externos, así que
 * los estilos van aquí y solo usan la paleta institucional (SDD 7.1), sin sombras.
 *
 * @phpstan-import-type ReporteFila from \App\Models\Actividad
 * @var list<ReporteFila> $talleres
 * @var string $titulo
 * @var string $generado Fecha y hora de generación ("lun 12 oct 2026, 11:45").
 * @var string $generadoPor Administrador que lo descargó.
 */

$estados = ['programado' => 'Programado', 'realizado' => 'Realizado', 'no_realizado' => 'No realizado'];
$conAuditor = count(array_filter($talleres, static fn (array $t): bool => $t['auditores'] !== []));
$porEstado = array_count_values(array_column($talleres, 'estado'));
$alumnos = [];
foreach ($talleres as $t) {
    foreach ($t['auditores'] as $a) {
        $alumnos[$a['apellidos'] . '|' . $a['nombre']] = true;
    }
}
$resumen = [
    ['Talleres', count($talleres), ''],
    ['Con auditor', $conAuditor, ''],
    ['Sin auditor', count($talleres) - $conAuditor, count($talleres) > $conAuditor ? 'alerta' : ''],
    ['Programados', $porEstado['programado'] ?? 0, ''],
    ['Realizados', $porEstado['realizado'] ?? 0, ''],
    ['No realizados', $porEstado['no_realizado'] ?? 0, ''],
    ['Auditores', count($alumnos), ''],
];

// Franjas: talleres de la misma fecha y el mismo grupo (a la misma hora), en el orden del reporte.
$franjas = [];
foreach ($talleres as $t) {
    $clave = substr($t['fecha'], 0, 10) . '|' . $t['grupo_nombre'];
    $franjas[$clave] ??= ['muestra' => $t, 'talleres' => []];
    $franjas[$clave]['talleres'][] = $t;
}
$numero = 0;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= e($titulo) ?></title>
    <style>
        @page { size: letter landscape; margin: 14mm 12mm 16mm; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 8pt; line-height: 1.3; color: #2B211A; }

        /* Encabezado */
        .encabezado { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .encabezado td { vertical-align: middle; padding: 0; }
        .logo {
            width: 13mm; height: 9mm; padding-top: 4mm; border: 1.2pt solid #FF8300; border-radius: 3.5mm;
            background: #FFF3E6; color: #6B3F23; font-weight: bold; font-size: 13pt; line-height: 1; text-align: center;
        }
        .encabezado .c-logo { width: 13mm; }
        .encabezado .titulo { padding-left: 4mm; }
        h1 { margin: 0; font-size: 16pt; line-height: 1.15; color: #6B3F23; }
        .subtitulo { margin: 1mm 0 0; color: #5C524B; font-size: 8.5pt; }
        .generado { text-align: right; color: #5C524B; font-size: 7.5pt; line-height: 1.5; }
        .generado strong { color: #2B211A; }
        .acento { height: 1.2mm; background: #FF8300; margin-bottom: 4mm; }

        /* Resumen */
        .resumen { width: 100%; border-collapse: separate; border-spacing: 2mm 0; margin: 0 -2mm 5mm; }
        .resumen td {
            width: 14.28%; padding: 2mm 3mm; vertical-align: top; background: #F7F4F1;
            border: 0.6pt solid #D9D2CB; border-radius: 2mm;
        }
        .resumen .valor { display: block; font-size: 15pt; font-weight: bold; color: #6B3F23; line-height: 1.1; }
        .resumen .etiqueta {
            display: block; color: #5C524B; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.3pt;
        }
        .resumen .alerta { background: #FFF3E6; border-color: #FF8300; }
        .resumen .alerta .valor { color: #B3261E; }

        /* Tabla */
        table.talleres { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .talleres thead { display: table-header-group; }
        .talleres th {
            background: #6B3F23; color: #FFFFFF; font-size: 7pt; font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.3pt; text-align: left; padding: 2mm 2mm; border-bottom: 1pt solid #FF8300;
        }
        .talleres td { padding: 1.4mm 2mm; vertical-align: top; border-bottom: 0.5pt solid #D9D2CB; }
        .talleres tr { page-break-inside: avoid; }
        .talleres tr.par td { background: #FBF9F7; }
        .talleres tr.franja td {
            background: #FFF3E6; color: #4F2E19; font-weight: bold; font-size: 8pt; padding: 1.8mm 2mm;
            border-top: 0.8pt solid #FF8300; border-bottom: 0.8pt solid #FF8300;
        }
        .franja .cuenta { color: #5C524B; font-weight: normal; }
        .num { color: #5C524B; text-align: right; }
        .nombre { font-weight: bold; }
        .secundario { display: block; color: #5C524B; font-size: 7pt; margin-top: 0.5mm; }
        .estado { font-weight: bold; }
        .estado--programado { color: #5C524B; }
        .estado--realizado { color: #2E7D32; }
        .estado--no_realizado { color: #B3261E; }
        .auditor { display: block; }
        .baja { color: #5C524B; font-style: italic; }
        .sin-auditor { color: #B3261E; font-weight: bold; }
        .vacio { padding: 8mm; text-align: center; color: #5C524B; border: 0.6pt dashed #5C524B; }

        /* Anchos de columna (suman 100 %) */
        .c-num { width: 3.5%; }
        .c-taller { width: 23%; }
        .c-carrera { width: 15%; }
        .c-edificio { width: 11%; }
        .c-fecha { width: 10%; }
        .c-horario { width: 8%; }
        .c-estado { width: 10%; }
        .c-auditores { width: 19.5%; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="c-logo"><div class="logo">OV</div></td>
            <td class="titulo">
                <h1><?= e($titulo) ?></h1>
                <p class="subtitulo">Todos los talleres activos con su información completa y sus alumnos auditores.</p>
            </td>
            <td class="generado">
                Generado el <strong><?= e($generado) ?></strong><br>
                por <strong><?= e($generadoPor) ?></strong>
            </td>
        </tr>
    </table>
    <div class="acento"></div>

    <table class="resumen">
        <tr>
            <?php foreach ($resumen as [$etiqueta, $valor, $clase]) : ?>
                <td class="<?= e($clase) ?>">
                    <span class="valor"><?= e($valor) ?></span>
                    <span class="etiqueta"><?= e($etiqueta) ?></span>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>

    <?php if ($talleres === []) : ?>
        <p class="vacio">Todavía no hay talleres activos.</p>
    <?php else : ?>
        <table class="talleres">
            <thead>
                <tr>
                    <th class="c-num">#</th>
                    <th class="c-taller">Taller</th>
                    <th class="c-carrera">Carrera</th>
                    <th class="c-edificio">Edificio</th>
                    <th class="c-fecha">Fecha</th>
                    <th class="c-horario">Horario</th>
                    <th class="c-estado">Estado</th>
                    <th class="c-auditores">Auditores</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($franjas as $franja) : ?>
                    <?php
                    $m = $franja['muestra'];
                    $total = count($franja['talleres']);
                    $cubiertos = count(array_filter(
                        $franja['talleres'],
                        static fn (array $t): bool => $t['auditores'] !== []
                    ));
                    $etiqueta = fecha_corta($m['fecha']) . ' · '
                        . grupo_etiqueta($m['grupo_nombre'], $m['hora_inicio'], $m['hora_fin']);
                    ?>
                    <tr class="franja">
                        <td colspan="8">
                            <?= e($etiqueta) ?>
                            <span class="cuenta">
                                — <?= e(cantidad($total, 'taller', 'talleres')) ?>, <?= e($cubiertos) ?> con auditor
                            </span>
                        </td>
                    </tr>
                    <?php foreach ($franja['talleres'] as $i => $t) : ?>
                        <?php $numero++; ?>
                        <tr class="<?= $i % 2 === 1 ? 'par' : '' ?>">
                            <td class="num"><?= e($numero) ?></td>
                            <td><span class="nombre"><?= e($t['nombre']) ?></span></td>
                            <td><?= e($t['carrera']) ?></td>
                            <td><?= e(edificio_etiqueta($t['edificio'], $t['edificio_nombre'], false)) ?></td>
                            <td><?= e(fecha_corta($t['fecha'])) ?></td>
                            <td>
                                <?= e(horario($t['hora_inicio'], $t['hora_fin'])) ?>
                                <span class="secundario"><?= e($t['grupo_nombre']) ?></span>
                            </td>
                            <td>
                                <span class="estado estado--<?= e($t['estado']) ?>">
                                    <?= e($estados[$t['estado']] ?? $t['estado']) ?>
                                </span>
                                <?php if ($t['estado'] === 'no_realizado' && $t['motivo_no_realizado'] !== null) : ?>
                                    <span class="secundario">Motivo: <?= e($t['motivo_no_realizado']) ?></span>
                                <?php endif; ?>
                                <?php if ($t['estado'] !== 'programado' && $t['registrado_por'] !== null) : ?>
                                    <span class="secundario">Registró: <?= e($t['registrado_por']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['auditores'] === []) : ?>
                                    <span class="sin-auditor">Sin auditor</span>
                                <?php else : ?>
                                    <?php foreach ($t['auditores'] as $a) : ?>
                                        <?php $baja = $a['activo'] !== 1; ?>
                                        <span class="auditor<?= $baja ? ' baja' : '' ?>">
                                            <?= e($a['nombre'] . ' ' . $a['apellidos'] . ($baja ? ' (baja)' : '')) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
