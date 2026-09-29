<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Asignacion;
use App\Models\Usuario;
use DomainException;
use Tests\Support\IntegrationTestCase;

/**
 * Asignación manual de talleres (RF-04, RF-05; CU-01; reglas 1, 2 y 6).
 */
final class AsignacionTest extends IntegrationTestCase
{
    private function contarAsignaciones(): int
    {
        return (int) $this->app->db()->fetchValue('SELECT COUNT(*) FROM asignaciones');
    }

    public function testAsignaVariosTalleresOmitiendoLosQueYaTenia(): void
    {
        $admin = $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $a = $this->crearActividad($admin, ['nombre' => 'Taller A']);
        $b = $this->crearActividad($admin, ['nombre' => 'Taller B']);
        $c = $this->crearActividad($admin, ['nombre' => 'Taller C']);
        $this->asignar($a, $auditor, $admin);

        $response = $this->post('/asignaciones', [
            'usuario_id' => (string) $auditor,
            'actividades' => [(string) $a, (string) $b, (string) $c, (string) $b],
            'nombre' => 'Taller',
        ]);

        $this->assertRedirige('/asignaciones?nombre=Taller&usuario=' . $auditor, $response);
        $this->assertSame(3, $this->contarAsignaciones());
        $this->assertSame(
            $admin,
            (int) $this->app->db()->fetchValue('SELECT asignado_por FROM asignaciones WHERE actividad_id = ?', [$b])
        );
        $html = $this->get('/asignaciones', ['usuario' => (string) $auditor])->body();
        $this->assertStringContainsString('Se asignaron 2 talleres: Taller B, Taller C. 1 ya estaba asignado.', $html);
        $this->assertStringContainsString('Ya asignado', $html);
    }

    public function testUnTallerPuedeTenerVariosAuditores(): void
    {
        $admin = $this->entrarComoAdministrador();
        $taller = $this->crearActividad($admin, ['nombre' => 'Compartido']);
        foreach (['ana@anahuac.mx', 'luis@anahuac.mx'] as $correo) {
            $id = $this->crearUsuario(Usuario::AUDITOR, $correo);
            $this->post('/asignaciones', ['usuario_id' => (string) $id, 'actividades' => [(string) $taller]]);
        }

        $this->assertSame(2, $this->contarAsignaciones());
        $detalle = $this->get('/actividades/' . $taller)->body();
        $this->assertStringContainsString('ana@anahuac.mx', $detalle);
        $this->assertStringContainsString('luis@anahuac.mx', $detalle);
    }

    public function testSoloSeAsignaAAuditoresActivosYATalleresActivos(): void
    {
        $admin = $this->entrarComoAdministrador();
        $inactivo = $this->crearUsuario(Usuario::AUDITOR, 'baja@anahuac.mx', null, false);
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $taller = $this->crearActividad($admin);
        $desactivado = $this->crearActividad($admin, ['activo' => 0]);

        $this->post('/asignaciones', ['usuario_id' => (string) $inactivo, 'actividades' => [(string) $taller]]);
        $this->post('/asignaciones', ['usuario_id' => (string) $admin, 'actividades' => [(string) $taller]]);
        $this->post('/asignaciones', ['usuario_id' => '99999', 'actividades' => [(string) $taller]]);
        $this->post('/asignaciones', ['usuario_id' => (string) $auditor, 'actividades' => [(string) $desactivado]]);
        $this->post('/asignaciones', ['usuario_id' => (string) $auditor, 'actividades' => 'no-es-lista']);

        $this->assertSame(0, $this->contarAsignaciones());
        $this->assertStringNotContainsString('baja@anahuac.mx', $this->get('/asignaciones')->body());
    }

    public function testSeRechazaElFormularioSinTalleresElegidos(): void
    {
        $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');

        $this->post('/asignaciones', ['usuario_id' => (string) $auditor]);

        $this->assertStringContainsString(
            'Elige al menos un taller.',
            $this->get('/asignaciones', ['usuario' => (string) $auditor])->body()
        );
    }

    public function testSoloSeQuitaUnaAsignacionMientrasElTallerEstaProgramado(): void
    {
        $admin = $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $programado = $this->crearActividad($admin);
        $realizado = $this->crearActividad($admin, ['estado' => 'realizado']);
        $this->asignar($programado, $auditor, $admin);
        $this->asignar($realizado, $auditor, $admin);
        $ids = $this->app->db()->fetchAll('SELECT id, actividad_id FROM asignaciones ORDER BY id');

        $response = $this->post("/asignaciones/{$ids[1]['id']}/quitar", ['volver' => 'detalle', 'actividad_id' => (string) $realizado]);
        $this->assertRedirige('/actividades/' . $realizado, $response);
        $this->assertSame(2, $this->contarAsignaciones());
        $this->assertStringContainsString('Solo se puede quitar un auditor mientras', $this->get('/actividades/' . $realizado)->body());

        $response = $this->post("/asignaciones/{$ids[0]['id']}/quitar", ['volver' => 'asignaciones', 'usuario_id' => (string) $auditor]);
        $this->assertRedirige('/asignaciones?usuario=' . $auditor, $response);
        $this->assertSame(1, $this->contarAsignaciones());
    }

    public function testElModeloRechazaQuitarDeUnTallerNoProgramado(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $taller = $this->crearActividad($admin, ['estado' => 'no_realizado', 'motivo_no_realizado' => 'Lluvia']);
        $this->asignar($taller, $auditor, $admin);

        $this->expectException(DomainException::class);
        (new Asignacion($this->app->db()))->quitar($this->app->db()->lastInsertId());
    }

    public function testElAuditorNoPuedeAsignarNiQuitar(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $taller = $this->crearActividad($admin);
        $auditor = $this->entrarComoAuditor();
        $this->asignar($taller, $auditor, $admin);
        $asignacion = $this->app->db()->lastInsertId();

        $this->assertSame(403, $this->get('/asignaciones')->status());
        $this->assertSame(403, $this->post('/asignaciones', [
            'usuario_id' => (string) $auditor,
            'actividades' => [(string) $this->crearActividad($admin)],
        ])->status());
        $this->assertSame(403, $this->post("/asignaciones/{$asignacion}/quitar")->status());
        $this->assertSame(1, $this->contarAsignaciones());
    }

    public function testElAuditorVeEnMisTalleresLoQueLeAsignaron(): void
    {
        $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $taller = $this->crearActividad((int) $this->usuarioEnSesion(), ['nombre' => 'Mi primer taller']);
        $this->post('/asignaciones', ['usuario_id' => (string) $auditor, 'actividades' => [(string) $taller]]);

        $this->entrarComoAuditor('ana@anahuac.mx');

        $html = $this->get('/mis-talleres')->body();
        $this->assertStringContainsString('Mi primer taller', $html);
        $this->assertStringContainsString('/actividades/' . $taller, $html);
    }
}
