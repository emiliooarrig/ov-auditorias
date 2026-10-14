<?php

/**
 * Directorio de alumnos auditores para PDF (Dompdf), carta vertical, para imprimir y repartir: un renglón
 * por alumno, ordenados por apellido y separados por letra, con todos sus talleres (hora, taller con su
 * carrera y edificio). Así cada alumno se encuentra por su apellido sin buscar en la tabla de talleres.
 * Como el reporte de talleres: sin métricas ni quién o cuándo lo generó, y con los estilos aquí mismo.
 *
 * @phpstan-import-type DirectorioFila from \App\Models\Alumno
 * @var list<DirectorioFila> $alumnos
 * @var string $titulo
 */

// Inicial del primer apellido sin acento ("Álvarez" va en la A), igual que el orden de MySQL.
$inicial = static fn (string $apellidos): string => mb_strtoupper(mb_substr(
    (string) preg_replace('/\p{Mn}/u', '', (string) Normalizer::normalize(trim($apellidos), Normalizer::FORM_D)),
    0,
    1
));
// Con talleres en más de un día, cada taller dice también su fecha.
$fechas = [];
foreach ($alumnos as $al) {
    foreach ($al['talleres'] as $t) {
        $fechas[substr($t['fecha'], 0, 10)] = true;
    }
}
$conFecha = count($fechas) > 1;
$letra = null;
$par = false;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= e($titulo) ?></title>
    <style>
        @page { size: letter portrait; margin: 14mm 12mm 16mm; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 9pt; line-height: 1.3; color: #2B211A; }

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
        .acento { height: 1.2mm; background: #FF8300; margin-bottom: 5mm; }

        /* Directorio: un renglón por alumno; sus talleres van en una tabla anidada alineada a las columnas. */
        table.directorio { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .directorio thead { display: table-header-group; }
        .directorio th {
            background: #6B3F23; color: #FFFFFF; font-size: 7.5pt; font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.3pt; text-align: left; padding: 2mm; border-bottom: 1pt solid #FF8300;
        }
        .directorio > tbody > tr { page-break-inside: avoid; }
        .directorio td { vertical-align: top; }
        .directorio td.c-alumno { padding: 1.1mm 2mm; border-bottom: 0.5pt solid #D9D2CB; }
        .directorio td.c-talleres { padding: 0; border-bottom: 0.5pt solid #D9D2CB; }
        .directorio tr.par td { background: #FBF9F7; }
        .directorio tr.letra td {
            background: #FFF3E6; color: #6B3F23; font-weight: bold; font-size: 12pt; padding: 1.2mm 2mm;
            border-top: 0.8pt solid #FF8300; border-bottom: 0.8pt solid #FF8300; page-break-after: avoid;
        }
        .apellidos { font-weight: bold; font-size: 9.5pt; }
        table.sesiones { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sesiones td { padding: 0.8mm 2mm; vertical-align: top; line-height: 1.2; }
        .sesiones tr + tr td { border-top: 0.5pt dotted #D9D2CB; }
        .hora { font-weight: bold; white-space: nowrap; }
        .secundario { color: #5C524B; font-size: 7pt; }
        .edificio { font-weight: bold; }
        .sin-taller { padding: 1.1mm 2mm; color: #5C524B; font-style: italic; }
        .vacio { padding: 8mm; text-align: center; color: #5C524B; border: 0.6pt dashed #5C524B; }

        /* Anchos: alumno 27 % y talleres 73 % (hora 12, taller 43, edificio 18 del total). */
        .c-alumno { width: 27%; }
        .c-hora { width: 12%; }
        .c-taller { width: 43%; }
        .c-edificio { width: 18%; }
        .sesiones .s-hora { width: 16.44%; }
        .sesiones .s-taller { width: 58.9%; }
        .sesiones .s-edificio { width: 24.66%; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="c-logo"><div class="logo">OV</div></td>
            <td class="titulo"><h1><?= e($titulo) ?></h1></td>
        </tr>
    </table>
    <div class="acento"></div>

    <?php if ($alumnos === []) : ?>
        <p class="vacio">Todavía no hay alumnos.</p>
    <?php else : ?>
        <table class="directorio">
            <thead>
                <tr>
                    <th class="c-alumno">Alumno</th>
                    <th class="c-hora">Hora</th>
                    <th class="c-taller">Taller y carrera</th>
                    <th class="c-edificio">Edificio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alumnos as $al) : ?>
                    <?php
                    $actual = $inicial($al['apellidos']);
                    $nuevaLetra = $actual !== $letra;
                    if ($nuevaLetra) {
                        $letra = $actual;
                        $par = false;
                    }
                    ?>
                    <?php if ($nuevaLetra) : ?>
                        <tr class="letra"><td colspan="4"><?= e($letra) ?></td></tr>
                    <?php endif; ?>
                    <tr class="<?= $par ? 'par' : '' ?>">
                        <td class="c-alumno">
                            <span class="apellidos"><?= e($al['apellidos']) ?></span>, <?= e($al['nombre']) ?>
                        </td>
                        <td class="c-talleres" colspan="3">
                            <?php if ($al['talleres'] === []) : ?>
                                <div class="sin-taller">Sin taller asignado</div>
                            <?php else : ?>
                                <table class="sesiones">
                                    <?php foreach ($al['talleres'] as $t) : ?>
                                        <?php
                                        $hora = horario($t['hora_inicio'], $t['hora_fin']);
                                        $fecha = $conFecha ? fecha_corta($t['fecha']) : '';
                                        $edificio = edificio_etiqueta($t['edificio'], $t['edificio_nombre'], false);
                                        ?>
                                        <tr>
                                            <td class="s-hora">
                                                <span class="hora"><?= e($hora) ?></span>
                                                <?php if ($fecha !== '') : ?>
                                                    <br><span class="secundario"><?= e($fecha) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="s-taller">
                                                <?= e($t['nombre']) ?>
                                                <br><span class="secundario"><?= e($t['carrera']) ?></span>
                                            </td>
                                            <td class="s-edificio">
                                                <span class="edificio"><?= e($edificio) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php $par = !$par; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
