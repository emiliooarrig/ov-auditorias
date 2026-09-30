<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Usuario;
use Tests\Support\IntegrationTestCase;

/**
 * Alertas de SweetAlert: el servidor marca los mensajes (data-alerta) y los formularios que piden
 * confirmación (data-confirmar); app.js los muestra. Sin JavaScript todo funciona igual.
 */
final class AlertasTest extends IntegrationTestCase
{
    public function testIniciarYCerrarSesionDejanUnMensajeParaAlerta(): void
    {
        $this->entrarComoAuditor('ana.lopez@anahuac.mx');
        $inicio = $this->get('/mis-talleres')->body();
        $this->assertStringContainsString('data-alerta', $inicio);
        $this->assertStringContainsString('Hola, Prueba. Iniciaste sesión.', $inicio);

        $this->post('/logout');
        $login = $this->get('/login')->body();
        $this->assertStringContainsString('data-alerta', $login);
        $this->assertStringContainsString('Cerraste sesión.', $login);
    }

    public function testElAdministradorTambienRecibeLaBienvenida(): void
    {
        $this->entrarComoAdministrador();

        $this->assertStringContainsString('Iniciaste sesión.', $this->get('/')->body());
    }

    public function testCerrarSesionPideConfirmacion(): void
    {
        $this->entrarComoAuditor();

        $this->assertStringContainsString('data-confirmar="¿Cerrar sesión?"', $this->get('/mis-talleres')->body());
    }

    public function testActivarDesactivarYCambiarAAuditorPidenConfirmacion(): void
    {
        $this->entrarComoAdministrador();
        $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');
        $this->crearUsuario(Usuario::AUDITOR, 'luis.perez@anahuac.mx', null, false);
        $this->crearUsuario(Usuario::ADMINISTRADOR, 'otra.admin@anahuac.mx', 'Clave-segura-2026');

        $html = $this->get('/usuarios')->body();

        $this->assertStringContainsString('data-confirmar="¿Desactivar a Prueba Usuario?"', $html);
        $this->assertStringContainsString('data-confirmar="¿Activar a Prueba Usuario?"', $html);
        $this->assertStringContainsString('data-confirmar="¿Cambiar a Prueba Usuario a auditor?"', $html);
        // Hacer administrador no se confirma con alerta: ya pide la contraseña en el mismo formulario.
        $this->assertSame(1, substr_count($html, 'a auditor?"'));
    }

    public function testElResultadoDeActivarODesactivarSeMuestraComoAlerta(): void
    {
        $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');

        $this->post("/usuarios/{$auditor}/estado", ['activo' => '0']);
        $html = $this->get('/usuarios')->body();

        $this->assertStringContainsString('data-alerta', $html);
        $this->assertStringContainsString('Cuenta desactivada.', $html);
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
