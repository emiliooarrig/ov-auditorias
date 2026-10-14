<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Response;
use App\Models\Asignacion;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\IntegrationTestCase;

/**
 * Asignación aleatoria: cada alumno activo recibe al menos un taller y ningún taller programado queda
 * sin auditor, repartido parejo, sin choques de horario y sin tocar lo que ya estaba asignado.
 */
final class SorteoTest extends IntegrationTestCase
{
    /**
     * @return array<int, int> Talleres por alumno (solo alumnos con alguno).
     */
    private function cargaPorAlumno(): array
    {
        $filas = $this->app->db()->fetchAll('SELECT alumno_id, COUNT(*) AS n FROM asignaciones GROUP BY alumno_id');

        return array_map('intval', array_column($filas, 'n', 'alumno_id'));
    }

    /**
     * @return array<int, int> Auditores por taller (solo talleres con alguno).
     */
    private function auditoresPorTaller(): array
    {
        $filas = $this->app->db()->fetchAll('SELECT actividad_id, COUNT(*) AS n FROM asignaciones GROUP BY actividad_id');

        return array_map('intval', array_column($filas, 'n', 'actividad_id'));
    }

    /**
     * Pulsa "Asignar al azar" con los auditores por taller indicados (texto, como llega del formulario).
     */
    private function sortear(string $auditores = '1'): Response
    {
        return $this->post('/asignaciones/aleatoria', ['auditores_por_taller' => $auditores]);
    }

