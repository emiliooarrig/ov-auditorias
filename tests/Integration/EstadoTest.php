<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Actividad;
use App\Models\Usuario;
use PDOException;
use Tests\Support\IntegrationTestCase;

/**
 * Cambio de estado de un taller y bitácora (RF-07, RF-09; CU-02; reglas 3, 5 y 8).
 */
final class EstadoTest extends IntegrationTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function taller(int $id): array
    {
        return (array) $this->app->db()->fetchOne('SELECT * FROM actividades WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bitacora(int $id): array
    {
        return $this->app->db()->fetchAll('SELECT * FROM bitacora_estados WHERE actividad_id = ? ORDER BY id', [$id]);
    }

    public function testAdministradorMarcaNoRealizadoConMotivoYQuedaEnLaBitacora(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin);

        $response = $this->post("/actividades/{$id}/estado", ['estado' => 'no_realizado', 'motivo' => '  Ponente enfermo ']);

        $this->assertRedirige("/actividades/{$id}", $response);
        $this->assertSame('no_realizado', $this->taller($id)['estado']);
        $this->assertSame('Ponente enfermo', $this->taller($id)['motivo_no_realizado']);
        $bitacora = $this->bitacora($id);
        $this->assertCount(1, $bitacora);
        $this->assertSame('programado', $bitacora[0]['estado_anterior']);
        $this->assertSame('Ponente enfermo', $bitacora[0]['motivo']);
        $this->assertSame($admin, $bitacora[0]['usuario_id']);

        // El panel muestra el motivo y quién lo registró (criterio de aceptación 8.3).
        $panel = $this->get('/')->body();
        $this->assertStringContainsString('Ponente enfermo', $panel);
        $this->assertStringContainsString('Registró: Prueba Usuario', $panel);
    }

    public function testNoRealizadoSinMotivoSeRechaza(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin);

        $this->post("/actividades/{$id}/estado", ['estado' => 'no_realizado', 'motivo' => '   ']);

        $this->assertSame('programado', $this->taller($id)['estado']);
        $this->assertSame([], $this->bitacora($id));
        $this->assertStringContainsString('Escribe el motivo', $this->get("/actividades/{$id}")->body());
    }

    public function testRevertirAProgramadoLimpiaElMotivoPeroLaBitacoraLoConserva(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin);
        $this->post("/actividades/{$id}/estado", ['estado' => 'no_realizado', 'motivo' => 'Lluvia']);

        $this->post("/actividades/{$id}/estado", ['estado' => 'programado', 'motivo' => 'ignorado']);
        $this->post("/actividades/{$id}/estado", ['estado' => 'realizado']);

        $taller = $this->taller($id);
        $this->assertSame('realizado', $taller['estado']);
        $this->assertNull($taller['motivo_no_realizado']);
        $this->assertSame(
            [['no_realizado', 'Lluvia'], ['programado', null], ['realizado', null]],
            array_map(static fn (array $f): array => [$f['estado_nuevo'], $f['motivo']], $this->bitacora($id))
        );
    }

    public function testEstadoInvalidoOIgualAlActualSeRechaza(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin);

        $this->post("/actividades/{$id}/estado", ['estado' => 'borrado']);
        $this->post("/actividades/{$id}/estado", ['estado' => 'programado']);

        $this->assertSame('programado', $this->taller($id)['estado']);
        $this->assertSame([], $this->bitacora($id));
    }

    public function testElAuditorAsignadoMarcaSuTallerComoNoRealizado(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $id = $this->crearActividad($admin);
        $auditor = $this->entrarComoAuditor();
        $this->asignar($id, $auditor, $admin);

        $detalle = $this->get("/actividades/{$id}")->body();
        $this->assertStringContainsString('Marcar como no realizado', $detalle);
        $this->assertStringNotContainsString('Editar</a>', $detalle);
        $this->assertStringNotContainsString('Desactivar taller', $detalle);
        $this->assertStringNotContainsString('/quitar', $detalle);

        $this->post("/actividades/{$id}/estado", ['estado' => 'no_realizado', 'motivo' => 'No llegó el ponente']);

        $this->assertSame('no_realizado', $this->taller($id)['estado']);
        $this->assertSame($auditor, $this->bitacora($id)[0]['usuario_id']);
        $this->assertStringContainsString('No llegó el ponente', $this->get('/mis-talleres')->body());
    }

    public function testElAuditorNoPuedeMarcarRealizadoNiRevertirNiRepetir(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $programado = $this->crearActividad($admin);
        $noRealizado = $this->crearActividad($admin, ['estado' => 'no_realizado', 'motivo_no_realizado' => 'Lluvia']);
        $auditor = $this->entrarComoAuditor();
        $this->asignar($programado, $auditor, $admin);
        $this->asignar($noRealizado, $auditor, $admin);

        $this->assertSame(403, $this->post("/actividades/{$programado}/estado", ['estado' => 'realizado'])->status());
        $this->assertSame(403, $this->post("/actividades/{$noRealizado}/estado", ['estado' => 'programado'])->status());
        $this->post("/actividades/{$noRealizado}/estado", ['estado' => 'no_realizado', 'motivo' => 'Otro motivo']);

        $this->assertSame('programado', $this->taller($programado)['estado']);
        $this->assertSame('Lluvia', $this->taller($noRealizado)['motivo_no_realizado']);
        $this->assertStringNotContainsString('Marcar como no realizado', $this->get("/actividades/{$noRealizado}")->body());
    }

    public function testUnAuditorNoAsignadoRecibe404AunqueConozcaElId(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $otro = $this->crearUsuario(Usuario::AUDITOR, 'otro@anahuac.mx');
        $id = $this->crearActividad($admin, ['nombre' => 'Taller ajeno']);
        $this->asignar($id, $otro, $admin);
        $this->entrarComoAuditor();

        $this->assertSame(404, $this->get("/actividades/{$id}")->status());
        $this->assertSame(404, $this->post("/actividades/{$id}/estado", ['estado' => 'no_realizado', 'motivo' => 'x'])->status());
        $this->assertSame('programado', $this->taller($id)['estado']);
        $this->assertStringNotContainsString('Taller ajeno', $this->get('/mis-talleres')->body());
        $this->assertRedirige('/mis-talleres', $this->get('/'));
    }

    public function testElCambioDeEstadoYLaBitacoraSonUnaSolaTransaccion(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $id = $this->crearActividad($admin);

        try {
            // Usuario inexistente: la inserción en la bitácora falla por llave foránea.
            (new Actividad($this->app->db()))->cambiarEstado($id, 'realizado', null, 999999);
            $this->fail('Se esperaba un error de llave foránea');
        } catch (PDOException) {
        }

        $this->assertSame('programado', $this->taller($id)['estado'], 'El UPDATE se deshizo');
        $this->assertSame([], $this->bitacora($id));
    }
}
