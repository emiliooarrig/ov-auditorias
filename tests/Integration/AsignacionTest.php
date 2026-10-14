<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Asignacion;
use DomainException;
use Tests\Support\IntegrationTestCase;

/**
 * Asignación manual taller–alumno desde el panel del alumno o el detalle del taller (RF-04, RF-05;
 * reglas 2 y 6; un alumno no puede tener dos talleres a la misma hora).
 */
final class AsignacionTest extends IntegrationTestCase
{
    private function contarAsignaciones(): int
    {
        return $this->contar('SELECT COUNT(*) FROM asignaciones');
    }

    public function testDesdeElPanelDelAlumnoVuelveAlPanelConSusFiltros(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $taller = $this->crearActividad($admin, ['nombre' => 'Robótica']);

        $response = $this->post('/asignaciones', [
            'alumno_id' => (string) $ana,
            'actividad_id' => (string) $taller,
            'volver' => 'alumno',
            'texto' => 'lo',
            'talleres' => 'sin',
        ]);

        $this->assertRedirige("/alumnos/{$ana}?texto=lo&talleres=sin", $response);
        $fila = $this->app->db()->fetchOne('SELECT alumno_id, actividad_id, origen, asignado_por FROM asignaciones');
        $this->assertSame(['alumno_id' => $ana, 'actividad_id' => $taller, 'origen' => 'manual', 'asignado_por' => $admin], $fila);
        $this->assertStringContainsString('Se asignó «Robótica» a Ana López.', $this->get("/alumnos/{$ana}")->body());
    }

    public function testDesdeElDetalleDelTallerSeAgreganVariosAlumnos(): void
    {
        $admin = $this->entrarComoAdministrador();
        $taller = $this->crearActividad($admin, ['nombre' => 'Compartido']);
        $ana = $this->crearAlumno('Ana', 'López');
        $luis = $this->crearAlumno('Luis', 'Pérez');
        $this->crearAlumno('Eva', 'Soto', false);

        $detalle = $this->get("/actividades/{$taller}")->body();
        $this->assertStringContainsString('López, Ana — Todo el día — sin talleres', $detalle);
        $this->assertStringNotContainsString('Soto, Eva', $detalle, 'Los alumnos dados de baja no se ofrecen');

        foreach ([$ana, $luis] as $alumno) {
            $response = $this->post('/asignaciones', [
                'alumno_id' => (string) $alumno,
                'actividad_id' => (string) $taller,
                'volver' => 'taller',
            ]);
            $this->assertRedirige("/actividades/{$taller}", $response);
        }

        $this->assertSame(2, $this->contarAsignaciones());
        $detalle = $this->get("/actividades/{$taller}")->body();
        $this->assertStringContainsString('Ana López', $detalle);
        $this->assertStringContainsString('Luis Pérez', $detalle);
        $this->assertStringContainsString('/alumnos/' . $ana, $detalle);
        $this->assertStringContainsString('No hay alumnos activos con horario que cubra este taller y libres', $detalle);
        // El panel central los muestra como auditores del taller.
        $this->assertStringContainsString('Ana López, Luis Pérez', $this->get('/')->body());
    }

