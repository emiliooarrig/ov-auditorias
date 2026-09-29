<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Usuario;
use Tests\Support\IntegrationTestCase;

/**
 * Ingreso, registro y control de acceso por rol (RF-01, RF-02, CU-04; SDD 8.2 y 8.3).
 */
final class AuthTest extends IntegrationTestCase
{
    private const PASSWORD = 'Clave-segura-2026';

    public function testAuditorEntraSoloConSuCorreoYLlegaAMisTalleres(): void
    {
        $id = $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');

        $response = $this->post('/login', ['correo' => '  Ana.Lopez@ANAHUAC.mx ']);

        $this->assertRedirige('/mis-talleres', $response);
        $this->assertSame($id, $this->usuarioEnSesion());
        $this->assertSame(200, $this->get('/mis-talleres')->status());
        $this->assertSame(1, $this->contarAccesos('login_ok', 'ana.lopez@anahuac.mx'));
        $this->assertNotNull($this->app->db()->fetchValue('SELECT ultimo_acceso FROM usuarios WHERE id = ?', [$id]));
    }

    public function testCorreoNuevoDelDominioSeRegistraComoAuditor(): void
    {
        $this->assertRedirige('/registro', $this->post('/login', ['correo' => 'nuevo.alumno@anahuac.mx']));
        $this->assertStringContainsString('nuevo.alumno@anahuac.mx', $this->get('/registro')->body());

        $response = $this->post('/registro', ['nombre' => 'José  María', 'apellidos' => "O'Connor Núñez"]);

        $this->assertRedirige('/mis-talleres', $response);
        $usuario = (new Usuario($this->app->db()))->buscarPorCorreo('nuevo.alumno@anahuac.mx');
        $this->assertNotNull($usuario);
        $this->assertSame(Usuario::AUDITOR, $usuario['rol']);
        $this->assertSame('José María', $usuario['nombre']);
        $this->assertNull($usuario['password_hash']);
        $this->assertSame($usuario['id'], $this->usuarioEnSesion());
        $this->assertSame(1, $this->contarAccesos('registro', 'nuevo.alumno@anahuac.mx'));
    }

