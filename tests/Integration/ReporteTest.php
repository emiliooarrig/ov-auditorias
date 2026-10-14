<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Actividad;
use App\Models\Alumno;
use Tests\Support\IntegrationTestCase;

/**
 * Reporte en PDF de todos los talleres con su información y sus alumnos auditores.
 */
final class ReporteTest extends IntegrationTestCase
{
    public function testSoloLoDescargaUnAdministrador(): void
    {
        $this->assertRedirige('/login', $this->get('/reportes/talleres.pdf'));
    }

    public function testDescargaUnPdfConTodosLosTalleres(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->asignar($this->crearActividad($admin, ['nombre' => 'Robótica']), $this->crearAlumno('Ana', 'López'), $admin);

        $response = $this->get('/reportes/talleres.pdf', ['grupo' => '99']);

        $this->assertSame(200, $response->status());
        $this->assertSame('application/pdf', $response->header('Content-Type'));
        // Sin fecha en el nombre: el reporte se reparte a los alumnos.
        $this->assertSame('attachment; filename="reporte-talleres.pdf"', $response->header('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->body());
        $this->assertSame((string) strlen($response->body()), $response->header('Content-Length'));
        $this->assertDirectoryExists(dirname(__DIR__, 2) . '/storage/cache/dompdf');
        // El panel central ofrece el botón de descarga.
        $this->assertStringContainsString('href="/reportes/talleres.pdf" download', $this->get('/')->body());
    }

    public function testDescargaElDirectorioDeAlumnos(): void
    {
        $this->assertRedirige('/login', $this->get('/reportes/alumnos.pdf'));
        $admin = $this->entrarComoAdministrador();
        $this->asignar($this->crearActividad($admin), $this->crearAlumno('Ana', 'López'), $admin);

        $response = $this->get('/reportes/alumnos.pdf');

        $this->assertSame(200, $response->status());
        $this->assertSame('application/pdf', $response->header('Content-Type'));
        $this->assertSame('attachment; filename="directorio-alumnos.pdf"', $response->header('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->body());
        $this->assertStringContainsString('href="/reportes/alumnos.pdf" download', $this->get('/')->body());
    }

    public function testElDirectorioVaPorApellidoConLaHoraElTallerYElEdificioDeCadaAlumno(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'Álvarez');
        $luis = $this->crearAlumno('Luis', 'Pérez');
        $this->crearAlumno('Iván', 'Ruiz');
        $eva = $this->crearAlumno('Eva', 'Soto', false);
        $derecho = $this->contar("SELECT id FROM carreras WHERE nombre = 'Derecho'");
        $pintura = $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 5, 'carrera_id' => $derecho]);
        $robotica = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $desactivado = $this->crearActividad($admin, ['nombre' => 'Desactivado', 'grupo' => 2, 'activo' => 0]);
        $this->asignar($pintura, $ana, $admin);
        $this->asignar($robotica, $ana, $admin);
        $this->asignar($desactivado, $ana, $admin);
        $this->asignar($robotica, $luis, $admin);
        $this->asignar($pintura, $eva, $admin);

        $filas = (new Alumno($this->app->db()))->directorio();

        // Por apellido sin importar el acento; los dados de baja no aparecen; el que no tiene taller, sí.
        $this->assertSame(['Álvarez', 'Pérez', 'Ruiz'], array_column($filas, 'apellidos'));
        $this->assertSame(['Robótica', 'Pintura'], array_column($filas[0]['talleres'], 'nombre'), 'Por hora');
        $this->assertSame(['Actuaría', 'Derecho'], array_column($filas[0]['talleres'], 'carrera'));
        $this->assertSame([], $filas[2]['talleres']);

        $html = $this->app->view()->render('reportes/alumnos', [
            'alumnos' => $filas,
            'titulo' => 'Directorio de alumnos auditores',
        ], null);

        foreach (['Alumno', 'Hora', 'Taller y carrera', 'Edificio'] as $columna) {
            $this->assertStringContainsString('>' . $columna . '</th>', $html);
        }
        // La carrera va debajo del nombre del taller.
        $this->assertMatchesRegularExpression('#Pintura\s*<br><span class="secundario">Derecho</span>#', $html);
        foreach (['A', 'P', 'R'] as $letra) {
            $this->assertStringContainsString('<tr class="letra"><td colspan="4">' . $letra . '</td></tr>', $html);
        }
        $this->assertStringContainsString('<span class="apellidos">Álvarez</span>, Ana', $html);
        $this->assertStringContainsString('10:00–11:00', $html);
        $this->assertStringContainsString('15:00–16:00', $html);
        $this->assertStringContainsString('5 (Derecho)', $html);
        $this->assertStringContainsString('Sin taller asignado', $html);
        // Un solo día: no repite la fecha. Y como el otro reporte, no dice quién ni cuándo lo generó.
        foreach (['Soto', 'Desactivado', 'jue 15 oct', 'Generado'] as $ausente) {
            $this->assertStringNotContainsString($ausente, $html);
        }
    }

    public function testElReporteLlevaTodaLaInformacionDeCadaTallerYSusAuditores(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $luis = $this->crearAlumno('Luis', 'Pérez');
        $eva = $this->crearAlumno('Eva', 'Soto', false);
        $robotica = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $oratoria = $this->crearActividad($admin, ['nombre' => 'Oratoria <b>', 'grupo' => 5, 'estado' => 'realizado']);
        $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 2]);
        $this->crearActividad($admin, ['nombre' => 'Desactivado', 'activo' => 0]);
        $this->asignar($robotica, $luis, $admin);
        $this->asignar($robotica, $ana, $admin);
        $this->asignar($oratoria, $eva, $admin);
        (new Actividad($this->app->db()))->cambiarEstado($oratoria, 'no_realizado', 'Llovió', $admin);

