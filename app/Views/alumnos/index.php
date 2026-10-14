<?php

/**
 * Alumnos auditores: lista a la izquierda y panel a la derecha (en pantallas angostas, el panel del
 * alumno elegido va arriba). Sin alumno elegido, el panel sirve para darlos de alta.
 * Con JavaScript la lista se filtra en el navegador y el panel cambia sin recargar la página.
 *
 * @phpstan-import-type AlumnoListado from \App\Models\Alumno
 * @phpstan-import-type AlumnoFila from \App\Models\Alumno
 * @var list<AlumnoListado> $alumnos
 * @var array{texto: string, estado: string, talleres: string, horario: string, pagina: string} $filtros
 * @var AlumnoFila|null $seleccionado
 * @var list<array<string, mixed>>|null $talleres
 * @var list<array<string, mixed>>|null $disponibles
 * @var int $listaMax
 * @var int $porPagina
 * @var list<array{id: int, nombre: string, hora_inicio: string, hora_fin: string}> $horarios
 */

$vista = app()->view();
$seleccionadoId = $seleccionado['id'] ?? null;
?>
<div class="page-header">
    <div>
        <h1>Alumnos</h1>
        <p>
            Los alumnos que auditan talleres. No entran al sistema: aquí los das de alta con su horario y ves
            qué talleres audita cada uno.
        </p>
    </div>
    <a class="btn btn--primary" href="<?= e(url('/alumnos', $filtros) . '#alta') ?>" data-panel-enlace
       data-conserva-filtros>
        <?= icono('usuario-mas') ?> Agregar alumnos
    </a>
</div>

<div class="layout-roster<?= $seleccionado !== null ? ' layout-roster--selected' : '' ?>" data-roster>
    <section class="layout-roster__list" aria-labelledby="t-lista">
        <h2 class="sr-only" id="t-lista">Lista de alumnos</h2>
        <?= $vista->partial(
            'alumnos/_lista',
            compact('alumnos', 'filtros', 'seleccionadoId', 'horarios', 'porPagina')
        ) ?>
    </section>
    <div class="layout-roster__panel" data-panel>
        <?php if ($seleccionado === null) : ?>
            <?= $vista->partial('alumnos/_alta', compact('filtros', 'listaMax', 'horarios')) ?>
        <?php else : ?>
            <?= $vista->partial('alumnos/_panel', [
                'alumno' => $seleccionado,
                'talleres' => $talleres ?? [],
                'disponibles' => $disponibles ?? [],
                'filtros' => $filtros,
                'horarios' => $horarios,
            ]) ?>
        <?php endif; ?>
    </div>
</div>
