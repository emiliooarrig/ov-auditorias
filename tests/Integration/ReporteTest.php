<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Actividad;
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
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="reporte-talleres-\d{4}-\d{2}-\d{2}\.pdf"$/',
            (string) $response->header('Content-Disposition')
        );
        $this->assertStringStartsWith('%PDF-', $response->body());
        $this->assertSame((string) strlen($response->body()), $response->header('Content-Length'));
        $this->assertDirectoryExists(dirname(__DIR__, 2) . '/storage/cache/dompdf');
        // El panel central ofrece el botón de descarga.
        $this->assertStringContainsString('href="/reportes/talleres.pdf" download', $this->get('/')->body());
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
            'generado' => 'lun 12 oct 2026, 11:45',
            'generadoPor' => 'Prueba Usuario',
        ], null);

        foreach (['Taller', 'Carrera', 'Edificio', 'Fecha', 'Horario', 'Estado', 'Auditores'] as $columna) {
            $this->assertStringContainsString('>' . $columna . '</th>', $html);
        }
        $this->assertStringContainsString('jue 15 oct 2026 · Taller 1 (10:00–11:00)', $html);
        $this->assertStringContainsString('Ana López', $html);
        $this->assertStringContainsString('Eva Soto (baja)', $html);
        $this->assertStringContainsString('Sin auditor', $html);
        $this->assertStringContainsString('No realizado', $html);
        $this->assertStringContainsString('Motivo: Llovió', $html);
        $this->assertStringContainsString('Registró: Prueba Usuario', $html);
        $this->assertStringContainsString('Oratoria &lt;b&gt;', $html);
        $this->assertStringNotContainsString('Desactivado', $html);
    }
}
