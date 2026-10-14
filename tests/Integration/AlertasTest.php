<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Alertas de SweetAlert: el servidor marca los mensajes (data-alerta) y los formularios que piden
 * confirmación (data-confirmar); app.js los muestra. Sin JavaScript todo funciona igual.
 */
final class AlertasTest extends IntegrationTestCase
{
    public function testIniciarYCerrarSesionDejanUnMensajeParaAlerta(): void
    {
        $this->entrarComoAdministrador();
        $inicio = $this->get('/')->body();
        $this->assertStringContainsString('data-alerta', $inicio);
        $this->assertStringContainsString('Hola, Prueba. Iniciaste sesión.', $inicio);

        $this->post('/logout');
        $login = $this->get('/login')->body();
        $this->assertStringContainsString('data-alerta', $login);
        $this->assertStringContainsString('Cerraste sesión.', $login);
    }

    public function testCerrarSesionPideConfirmacion(): void
    {
        $this->entrarComoAdministrador();

        $this->assertStringContainsString('data-confirmar="¿Cerrar sesión?"', $this->get('/')->body());
    }

    public function testActivarYDesactivarAdministradoresPidenConfirmacion(): void
    {
        $this->entrarComoAdministrador();
        $this->crearAdministrador('ana.lopez@anahuac.mx');
        $this->crearAdministrador('luis.perez@anahuac.mx', self::CLAVE, false);

        $html = $this->get('/administradores')->body();

        $this->assertStringContainsString('data-confirmar="¿Desactivar a Prueba Usuario?"', $html);
        $this->assertStringContainsString('data-confirmar="¿Activar a Prueba Usuario?"', $html);
        // La cuenta propia no se puede desactivar: no tiene ese botón.
        $this->assertSame(1, substr_count($html, 'data-confirmar="¿Desactivar a'));
        $this->assertSame(1, substr_count($html, 'data-confirmar="¿Activar a'));
    }

    public function testElResultadoDeUnaAccionSeMuestraComoAlerta(): void
    {
        $this->entrarComoAdministrador();
        $otro = $this->crearAdministrador('ana.lopez@anahuac.mx');

        $this->post("/administradores/{$otro}/estado", ['activo' => '0']);
        $html = $this->get('/administradores')->body();

        $this->assertStringContainsString('data-alerta', $html);
        $this->assertStringContainsString('Cuenta desactivada.', $html);
    }

    public function testDarDeBajaYAsignarAlAzarPidenConfirmacion(): void
    {
        $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');

        $this->assertStringContainsString('data-confirmar="¿Dar de baja a Ana López?"', $this->get("/alumnos/{$ana}")->body());
        $this->assertStringContainsString('data-confirmar="¿Asignar al azar?"', $this->get('/asignaciones')->body());
    }

    public function testDesactivarUnTallerPideConfirmacionYConservaLaCasillaSinJavaScript(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin, ['nombre' => 'Robótica']);

        $html = $this->get("/actividades/{$id}")->body();
        $this->assertStringContainsString('data-confirmar="¿Desactivar «Robótica»?"', $html);
        $this->assertStringContainsString('data-confirmacion-manual', $html);

        // Sin la casilla (lo que marca el diálogo), el servidor sigue rechazando la desactivación.
        $this->post("/actividades/{$id}/desactivar");
        $this->assertSame(1, (int) $this->app->db()->fetchValue('SELECT activo FROM actividades WHERE id = ?', [$id]));

        $this->post("/actividades/{$id}/desactivar", ['confirmar' => '1']);
        $this->assertStringContainsString('data-alerta', $this->get('/')->body());
    }

    public function testElTextoDeLaConfirmacionSeEscapa(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin, ['nombre' => '"><script>alert(1)</script>']);

        $html = $this->get("/actividades/{$id}")->body();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('data-confirmar="¿Desactivar «&quot;&gt;&lt;script&gt;', $html);
    }
}