        $filas = (new Actividad($this->app->db()))->reporte();

        $this->assertSame(['Robótica', 'Pintura', 'Oratoria <b>'], array_column($filas, 'nombre'), 'Por fecha y horario');
        $this->assertSame(
            [['nombre' => 'Ana', 'apellidos' => 'López', 'activo' => 1], ['nombre' => 'Luis', 'apellidos' => 'Pérez', 'activo' => 1]],
            $filas[0]['auditores']
        );
        $this->assertSame([], $filas[1]['auditores']);

        $html = $this->app->view()->render('reportes/talleres', [
            'talleres' => $filas,
            'titulo' => 'Reporte de talleres y auditores',
        ], null);

        foreach (['Taller', 'Carrera', 'Edificio', 'Fecha', 'Horario', 'Estado', 'Auditores'] as $columna) {
            $this->assertStringContainsString('>' . $columna . '</th>', $html);
        }
        $this->assertMatchesRegularExpression('#<td colspan="8">jue 15 oct 2026 · Taller 1 \(10:00–11:00\)</td>#', $html);
        $this->assertStringContainsString('Ana López', $html);
        $this->assertStringContainsString('Eva Soto (baja)', $html);
        $this->assertStringContainsString('Sin auditor', $html);
        $this->assertStringContainsString('No realizado', $html);
        $this->assertStringContainsString('Motivo: Llovió', $html);
        $this->assertStringContainsString('Registró: Prueba Usuario', $html);
        $this->assertStringContainsString('Oratoria &lt;b&gt;', $html);
        $this->assertStringNotContainsString('Desactivado', $html);

        // Se reparte a los alumnos: sin métricas, sin conteos por franja, sin leyenda y sin quién o cuándo.
        foreach (['Con auditor', 'class="resumen"', 'Generado', 'Todos los talleres activos', 'talleres,'] as $interno) {
            $this->assertStringNotContainsString($interno, $html);
        }
    }
}