    public function testRegistroRechazaNombresVaciosOConCodigo(): void
    {
        $this->post('/login', ['correo' => 'nuevo.alumno@anahuac.mx']);

        $response = $this->post('/registro', ['nombre' => '<script>alert(1)</script>', 'apellidos' => '']);

        $this->assertRedirige('/registro', $response);
        $html = $this->get('/registro')->body();
        $this->assertStringContainsString('Este campo es obligatorio.', $html);
        $this->assertStringContainsString('Solo se permiten letras', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame(0, (int) $this->app->db()->fetchValue('SELECT COUNT(*) FROM usuarios'));
    }

    public function testRegistroSinPasarPorElIngresoVuelveAlLogin(): void
    {
        $this->assertRedirige('/login', $this->get('/registro'));
        $this->assertRedirige('/login', $this->post('/registro', ['nombre' => 'Ana', 'apellidos' => 'López']));
    }

    public function testCorreoDeOtroDominioEsRechazado(): void
    {
        foreach (['alguien@gmail.com', 'alguien@alumnos.anahuac.mx', 'anahuac.mx', ''] as $correo) {
            $this->assertRedirige('/login', $this->post('/login', ['correo' => $correo]));
            $this->assertStringContainsString('Usa tu correo institucional', $this->get('/login')->body());
        }
        $this->assertRedirige('/login', $this->get('/registro'));
    }

    public function testAdministradorNoEntraSinContrasena(): void
    {
        $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', self::PASSWORD);

        $this->assertRedirige('/login', $this->post('/login', ['correo' => 'admin@anahuac.mx']));

        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('Contraseña de administrador', $this->get('/login')->body());
        $this->assertRedirige('/login', $this->get('/'));
    }

    public function testAdministradorConContrasenaCorrectaLlegaAlPanel(): void
    {
        $id = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', self::PASSWORD);

        $this->post('/login', ['correo' => 'admin@anahuac.mx']);
        $response = $this->post('/login', ['password' => self::PASSWORD]);

        $this->assertRedirige('/', $response);
        $this->assertSame($id, $this->usuarioEnSesion());
        $this->assertSame(200, $this->get('/')->status());
    }

    public function testContrasenaIncorrectaQuedaRegistradaYBloqueaTrasVariosFallos(): void
    {
        $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', self::PASSWORD);
        $this->post('/login', ['correo' => 'admin@anahuac.mx']);

        for ($i = 0; $i < 5; $i++) {
            $this->assertRedirige('/login', $this->post('/login', ['password' => 'incorrecta-' . $i]));
        }
        $this->assertSame(5, $this->contarAccesos('login_fallido', 'admin@anahuac.mx'));

        // Con el bloqueo activo, ni la contraseña correcta abre la sesión.
        $this->post('/login', ['password' => self::PASSWORD]);
        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('Demasiados intentos fallidos', $this->get('/login')->body());
    }

    public function testElCorreoDelAdministradorSaleDeLaSesionNoDelFormulario(): void
    {
        $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', self::PASSWORD);
        $this->crearUsuario(Usuario::ADMINISTRADOR, 'otro.admin@anahuac.mx', 'Otra-clave-2026');

        $this->post('/login', ['correo' => 'admin@anahuac.mx']);
        $this->post('/login', ['correo' => 'otro.admin@anahuac.mx', 'password' => 'Otra-clave-2026']);

        $this->assertNull($this->usuarioEnSesion());
    }

    public function testCuentaDesactivadaNoEntra(): void
    {
        $this->crearUsuario(Usuario::AUDITOR, 'baja@anahuac.mx', null, false);

        $this->post('/login', ['correo' => 'baja@anahuac.mx']);

        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('Tu cuenta está desactivada', $this->get('/login')->body());
    }

    public function testDesactivarUnaCuentaCierraSuSesionActiva(): void
    {
        $id = $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');
        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx']);

        $this->app->db()->execute('UPDATE usuarios SET activo = 0 WHERE id = :id', ['id' => $id]);

        $this->assertRedirige('/login', $this->get('/mis-talleres'));
        $this->assertNull($this->usuarioEnSesion());
    }

    public function testCadaRolSoloEntraASusPantallas(): void
    {
        $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');
        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx']);
        $this->assertRedirige('/mis-talleres', $this->get('/'));
        $this->assertRedirige('/mis-talleres', $this->get('/login'));
        $this->post('/logout');

        $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', self::PASSWORD);
        $this->post('/login', ['correo' => 'admin@anahuac.mx']);
        $this->post('/login', ['password' => self::PASSWORD]);
        $this->assertSame(403, $this->get('/mis-talleres')->status());
    }

    public function testCerrarSesionInvalidaLaSesionYElTokenAnterior(): void
    {
        $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');
        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx']);
        $tokenAnterior = $this->app->csrf()->token();

        $this->assertRedirige('/login', $this->post('/logout'));

        $this->assertNull($this->usuarioEnSesion());
        $this->assertRedirige('/login', $this->get('/mis-talleres'));
        $this->assertFalse($this->app->csrf()->validate($tokenAnterior));
        $this->assertSame(1, $this->contarAccesos('logout', 'ana.lopez@anahuac.mx'));
    }

    public function testPostSinTokenCsrfEsRechazado(): void
    {
        $this->crearUsuario(Usuario::AUDITOR, 'ana.lopez@anahuac.mx');

        $response = $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx'], false);

        $this->assertSame(403, $response->status());
        $this->assertNull($this->usuarioEnSesion());
    }

    private function contarAccesos(string $evento, string $correo): int
    {
        return (int) $this->app->db()->fetchValue(
            'SELECT COUNT(*) FROM accesos WHERE evento = :evento AND correo = :correo',
            ['evento' => $evento, 'correo' => $correo]
        );
    }
}
