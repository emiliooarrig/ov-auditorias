<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Ingreso de administradores, los únicos usuarios del sistema (RF-01, CU-04). Los alumnos no ingresan.
 */
final class AuthTest extends IntegrationTestCase
{
    public function testAdministradorEntraConCorreoYContrasenaYLlegaAlPanel(): void
    {
        $id = $this->crearAdministrador('ana.lopez@anahuac.mx');

        $response = $this->post('/login', ['correo' => '  Ana.Lopez@ANAHUAC.mx ', 'password' => self::CLAVE]);

        $this->assertRedirige('/', $response);
        $this->assertSame($id, $this->usuarioEnSesion());
        $this->assertSame(200, $this->get('/')->status());
        $this->assertSame(1, $this->contarAccesos('login_ok', 'ana.lopez@anahuac.mx'));
        $this->assertNotNull($this->app->db()->fetchValue('SELECT ultimo_acceso FROM usuarios WHERE id = ?', [$id]));
    }

    public function testLaPantallaDeIngresoPideCorreoYContrasenaEnUnSoloPaso(): void
    {
        $html = $this->get('/login')->body();

        $this->assertStringContainsString('name="correo"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('Solo para administradores', $html);
        $this->assertStringNotContainsString('primera vez', $html);
    }

    public function testCorreoDesconocidoYContrasenaIncorrectaDanElMismoMensaje(): void
    {
        $this->crearAdministrador('admin@anahuac.mx');

        foreach (['admin@anahuac.mx' => 'incorrecta-123', 'nadie@anahuac.mx' => self::CLAVE] as $correo => $clave) {
            $this->assertRedirige('/login', $this->post('/login', ['correo' => $correo, 'password' => $clave]));
            $html = $this->get('/login')->body();
            $this->assertStringContainsString('El correo o la contraseña no son correctos.', $html);
            // Se conserva el correo escrito, nunca la contraseña.
            $this->assertStringContainsString('value="' . $correo . '"', $html);
            $this->assertStringNotContainsString($clave, $html);
        }
        $this->assertNull($this->usuarioEnSesion());
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM usuarios WHERE correo = ?', ['nadie@anahuac.mx']));
    }

    public function testCorreoDeOtroDominioEsRechazado(): void
    {
        foreach (['alguien@gmail.com', 'alguien@alumnos.anahuac.mx', 'anahuac.mx', ''] as $correo) {
            $this->assertRedirige('/login', $this->post('/login', ['correo' => $correo, 'password' => self::CLAVE]));
            $this->assertStringContainsString('Usa tu correo institucional', $this->get('/login')->body());
        }
    }

    public function testElRegistroYMisTalleresYaNoExisten(): void
    {
        $this->assertSame(404, $this->get('/registro')->status());
        $this->assertSame(404, $this->post('/registro', ['nombre' => 'Ana', 'apellidos' => 'López'])->status());

        $this->entrarComoAdministrador();
        $this->assertSame(404, $this->get('/mis-talleres')->status());
        $this->assertSame(404, $this->get('/usuarios')->status());
    }

    public function testUnaCuentaDeAuditorAnteriorNoPuedeIngresar(): void
    {
        $this->crearCuentaDeAuditor('ana.lopez@anahuac.mx');

        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx', 'password' => '']);
        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx', 'password' => 'cualquier-cosa']);

        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('El correo o la contraseña no son correctos.', $this->get('/login')->body());
        $this->assertSame(2, $this->contarAccesos('login_fallido', 'ana.lopez@anahuac.mx'));
    }

    public function testUnaSesionAbiertaDeAuditorDejaDeSerValida(): void
    {
        $auditor = $this->crearCuentaDeAuditor('ana.lopez@anahuac.mx');
        $_SESSION['usuario_id'] = $auditor;

        $this->assertRedirige('/login', $this->get('/'));
        $this->assertNull($this->usuarioEnSesion());
    }

    public function testContrasenaIncorrectaQuedaRegistradaYBloqueaTrasVariosFallos(): void
    {
        $this->crearAdministrador('admin@anahuac.mx');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['correo' => 'admin@anahuac.mx', 'password' => 'incorrecta-' . $i]);
        }
        $this->assertSame(5, $this->contarAccesos('login_fallido', 'admin@anahuac.mx'));

        // Con el bloqueo activo, ni la contraseña correcta abre la sesión.
        $this->post('/login', ['correo' => 'admin@anahuac.mx', 'password' => self::CLAVE]);
        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('Demasiados intentos fallidos', $this->get('/login')->body());
    }

    public function testCuentaDesactivadaSoloLoDiceConLaContrasenaCorrecta(): void
    {
        $this->crearAdministrador('baja@anahuac.mx', self::CLAVE, false);

        $this->post('/login', ['correo' => 'baja@anahuac.mx', 'password' => 'incorrecta-123']);
        $this->assertStringNotContainsString('desactivada', $this->get('/login')->body());

        $this->post('/login', ['correo' => 'baja@anahuac.mx', 'password' => self::CLAVE]);
        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('Tu cuenta está desactivada', $this->get('/login')->body());
    }

    public function testDesactivarUnaCuentaCierraSuSesionActiva(): void
    {
        $id = $this->entrarComoAdministrador();

        $this->app->db()->execute('UPDATE usuarios SET activo = 0 WHERE id = :id', ['id' => $id]);

        $this->assertRedirige('/login', $this->get('/'));
        $this->assertNull($this->usuarioEnSesion());
    }

    public function testSinSesionTodoRedirigeAlIngresoYConSesionElIngresoRedirigeAlPanel(): void
    {
        foreach (['/', '/alumnos', '/asignaciones', '/administradores', '/actividades/nueva'] as $ruta) {
            $this->assertRedirige('/login', $this->get($ruta));
        }
        $this->assertRedirige('/login', $this->post('/asignaciones/aleatoria'));
        $this->assertRedirige('/login', $this->post('/alumnos', ['nombre' => 'Ana', 'apellidos' => 'López']));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM alumnos'));

        $this->entrarComoAdministrador();
        $this->assertRedirige('/', $this->get('/login'));
    }

    public function testCerrarSesionInvalidaLaSesionYElTokenAnterior(): void
    {
        $this->entrarComoAdministrador();
        $tokenAnterior = $this->app->csrf()->token();

        $this->assertRedirige('/login', $this->post('/logout'));

        $this->assertNull($this->usuarioEnSesion());
        $this->assertRedirige('/login', $this->get('/'));
        $this->assertFalse($this->app->csrf()->validate($tokenAnterior));
        $this->assertSame(1, $this->contarAccesos('logout', 'admin@anahuac.mx'));
    }

    public function testPostSinTokenCsrfEsRechazado(): void
    {
        $this->crearAdministrador('admin@anahuac.mx');

        $response = $this->post('/login', ['correo' => 'admin@anahuac.mx', 'password' => self::CLAVE], false);

        $this->assertSame(403, $response->status());
        $this->assertNull($this->usuarioEnSesion());
    }

    private function contarAccesos(string $evento, string $correo): int
    {
        return $this->contar(
            'SELECT COUNT(*) FROM accesos WHERE evento = :evento AND correo = :correo',
            ['evento' => $evento, 'correo' => $correo]
        );
    }
}