    private function sinChoquesDeHorario(): void
    {
        $this->assertSame(0, $this->contar(
            'SELECT COUNT(*) FROM (
                SELECT s.alumno_id FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                GROUP BY s.alumno_id, a.fecha, a.grupo_id HAVING COUNT(*) > 1) choques'
        ));
    }

    public function testMasTalleresQueAlumnos(): void
    {
        $admin = $this->entrarComoAdministrador();
        $alumnos = array_map(fn (int $i): int => $this->crearAlumno('Alumno', "Número {$i}"), range(1, 3));
        $talleres = [];
        foreach (range(1, 8) as $i) {
            $talleres[] = $this->crearActividad($admin, [
                'nombre' => "Taller {$i}",
                'grupo' => ($i % 6) + 1,
                'fecha' => '2026-11-0' . (1 + intdiv($i, 6)),
            ]);
        }

        $this->assertRedirige('/asignaciones?auditores=1', $this->sortear());

        $this->assertCount(8, $this->auditoresPorTaller(), 'Ningún taller queda sin auditor');
        $this->assertSame([1], array_values(array_unique($this->auditoresPorTaller())));
        $carga = $this->cargaPorAlumno();
        $this->assertCount(3, $carga);
        $this->assertLessThanOrEqual(1, max($carga) - min($carga), 'Reparto parejo: 3, 3 y 2');
        $this->sinChoquesDeHorario();
        $this->assertSame(8, $this->contar("SELECT COUNT(*) FROM asignaciones WHERE origen = 'aleatoria'"));
        $this->assertSame(8, $this->contar('SELECT COUNT(*) FROM asignaciones WHERE asignado_por = ?', [$admin]));
        $this->assertSame([], array_diff($alumnos, array_keys($carga)));

        $html = $this->get('/asignaciones')->body();
        $this->assertStringContainsString('Se crearon 8 asignaciones al azar. 8 talleres recibieron su primer auditor.', $html);
        $this->assertStringContainsString('3 alumnos recibieron su primer taller.', $html);
        $this->assertStringContainsString('Todos los talleres programados tienen al menos un auditor.', $html);
    }

    public function testMasAlumnosQueTalleres(): void
    {
        $admin = $this->entrarComoAdministrador();
        foreach (range(1, 7) as $i) {
            $this->crearAlumno('Alumno', "Número {$i}");
        }
        foreach (range(1, 3) as $i) {
            $this->crearActividad($admin, ['nombre' => "Taller {$i}", 'grupo' => $i]);
        }

        $this->sortear();

        $carga = $this->cargaPorAlumno();
        $this->assertCount(7, $carga, 'Cada alumno recibe un taller');
        $this->assertSame([1], array_values(array_unique($carga)));
        $auditores = $this->auditoresPorTaller();
        $this->assertCount(3, $auditores);
        $this->assertLessThanOrEqual(1, max($auditores) - min($auditores), 'Reparto parejo: 3, 2 y 2');
    }

    public function testConservaLoAsignadoYSoloCompletaLoQueFalta(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $luis = $this->crearAlumno('Luis', 'Pérez');
        $eva = $this->crearAlumno('Eva', 'Soto');
        $robotica = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $pintura = $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 2]);
        $this->asignar($robotica, $ana, $admin);
        $this->asignar($pintura, $ana, $admin);

        (new Asignacion($this->app->db()))->sortear($admin, 1, new Randomizer(new Mt19937(7)));

        // Ana ya tenía dos y ningún taller estaba vacío: solo Luis y Eva reciben uno.
        $this->assertSame(['manual', 'manual'], array_column(
            $this->app->db()->fetchAll('SELECT origen FROM asignaciones WHERE alumno_id = ? ORDER BY id', [$ana]),
            'origen'
        ));
        $carga = $this->cargaPorAlumno();
        $this->assertSame([$ana => 2, $luis => 1, $eva => 1], [$ana => $carga[$ana], $luis => $carga[$luis], $eva => $carga[$eva]]);
        $this->assertSame([2, 2], array_values($this->auditoresPorTaller()));

        // Una segunda vuelta ya no tiene nada que hacer.
        $this->sortear();
        $this->assertSame(4, $this->contar('SELECT COUNT(*) FROM asignaciones'));
        $this->assertStringContainsString('No hizo falta asignar nada', $this->get('/asignaciones')->body());
    }

    public function testNadieQuedaEnDosTalleresALaMismaHoraAunqueQueden(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->crearAlumno('Ana', 'López');
        $this->crearAlumno('Luis', 'Pérez');
        foreach (['A', 'B', 'C'] as $letra) {
            $this->crearActividad($admin, ['nombre' => "Mismo horario {$letra}", 'grupo' => 4, 'fecha' => '2026-11-10']);
        }

        $this->sortear();

        $this->sinChoquesDeHorario();
        $this->assertCount(2, $this->auditoresPorTaller());
        $html = $this->get('/asignaciones')->body();
        $this->assertMatchesRegularExpression('/Quedó sin auditor Mismo horario [ABC]: a esa hora no hay alumnos libres cuyo horario la cubra\./', $html);
        $this->assertStringContainsString('alert--aviso', $html);
    }

    public function testIgnoraAlumnosDadosDeBajaYTalleresQueYaNoEstanProgramados(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $this->crearAlumno('Eva', 'Soto', false);
        $programado = $this->crearActividad($admin, ['grupo' => 1]);
        $this->crearActividad($admin, ['grupo' => 2, 'estado' => 'realizado']);
        $this->crearActividad($admin, ['grupo' => 3, 'activo' => 0]);

        $this->sortear();

        $this->assertSame(
            [['alumno_id' => $ana, 'actividad_id' => $programado]],
            $this->app->db()->fetchAll('SELECT alumno_id, actividad_id FROM asignaciones')
        );
    }

    public function testSinAlumnosOSinTalleresNoHaceNada(): void
    {
        $admin = $this->entrarComoAdministrador();

        $html = $this->get('/asignaciones')->body();
        $this->assertStringContainsString('Primero agrega alumnos.', $html);
        $this->assertMatchesRegularExpression('#<button type="submit" class="btn btn--primary btn--block"\s+disabled#', $html);

        $this->sortear();
        $this->assertStringContainsString('No hay alumnos activos.', $this->get('/asignaciones')->body());

        $this->crearAlumno();
        $this->crearActividad($admin, ['estado' => 'realizado']);
        $this->sortear();
        $this->assertStringContainsString('No hay talleres programados que asignar.', $this->get('/asignaciones')->body());
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM asignaciones'));
    }

    public function testCadaTallerRecibeLosAuditoresPedidos(): void
    {
        $admin = $this->entrarComoAdministrador();
        $alumnos = array_map(fn (int $i): int => $this->crearAlumno('Alumno', "Número {$i}"), range(1, 6));
        foreach (range(1, 3) as $i) {
            $this->crearActividad($admin, ['nombre' => "Taller {$i}", 'grupo' => $i]);
        }

        $this->assertRedirige('/asignaciones?auditores=2', $this->sortear('2'));

        $this->assertSame([2, 2, 2], array_values($this->auditoresPorTaller()));
        $this->assertSame(array_fill_keys($alumnos, 1), $this->cargaPorAlumno());
        $html = $this->get('/asignaciones', ['auditores' => '2'])->body();
        $this->assertStringContainsString('Se crearon 6 asignaciones al azar.', $html);
        $this->assertStringContainsString('Ahora cada alumno audita 1 taller.', $html);
        $this->assertStringContainsString('alert--exito', $html);
        $this->assertMatchesRegularExpression('#name="auditores_por_taller"\s+value="2"#', $html);
        $this->assertStringContainsString('Todos los talleres programados tienen 2 auditores o más.', $html);

        // Con tres por taller, cada taller recibe uno más y la carga se reparte entre 1 y 2.
        $this->sortear('3');

        $this->assertSame([3, 3, 3], array_values($this->auditoresPorTaller()));
        $carga = $this->cargaPorAlumno();
        $this->assertSame([1, 2], [min($carga), max($carga)]);
        $this->sinChoquesDeHorario();
        $this->assertStringContainsString(
            'Se crearon 3 asignaciones al azar. Ahora cada alumno audita entre 1 y 2 talleres.',
            $this->get('/asignaciones')->body()
        );

        // Lo ya cubierto no se toca: otra vuelta con dos no tiene nada que hacer.
        $this->sortear('2');
        $this->assertSame(9, $this->contar('SELECT COUNT(*) FROM asignaciones'));
        $this->assertStringContainsString(
            'No hizo falta asignar nada: cada alumno tiene al menos un taller y cada taller programado tiene '
            . '2 auditores o más.',
            $this->get('/asignaciones')->body()
        );
    }

    public function testSiNoAlcanzanLosAlumnosAvisaQueTalleresQuedaronIncompletos(): void
    {
        $admin = $this->entrarComoAdministrador();
        foreach (range(1, 3) as $i) {
            $this->crearAlumno('Alumno', "Número {$i}");
        }
        $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 4, 'fecha' => '2026-11-10']);
        $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 4, 'fecha' => '2026-11-10']);

        $this->sortear('2');

        // Tres alumnos a la misma hora para dos talleres: uno recibe dos y el otro se queda con uno.
        $this->assertEqualsCanonicalizing([1, 2], array_values($this->auditoresPorTaller()));
        $this->sinChoquesDeHorario();
        $html = $this->get('/asignaciones')->body();
        $this->assertMatchesRegularExpression(
            '/Quedó con menos de 2 auditores (Robótica|Pintura): a esa hora no hay más alumnos libres cuyo horario la cubra\./',
            $html
        );
        $this->assertStringContainsString('alert--aviso', $html);
    }

    public function testRechazaUnaCantidadDeAuditoresFueraDeRango(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->crearAlumno();
        $this->crearActividad($admin);

        foreach (['0', '11', '-1', 'dos', '', '1.5'] as $valor) {
            $this->assertRedirige('/asignaciones', $this->sortear($valor));
            $this->assertStringContainsString(
                'Indica cuántos auditores quieres por taller: un número del 1 al 10.',
                $this->get('/asignaciones')->body(),
                "Valor «{$valor}»"
            );
        }
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM asignaciones'));
    }

    public function testElFormularioConservaLosAuditoresPorTaller(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->crearAlumno();
        $this->crearActividad($admin);

        $campo = '#<input type="number" id="auditores-por-taller" name="auditores_por_taller"\s+value="%d" min="1" max="10"#';
        $this->assertMatchesRegularExpression(sprintf($campo, 2), $this->get('/asignaciones')->body(), 'Propone dos');
        $this->assertMatchesRegularExpression(sprintf($campo, 4), $this->get('/asignaciones', ['auditores' => '4'])->body());
        $this->assertMatchesRegularExpression(sprintf($campo, 2), $this->get('/asignaciones', ['auditores' => '50'])->body());
    }

    public function testLaPantallaMuestraLoQueFaltaPorCubrir(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $luis = $this->crearAlumno('Luis', 'Pérez');
        $robotica = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 2]);
        $this->asignar($robotica, $ana, $admin);

        $html = $this->get('/asignaciones')->body();

        $this->assertMatchesRegularExpression('#Alumnos sin taller</span>\s*<span class="stat__value nums">1</span>#', $html);
        $this->assertMatchesRegularExpression('#Talleres sin auditor</span>\s*<span class="stat__value nums">1</span>#', $html);
        $this->assertStringContainsString('/alumnos/' . $luis, $html);
        $this->assertStringContainsString('Pérez, Luis', $html);
        $this->assertStringContainsString('Pintura', $html);
        $this->assertStringContainsString('data-confirmar="¿Asignar al azar?"', $html);
        // Reparto: Robótica con un auditor y Pintura sin ninguno; Ana con un taller y Luis sin taller.
        $this->assertMatchesRegularExpression('#Sin auditor\s*</dt>\s*<dd class="nums">1 taller</dd>#', $html);
        $this->assertMatchesRegularExpression('#1 auditor\s*</dt>\s*<dd class="nums">1 taller</dd>#', $html);
        $this->assertMatchesRegularExpression('#Sin taller\s*</dt>\s*<dd class="nums">1 alumno</dd>#', $html);
        $this->assertStringContainsString('2 talleres tienen menos de 2 auditores.', $html);
        // El panel central avisa que hay talleres sin auditor.
        $this->assertStringContainsString('1 taller programado no tiene auditor.', $this->get('/')->body());
    }
}