    public function testNoSeAsignaDosVecesNiADosTalleresALaMismaHora(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $robotica = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 2, 'fecha' => '2026-10-15']);
        $pintura = $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 2, 'fecha' => '2026-10-15']);
        $otroDia = $this->crearActividad($admin, ['nombre' => 'Oratoria', 'grupo' => 2, 'fecha' => '2026-10-16']);
        $asignar = fn (int $taller) => $this->post('/asignaciones', [
            'alumno_id' => (string) $ana,
            'actividad_id' => (string) $taller,
            'volver' => 'alumno',
        ]);

        $asignar($robotica);
        $asignar($robotica);
        $this->assertStringContainsString('Ana López ya audita ese taller.', $this->get("/alumnos/{$ana}")->body());
        $asignar($pintura);
        $this->assertStringContainsString(
            'Ana López ya tiene otro taller a esa hora: Robótica.',
            $this->get("/alumnos/{$ana}")->body()
        );
        $asignar($otroDia);

        $this->assertSame(2, $this->contarAsignaciones());
        // La lista para asignar a mano ya no ofrece la franja ocupada.
        $html = $this->get("/alumnos/{$ana}")->body();
        $this->assertDoesNotMatchRegularExpression(
            '#<select id="asignar-taller"(?:(?!</select>).)*<option value="' . $pintura . '">#s',
            $html
        );
    }

    public function testSoloAlumnosActivosYTalleresProgramados(): void
    {
        $admin = $this->entrarComoAdministrador();
        $baja = $this->crearAlumno('Eva', 'Soto', false);
        $ana = $this->crearAlumno('Ana', 'López');
        $taller = $this->crearActividad($admin);
        $realizado = $this->crearActividad($admin, ['estado' => 'realizado', 'grupo' => 2]);
        $desactivado = $this->crearActividad($admin, ['activo' => 0, 'grupo' => 3]);

        $intentos = [
            [$baja, $taller, 'No se pueden asignar talleres a un alumno dado de baja.'],
            [99999, $taller, 'Elige un alumno de la lista.'],
            [$ana, $realizado, 'Ese taller ya no está programado'],
            [$ana, $desactivado, 'Elige un taller de la lista.'],
            [$ana, 0, 'Elige un taller de la lista.'],
        ];
        foreach ($intentos as [$alumno, $actividad, $mensaje]) {
            $this->post('/asignaciones', ['alumno_id' => (string) $alumno, 'actividad_id' => (string) $actividad]);
            $this->assertStringContainsString($mensaje, $this->get('/asignaciones')->body() . $this->get("/alumnos/{$ana}")->body());
        }

        $this->assertSame(0, $this->contarAsignaciones());
    }

    public function testSoloSeQuitaUnaAsignacionMientrasElTallerEstaProgramado(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $programado = $this->crearActividad($admin);
        $realizado = $this->crearActividad($admin, ['estado' => 'realizado', 'grupo' => 2]);
        $deProgramado = $this->asignar($programado, $ana, $admin);
        $deRealizado = $this->asignar($realizado, $ana, $admin);

        $response = $this->post("/asignaciones/{$deRealizado}/quitar", ['volver' => 'taller', 'actividad_id' => (string) $realizado]);
        $this->assertRedirige('/actividades/' . $realizado, $response);
        $this->assertSame(2, $this->contarAsignaciones());
        $detalle = $this->get('/actividades/' . $realizado)->body();
        $this->assertStringContainsString('Solo se puede quitar un auditor mientras', $detalle);
        $this->assertStringNotContainsString('/quitar', $detalle);

        $response = $this->post("/asignaciones/{$deProgramado}/quitar", ['volver' => 'alumno', 'alumno_id' => (string) $ana]);
        $this->assertRedirige('/alumnos/' . $ana, $response);
        $this->assertSame(1, $this->contarAsignaciones());
    }

    public function testElModeloRechazaQuitarDeUnTallerNoProgramado(): void
    {
        $admin = $this->crearAdministrador();
        $taller = $this->crearActividad($admin, ['estado' => 'no_realizado', 'motivo_no_realizado' => 'Lluvia']);
        $asignacion = $this->asignar($taller, $this->crearAlumno(), $admin);

        $this->expectException(DomainException::class);
        (new Asignacion($this->app->db()))->quitar($asignacion);
    }

    public function testQuitarTodasDejaSoloLasDeTalleresRealizadosONoRealizados(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $luis = $this->crearAlumno('Luis', 'Pérez');
        $robotica = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $pintura = $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 2]);
        $realizado = $this->crearActividad($admin, ['grupo' => 3, 'estado' => 'realizado']);
        $noRealizado = $this->crearActividad($admin, ['grupo' => 4, 'estado' => 'no_realizado', 'motivo_no_realizado' => 'Lluvia']);
        $this->asignar($robotica, $ana, $admin);
        $this->asignar($robotica, $luis, $admin, 'aleatoria');
        $this->asignar($pintura, $ana, $admin, 'aleatoria');
        $conservadas = [$this->asignar($realizado, $ana, $admin), $this->asignar($noRealizado, $luis, $admin)];

        $html = $this->get('/asignaciones')->body();
        $this->assertStringContainsString('Quitar todas las asignaciones', $html);
        $this->assertStringContainsString('data-confirmar="¿Quitar las 3 asignaciones?"', $html);

        $this->assertRedirige('/asignaciones', $this->post('/asignaciones/quitar-todas', ['confirmar' => '1']));

        $this->assertSame($conservadas, array_map('intval', array_column(
            $this->app->db()->fetchAll('SELECT id FROM asignaciones ORDER BY id'),
            'id'
        )));
        $html = $this->get('/asignaciones')->body();
        $this->assertStringContainsString('Se quitaron 3 asignaciones. Usa la asignación al azar para volver a repartir.', $html);
        $this->assertStringNotContainsString('Quitar todas las asignaciones', $html, 'Sin nada que quitar no aparece');

        $this->post('/asignaciones/quitar-todas', ['confirmar' => '1']);
        $this->assertStringContainsString(
            'No había asignaciones de talleres programados que quitar.',
            $this->get('/asignaciones')->body()
        );
        $this->assertSame(2, $this->contarAsignaciones());
    }

    public function testQuitarTodasExigeConfirmacion(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->asignar($this->crearActividad($admin), $this->crearAlumno(), $admin);

        $this->assertRedirige('/asignaciones', $this->post('/asignaciones/quitar-todas'));

        $this->assertSame(1, $this->contarAsignaciones());
        $this->assertStringContainsString(
            'Marca la casilla de confirmación para quitar todas las asignaciones.',
            $this->get('/asignaciones')->body()
        );
        // Sin sesión no se puede.
        $this->post('/logout');
        $this->assertRedirige('/login', $this->post('/asignaciones/quitar-todas', ['confirmar' => '1']));
        $this->assertSame(1, $this->contarAsignaciones());
    }

    public function testLaBaseImpideLaMismaParejaDosVeces(): void
    {
        $admin = $this->crearAdministrador();
        $taller = $this->crearActividad($admin);
        $ana = $this->crearAlumno();
        $this->asignar($taller, $ana, $admin);

        $this->expectException(\PDOException::class);
        $this->asignar($taller, $ana, $admin);
    }
}
