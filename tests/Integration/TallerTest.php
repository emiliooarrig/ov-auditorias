<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Alta, edición y desactivación de talleres, panel central y "Mis talleres" (RF-03, RF-06, RF-08, RF-11).
 */
final class TallerTest extends IntegrationTestCase
{
    /**
     * @return array<string, string>
     */
    private function formulario(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Taller de liderazgo',
            'carrera_id' => '2',
            'edificio_id' => '4',
            'fecha' => '2026-10-20',
            'grupo_id' => '3', // Taller 3: 12:00–13:00
        ];
    }

    public function testAdministradorCreaTallerYQuedaEnLaBitacora(): void
    {
        $admin = $this->entrarComoAdministrador();

        $response = $this->post('/actividades', $this->formulario());

        $taller = $this->app->db()->fetchOne('SELECT * FROM actividades');
        $this->assertNotNull($taller);
        $this->assertRedirige('/actividades/' . $taller['id'], $response);
        $this->assertSame('programado', $taller['estado']);
        $this->assertSame((int) $taller['grupo_id'], 3);
        $this->assertSame('12:00:00', $taller['hora_inicio']);
        $this->assertSame('13:00:00', $taller['hora_fin']);
        $this->assertSame($admin, $taller['creado_por']);

        $bitacora = $this->app->db()->fetchOne('SELECT * FROM bitacora_estados WHERE actividad_id = ?', [$taller['id']]);
        $this->assertNotNull($bitacora);
        $this->assertNull($bitacora['estado_anterior']);
        $this->assertSame('programado', $bitacora['estado_nuevo']);
        $this->assertSame($admin, $bitacora['usuario_id']);

        $detalle = $this->get('/actividades/' . $taller['id'])->body();
        $this->assertStringContainsString('Taller de liderazgo', $detalle);
        $this->assertStringContainsString('12:00–13:00', $detalle);
        $this->assertStringContainsString('Taller 3', $detalle);
        $this->assertStringContainsString('Creado como', $detalle);
    }

    public function testDatosInvalidosNoSeGuardanYMuestranSusErrores(): void
    {
        $this->entrarComoAdministrador();

        $response = $this->post('/actividades', $this->formulario([
            'nombre' => '  ',
            'carrera_id' => '999',
            'edificio_id' => 'x',
            'fecha' => '2026-02-30',
            'grupo_id' => '99',
        ]));

        $this->assertRedirige('/actividades/nueva', $response);
        $this->assertSame(0, (int) $this->app->db()->fetchValue('SELECT COUNT(*) FROM actividades'));
        $html = $this->get('/actividades/nueva')->body();
        foreach (
            [
                'Escribe el nombre del taller.',
                'Elige una carrera de la lista.',
                'Elige un edificio de la lista.',
                'Escribe una fecha válida.',
                'Elige un grupo de la lista (Taller 1 a Taller 6).',
            ] as $mensaje
        ) {
            $this->assertStringContainsString($mensaje, $html);
        }
        $this->assertStringContainsString('value="2026-02-30"', $html, 'Conserva lo enviado');
    }

    public function testEditarActualizaElTaller(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin);

        $this->assertStringContainsString('Taller de prueba', $this->get("/actividades/{$id}/editar")->body());
        $response = $this->post("/actividades/{$id}", $this->formulario(['nombre' => 'Nombre corregido']));

        $this->assertRedirige("/actividades/{$id}", $response);
        $this->assertSame('Nombre corregido', $this->app->db()->fetchValue('SELECT nombre FROM actividades WHERE id = ?', [$id]));
    }

    public function testDesactivarExigeConfirmacionYConservaElRegistro(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin, ['nombre' => 'Por desactivar']);

        $this->post("/actividades/{$id}/desactivar");
        $this->assertSame(1, (int) $this->app->db()->fetchValue('SELECT activo FROM actividades WHERE id = ?', [$id]));

        $this->assertRedirige('/', $this->post("/actividades/{$id}/desactivar", ['confirmar' => '1']));
        $this->assertSame(0, (int) $this->app->db()->fetchValue('SELECT activo FROM actividades WHERE id = ?', [$id]));
        $this->assertStringNotContainsString('Por desactivar</strong>', $this->get('/')->body());
        $this->assertSame(404, $this->get("/actividades/{$id}")->status());
        $this->assertSame(404, $this->get("/actividades/{$id}/editar")->status());
    }

    public function testElAuditorNoAccedeALaGestionDeTalleres(): void
    {
        $admin = $this->crearUsuario('administrador', 'admin@anahuac.mx', 'Clave-segura-2026');
        $id = $this->crearActividad($admin);
        $this->entrarComoAuditor();

        $this->assertSame(403, $this->get('/actividades/nueva')->status());
        $this->assertSame(403, $this->post('/actividades', $this->formulario())->status());
        $this->assertSame(403, $this->get("/actividades/{$id}/editar")->status());
        $this->assertSame(403, $this->post("/actividades/{$id}", $this->formulario())->status());
        $this->assertSame(403, $this->post("/actividades/{$id}/desactivar", ['confirmar' => '1'])->status());
        $this->assertSame(1, (int) $this->app->db()->fetchValue('SELECT COUNT(*) FROM actividades WHERE activo = 1'));
    }

    public function testElNombreDelTallerSeEscapaEnElPanelYElDetalle(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin, ['nombre' => '<script>alert("x")</script>']);

        foreach (['/', "/actividades/{$id}", "/actividades/{$id}/editar"] as $ruta) {
            $html = $this->get($ruta)->body();
            $this->assertStringNotContainsString('<script>alert', $html, $ruta);
            $this->assertStringContainsString('&lt;script&gt;', $html, $ruta);
        }
    }

    public function testElPanelConservaLosFiltrosYLosPasaALaPaginacion(): void
    {
        $admin = $this->entrarComoAdministrador();
        for ($i = 1; $i <= 22; $i++) {
            $this->crearActividad($admin, ['nombre' => "Robótica {$i}", 'carrera_id' => 11, 'edificio_id' => 3]);
        }
        $this->crearActividad($admin, ['nombre' => 'Oratoria', 'carrera_id' => 5]);

        $html = $this->get('/', ['nombre' => 'Robótica', 'carrera' => '11', 'edificio' => '3'])->body();

        $this->assertStringContainsString('22 talleres', $html);
        $this->assertStringContainsString('value="Robótica"', $html);
        $this->assertMatchesRegularExpression('/<option value="11" selected>/', $html);
        $this->assertStringNotContainsString('Oratoria', $html);
        $this->assertStringContainsString(
            e('/?nombre=Rob%C3%B3tica&carrera=11&edificio=3&pagina=2'),
            $html
        );
        $this->assertStringContainsString('Limpiar', $html);
    }

    public function testFiltrosInvalidosSeIgnoran(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->crearActividad($admin);

        $response = $this->get('/', ['carrera' => '1 OR 1=1', 'edificio' => ['3'], 'pagina' => '-4']);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Taller de prueba', $response->body());
    }

    public function testMisTalleresSoloMuestraLosAsignadosAlAuditor(): void
    {
        $admin = $this->crearUsuario('administrador', 'admin@anahuac.mx', 'Clave-segura-2026');
        $mio = $this->crearActividad($admin, ['nombre' => 'Asignado a mí']);
        $this->crearActividad($admin, ['nombre' => 'De otra persona']);
        $auditor = $this->entrarComoAuditor();

        $this->assertStringContainsString('Todavía no tienes talleres asignados', $this->get('/mis-talleres')->body());

        $this->asignar($mio, $auditor, $admin);
        $html = $this->get('/mis-talleres')->body();

        $this->assertStringContainsString('Asignado a mí', $html);
        $this->assertStringNotContainsString('De otra persona', $html);
        $this->assertStringContainsString('1 taller asignado', $html);
    }
}
