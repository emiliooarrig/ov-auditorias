<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Horario de los alumnos: Matutino (07:00–14:00), Vespertino (13:00–19:00) o Todo el día. Un alumno solo
 * audita talleres que caben en su horario, ni a mano ni al azar; sin horario no recibe talleres.
 * Grupos de datos.sql: Taller 1 a 4 de 10:00 a 14:00, Taller 5 de 15:00 a 16:00 y Taller 6 de 16:00 a 17:00.
 */
final class HorarioAlumnoTest extends IntegrationTestCase
{
    /**
     * @return list<array{alumno_id: int, actividad_id: int}>
     */
    private function asignaciones(): array
    {
        /** @var list<array{alumno_id: int, actividad_id: int}> */
        return $this->app->db()->fetchAll('SELECT alumno_id, actividad_id FROM asignaciones ORDER BY id');
    }

    public function testNoSeAsignaAManoUnTallerFueraDelHorario(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López', true, 'Matutino');
        $luis = $this->crearAlumno('Luis', 'Pérez', true, 'Vespertino');
        $manana = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 3]);   // 12:00–13:00
        $comun = $this->crearActividad($admin, ['nombre' => 'Oratoria', 'grupo' => 4]);    // 13:00–14:00
        $tarde = $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 6]);     // 16:00–17:00

        $this->post('/asignaciones', ['alumno_id' => (string) $ana, 'actividad_id' => (string) $tarde, 'volver' => 'alumno']);
        $this->assertStringContainsString(
            'Ana López tiene horario Matutino (07:00–14:00) y el taller es de 16:00 a 17:00: '
            . 'elige un taller dentro de su horario.',
            $this->get("/alumnos/{$ana}")->body()
        );
        $this->post('/asignaciones', ['alumno_id' => (string) $luis, 'actividad_id' => (string) $manana, 'volver' => 'taller']);
        $this->assertStringContainsString('Luis Pérez tiene horario Vespertino', $this->get("/actividades/{$manana}")->body());
        $this->assertSame([], $this->asignaciones());

        // Dentro del horario sí; los límites cuentan como dentro: el taller de 13:00 a 14:00 cabe en el
        // matutino (termina a las 14:00) y en el vespertino (empieza a las 13:00).
        $this->post('/asignaciones', ['alumno_id' => (string) $ana, 'actividad_id' => (string) $manana]);
        $this->post('/asignaciones', ['alumno_id' => (string) $luis, 'actividad_id' => (string) $tarde]);
        $this->post('/asignaciones', ['alumno_id' => (string) $ana, 'actividad_id' => (string) $comun]);
        $this->post('/asignaciones', ['alumno_id' => (string) $luis, 'actividad_id' => (string) $comun]);
        $this->assertSame(
            [
                ['alumno_id' => $ana, 'actividad_id' => $manana],
                ['alumno_id' => $luis, 'actividad_id' => $tarde],
                ['alumno_id' => $ana, 'actividad_id' => $comun],
                ['alumno_id' => $luis, 'actividad_id' => $comun],
            ],
            $this->asignaciones()
        );
    }

    public function testLasListasParaAsignarAManoSoloOfrecenLoQueCabeEnElHorario(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López', true, 'Matutino');
        $this->crearAlumno('Luis', 'Pérez', true, 'Vespertino');
        $this->crearAlumno('Eva', 'Soto', true, 'Todo el día');
        $manana = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $tarde = $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 5]);

        $panel = $this->get("/alumnos/{$ana}")->body();
        $this->assertSame(1, preg_match('#<select id="asignar-taller".*?</select>#s', $panel, $select));
        $this->assertStringContainsString('Robótica', $select[0]);
        $this->assertStringNotContainsString('Pintura', $select[0]);
        $this->assertStringContainsString('Matutino (07:00–14:00)', $panel);

        $detalle = $this->get("/actividades/{$tarde}")->body();
        $this->assertSame(1, preg_match('#<select id="agregar-alumno".*?</select>#s', $detalle, $select));
        $this->assertStringContainsString('Pérez, Luis — Vespertino', $select[0]);
        $this->assertStringContainsString('Soto, Eva — Todo el día', $select[0]);
        $this->assertStringNotContainsString('López, Ana', $select[0]);
        $this->assertStringContainsString('López, Ana — Matutino', $this->get("/actividades/{$manana}")->body());
    }

    public function testUnAlumnoSinHorarioNoRecibeTalleres(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López', true, null);
        $taller = $this->crearActividad($admin, ['nombre' => 'Robótica']);

        $this->post('/asignaciones', ['alumno_id' => (string) $ana, 'actividad_id' => (string) $taller]);

        $this->assertSame([], $this->asignaciones());
        $panel = $this->get("/alumnos/{$ana}")->body();
        $this->assertStringContainsString('Registra el horario de Ana López', $panel);
        $this->assertStringContainsString('Sin horario', $panel);
        $this->assertStringNotContainsString('id="asignar-taller"', $panel);
        $this->assertStringContainsString('<details class="disclosure" open>', $panel, 'El formulario para registrarlo queda abierto');
        $this->assertStringNotContainsString('López, Ana', $this->get("/actividades/{$taller}")->body());
        $this->assertMatchesRegularExpression('#Sin horario</span>#', $this->get('/alumnos')->body());
    }

    public function testElSorteoRespetaElHorarioYAvisaDeLosAlumnosSinHorario(): void
    {
        $admin = $this->entrarComoAdministrador();
        $matutinos = [
            $this->crearAlumno('Ana', 'López', true, 'Matutino'),
            $this->crearAlumno('Luis', 'Pérez', true, 'Matutino'),
        ];
        $vespertino = $this->crearAlumno('Eva', 'Soto', true, 'Vespertino');
        $this->crearAlumno('Iván', 'Ruiz', true, null);
        $manana = array_map(fn (int $i): int => $this->crearActividad($admin, ['nombre' => "Mañana {$i}", 'grupo' => 1]), range(1, 3));
        $tarde = $this->crearActividad($admin, ['nombre' => 'Tarde', 'grupo' => 5]);

        $this->assertRedirige('/asignaciones?auditores=1', $this->post('/asignaciones/aleatoria', ['auditores_por_taller' => '1']));

        // Dos matutinos para tres talleres a las 10:00: uno queda sin cubrir. El vespertino toma el de la tarde.
        $asignaciones = $this->asignaciones();
        $this->assertCount(3, $asignaciones);
        foreach ($asignaciones as $a) {
            $this->assertContains($a['alumno_id'], $a['actividad_id'] === $tarde ? [$vespertino] : $matutinos);
        }
        $this->assertSame(0, $this->contar(
            'SELECT COUNT(*) FROM asignaciones s JOIN alumnos al ON al.id = s.alumno_id
             JOIN horarios_alumno h ON h.id = al.horario_id JOIN actividades a ON a.id = s.actividad_id
             WHERE NOT (h.hora_inicio <= a.hora_inicio AND a.hora_fin <= h.hora_fin)'
        ));
        $this->assertCount(2, array_intersect($manana, array_column($asignaciones, 'actividad_id')));

        $html = $this->get('/asignaciones')->body();
        $this->assertMatchesRegularExpression(
            '/Quedó sin auditor Mañana [123]: a esa hora no hay alumnos libres cuyo horario la cubra\./',
            $html
        );
        $this->assertStringContainsString('No participó por no tener horario registrado Iván Ruiz.', $html);
        $this->assertStringContainsString('Alumnos sin horario', $html);
        $this->assertStringContainsString('Ruiz, Iván', $html);
    }

    public function testSinNingunAlumnoConHorarioNoSeSortea(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->crearAlumno('Ana', 'López', true, null);
        $this->crearActividad($admin);

        $html = $this->get('/asignaciones')->body();
        $this->assertStringContainsString('Ningún alumno tiene horario: regístralo en su panel.', $html);

        $this->post('/asignaciones/aleatoria', ['auditores_por_taller' => '1']);
        $this->assertStringContainsString('Ningún alumno activo tiene horario registrado.', $this->get('/asignaciones')->body());
        $this->assertSame([], $this->asignaciones());
    }

    public function testLasAsignacionesFueraDeHorarioSeAvisanYSeQuitanDeUnaVez(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López', true, 'Matutino');
        $luis = $this->crearAlumno('Luis', 'Pérez', true, 'Vespertino');
        $tarde = $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 5]);
        $manana = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $realizado = $this->crearActividad($admin, ['nombre' => 'Oratoria', 'grupo' => 6, 'estado' => 'realizado']);
        // Asignaciones hechas antes de la regla: dos fuera de horario (una ya realizada) y una en horario.
        $this->asignar($tarde, $ana, $admin, 'aleatoria');
        $this->asignar($realizado, $ana, $admin);
        $this->asignar($tarde, $luis, $admin);
        $this->asignar($manana, $ana, $admin);

        $html = $this->get('/asignaciones')->body();
        $this->assertStringContainsString('Asignaciones fuera de horario', $html);
        $this->assertStringContainsString('Ver 1 asignación', $html);
        $this->assertStringContainsString('Quitar la asignación', $html);
        $this->assertStringContainsString('Fuera de su horario', $this->get("/alumnos/{$ana}")->body());
        $this->assertStringContainsString('Fuera de su horario', $this->get("/actividades/{$tarde}")->body());

        $this->assertRedirige('/asignaciones', $this->post('/asignaciones/fuera-de-horario/quitar'));

        // Solo se quita la del taller programado; la del realizado queda como registro.
        $this->assertEqualsCanonicalizing(
            [
                ['alumno_id' => $ana, 'actividad_id' => $realizado],
                ['alumno_id' => $luis, 'actividad_id' => $tarde],
                ['alumno_id' => $ana, 'actividad_id' => $manana],
            ],
            $this->asignaciones()
        );
        $html = $this->get('/asignaciones')->body();
        $this->assertStringContainsString('Se quitó 1 asignación fuera de horario.', $html);
        $this->assertStringNotContainsString('Ver 1 asignación', $html);
    }

    public function testCambiarElHorarioNoPuedeDejarTalleresProgramadosFuera(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López', true, 'Todo el día');
        $this->asignar($this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 5]), $ana, $admin);
        $datos = ['nombre' => 'Ana', 'apellidos' => 'López'];

        $this->post("/alumnos/{$ana}", $datos + ['horario_id' => (string) $this->horarioId('Matutino')]);

        $html = $this->get("/alumnos/{$ana}")->body();
        $this->assertStringContainsString(
            'Con ese horario quedaría fuera el taller Pintura. Quítaselos antes de cambiar su horario.',
            $html
        );
        $this->assertStringContainsString('<details class="disclosure" open>', $html);
        $this->assertSame($this->horarioId('Todo el día'), $this->contar('SELECT horario_id FROM alumnos'));

        $this->post("/alumnos/{$ana}", $datos + ['horario_id' => (string) $this->horarioId('Vespertino')]);
        $this->assertStringContainsString('Datos actualizados.', $this->get("/alumnos/{$ana}")->body());
        $this->assertSame($this->horarioId('Vespertino'), $this->contar('SELECT horario_id FROM alumnos'));
    }
}
