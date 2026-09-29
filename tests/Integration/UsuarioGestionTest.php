<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Usuario;
use Tests\Support\IntegrationTestCase;

/**
 * Gestión de usuarios (RF-10): listar, activar o desactivar, cambiar rol.
 */
final class UsuarioGestionTest extends IntegrationTestCase
{
    private const CLAVE_NUEVA = 'Nueva-clave-2026';

    private function rol(int $id): string
    {
        return (string) $this->app->db()->fetchValue(
            'SELECT r.nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?',
            [$id]
        );
    }

    private function activo(int $id): int
    {
        return (int) $this->app->db()->fetchValue('SELECT activo FROM usuarios WHERE id = ?', [$id]);
    }

    public function testElAdministradorVeLosRegistradosConLosDatosParaFiltrarlosEnElNavegador(): void
    {
        $this->entrarComoAdministrador();
        $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');
        $this->crearUsuario(Usuario::AUDITOR, 'luis.perez@anahuac.mx', null, false);

        $todos = $this->get('/usuarios')->body();
        $this->assertStringContainsString('ana.lopez@anahuac.mx', $todos);
        $this->assertStringContainsString('luis.perez@anahuac.mx', $todos);
        $this->assertStringContainsString('3 usuarios', $todos);
        $this->assertStringContainsString('data-rol="auditor" data-estado="desactivados"', $todos);

        // El servidor ya no filtra: entrega todos y solo restaura los valores de los filtros.
        $busqueda = $this->get('/usuarios', ['texto' => 'luis', 'estado' => 'desactivados', 'rol' => 'x'])->body();
        $this->assertStringContainsString('ana.lopez@anahuac.mx', $busqueda);
        $this->assertStringContainsString('3 usuarios', $busqueda);
        $this->assertStringContainsString('name="texto" value="luis"', $busqueda);
        $this->assertStringContainsString('<option value="desactivados" selected>', $busqueda);
        $this->assertStringNotContainsString('<option value="auditor" selected>', $busqueda);
    }

    public function testDesactivarUnAuditorLeImpideEntrarYReactivarloLoPermite(): void
    {
        $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');

        $response = $this->post("/usuarios/{$auditor}/estado", ['activo' => '0', 'texto' => 'ana']);

        $this->assertRedirige('/usuarios?texto=ana', $response);
        $this->assertSame(0, $this->activo($auditor));

        $this->post('/logout');
        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx']);
        $this->assertNull($this->usuarioEnSesion());

        $this->entrarComoAdministrador('otro.admin@anahuac.mx');
        $this->post("/usuarios/{$auditor}/estado", ['activo' => '1']);
        $this->assertSame(1, $this->activo($auditor));
    }

    public function testNadiePuedeDesactivarseNiCambiarseElRolASiMismo(): void
    {
        $admin = $this->entrarComoAdministrador();

        $this->post("/usuarios/{$admin}/estado", ['activo' => '0']);
        $this->post("/usuarios/{$admin}/rol", ['rol' => 'auditor']);

        $this->assertSame(1, $this->activo($admin));
        $this->assertSame(Usuario::ADMINISTRADOR, $this->rol($admin));
        $this->assertStringContainsString('No puedes cambiar tu propio rol.', $this->get('/usuarios')->body());
    }

    public function testPromoverExigeUnaContrasenaValidaYConfirmada(): void
    {
        $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');

        $this->post("/usuarios/{$auditor}/rol", ['rol' => 'administrador']);
        $this->post("/usuarios/{$auditor}/rol", ['rol' => 'administrador', 'password' => 'corta', 'password_confirmacion' => 'corta']);
        $this->post("/usuarios/{$auditor}/rol", [
            'rol' => 'administrador',
            'password' => self::CLAVE_NUEVA,
            'password_confirmacion' => 'Otra-distinta-2026',
        ]);

        $this->assertSame(Usuario::AUDITOR, $this->rol($auditor));
    }

    public function testElUsuarioPromovidoEntraConContrasenaYElDegradadoSoloConCorreo(): void
    {
        $this->entrarComoAdministrador();
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');

        $this->post("/usuarios/{$auditor}/rol", [
            'rol' => 'administrador',
            'password' => self::CLAVE_NUEVA,
            'password_confirmacion' => self::CLAVE_NUEVA,
        ]);
        $this->assertSame(Usuario::ADMINISTRADOR, $this->rol($auditor));

        // Ahora ingresa en dos pasos, con la contraseña asignada.
        $this->post('/logout');
        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx']);
        $this->assertNull($this->usuarioEnSesion());
        $this->assertRedirige('/', $this->post('/login', ['password' => self::CLAVE_NUEVA]));
        $this->assertSame($auditor, $this->usuarioEnSesion());

        // Otro administrador la regresa a auditora: su contraseña se borra.
        $this->entrarComoAdministrador('otro.admin@anahuac.mx');
        $this->post("/usuarios/{$auditor}/rol", ['rol' => 'auditor']);
        $this->assertSame(Usuario::AUDITOR, $this->rol($auditor));
        $this->assertNull($this->app->db()->fetchValue('SELECT password_hash FROM usuarios WHERE id = ?', [$auditor]));
    }

    public function testElCambioDeRolAplicaDeInmediatoEnLaSesionAbierta(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $otroAdmin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'otro.admin@anahuac.mx', 'Clave-segura-2026');
        $this->post('/login', ['correo' => 'otro.admin@anahuac.mx']);
        $this->post('/login', ['password' => 'Clave-segura-2026']);
        $this->assertSame(200, $this->get('/usuarios')->status());

        (new Usuario($this->app->db()))->cambiarRol($otroAdmin, Usuario::AUDITOR, null, $admin);

        $this->assertSame(403, $this->get('/usuarios')->status());
        $this->assertRedirige('/mis-talleres', $this->get('/'));
    }

    public function testNoSePuedeDejarElSistemaSinAdministradorActivo(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $auditor = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $usuarios = new Usuario($this->app->db());

        $this->expectExceptionMessage('Debe quedar al menos un administrador activo.');
        $usuarios->cambiarActivo($admin, false, $auditor);
    }

    public function testElAuditorNoAccedeALaGestionDeUsuarios(): void
    {
        $admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
        $auditor = $this->entrarComoAuditor();

        $this->assertSame(403, $this->get('/usuarios')->status());
        $this->assertSame(403, $this->post("/usuarios/{$admin}/estado", ['activo' => '0'])->status());
        $this->assertSame(403, $this->post("/usuarios/{$auditor}/rol", [
            'rol' => 'administrador',
            'password' => self::CLAVE_NUEVA,
            'password_confirmacion' => self::CLAVE_NUEVA,
        ])->status());
        $this->assertSame(Usuario::AUDITOR, $this->rol($auditor));
        $this->assertSame(1, $this->activo($admin));
    }
}
