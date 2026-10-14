<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Password;
use App\Models\Usuario;
use Tests\Support\IntegrationTestCase;

/**
 * Administradores, las únicas cuentas del sistema (RF-10): alta, activar o desactivar y contraseña.
 */
final class AdministradorTest extends IntegrationTestCase
{
    private const CLAVE_NUEVA = 'Nueva-clave-2026';

    private function activo(int $id): int
    {
        return $this->contar('SELECT activo FROM usuarios WHERE id = ?', [$id]);
    }

    private function hash(int $id): string
    {
        return (string) $this->app->db()->fetchValue('SELECT password_hash FROM usuarios WHERE id = ?', [$id]);
    }

    public function testLaListaSoloMuestraAdministradores(): void
    {
        $this->entrarComoAdministrador();
        $this->crearAdministrador('luis.perez@anahuac.mx', self::CLAVE, false);
        $this->crearCuentaDeAuditor('auditor.anterior@anahuac.mx');
        $this->crearAlumno('Ana', 'López');

        $html = $this->get('/administradores')->body();

        $this->assertStringContainsString('admin@anahuac.mx', $html);
        $this->assertStringContainsString('luis.perez@anahuac.mx', $html);
        $this->assertStringContainsString('Desactivado', $html);
        $this->assertStringNotContainsString('auditor.anterior@anahuac.mx', $html);
        $this->assertStringNotContainsString('López', $html);
        $this->assertStringContainsString('Cambiar mi contraseña', $html);
    }

    public function testAgregarUnAdministradorQuePuedeIngresar(): void
    {
        $this->entrarComoAdministrador();

        $response = $this->post('/administradores', [
            'nombre' => 'Ana',
            'apellidos' => 'López',
            'correo' => 'Ana.Lopez@anahuac.mx',
            'password' => self::CLAVE_NUEVA,
            'password_confirmacion' => self::CLAVE_NUEVA,
        ]);

        $this->assertRedirige('/administradores', $response);
        $nueva = (new Usuario($this->app->db()))->buscarPorCorreo('ana.lopez@anahuac.mx');
        $this->assertNotNull($nueva);
        $this->assertSame(Usuario::ADMINISTRADOR, $nueva['rol']);
        $this->assertStringContainsString('Ana ya puede ingresar con ana.lopez@anahuac.mx', $this->get('/administradores')->body());

        $this->post('/logout');
        $this->assertRedirige('/', $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx', 'password' => self::CLAVE_NUEVA]));
        $this->assertSame($nueva['id'], $this->usuarioEnSesion());
    }

    public function testAltaValidaCadaCampoYNoGuardaLaContrasenaEnElFormulario(): void
    {
        $this->entrarComoAdministrador();

        $this->post('/administradores', [
            'nombre' => '',
            'apellidos' => 'López1',
            'correo' => 'ana@gmail.com',
            'password' => 'corta',
            'password_confirmacion' => 'corta',
        ]);
        $html = $this->get('/administradores')->body();
        $this->assertStringContainsString('Este campo es obligatorio.', $html);
        $this->assertStringContainsString('Solo se permiten letras', $html);
        $this->assertStringContainsString('Usa un correo institucional', $html);
        $this->assertStringContainsString('La contraseña debe tener al menos', $html);
        $this->assertStringContainsString('value="ana@gmail.com"', $html);
        $this->assertStringNotContainsString('value="corta"', $html);

        $this->post('/administradores', [
            'nombre' => 'Otra',
            'apellidos' => 'Persona',
            'correo' => 'admin@anahuac.mx',
            'password' => self::CLAVE_NUEVA,
            'password_confirmacion' => self::CLAVE_NUEVA,
        ]);
        $this->assertStringContainsString('Ya hay un administrador con ese correo.', $this->get('/administradores')->body());
        $this->assertSame(1, $this->contar('SELECT COUNT(*) FROM usuarios'));
    }

    public function testElCorreoDeUnaCuentaDeAuditorAnteriorSeConvierteEnAdministrador(): void
    {
        $this->entrarComoAdministrador();
        $anterior = $this->crearCuentaDeAuditor('ana.lopez@anahuac.mx', false);

        $this->post('/administradores', [
            'nombre' => 'Ana',
            'apellidos' => 'López',
            'correo' => 'ana.lopez@anahuac.mx',
            'password' => self::CLAVE_NUEVA,
            'password_confirmacion' => self::CLAVE_NUEVA,
        ]);

        $cuenta = (new Usuario($this->app->db()))->buscarPorId($anterior);
        $this->assertNotNull($cuenta);
        $this->assertSame(Usuario::ADMINISTRADOR, $cuenta['rol']);
        $this->assertSame(1, (int) $cuenta['activo']);
        $this->assertSame('Ana', $cuenta['nombre']);
        $this->assertTrue(Password::verify(self::CLAVE_NUEVA, $cuenta['password_hash']));
    }

    public function testDesactivarImpideIngresarYReactivarLoPermite(): void
    {
        $this->entrarComoAdministrador();
        $otro = $this->crearAdministrador('ana.lopez@anahuac.mx');

        $this->assertRedirige('/administradores', $this->post("/administradores/{$otro}/estado", ['activo' => '0']));
        $this->assertSame(0, $this->activo($otro));

        $this->post('/logout');
        $this->post('/login', ['correo' => 'ana.lopez@anahuac.mx', 'password' => self::CLAVE]);
        $this->assertNull($this->usuarioEnSesion());

        $this->entrarComoAdministrador();
        $this->post("/administradores/{$otro}/estado", ['activo' => '1']);
        $this->assertSame(1, $this->activo($otro));
    }

    public function testNadiePuedeDesactivarseNiDejarElSistemaSinAdministradores(): void
    {
        $admin = $this->entrarComoAdministrador();

        $this->post("/administradores/{$admin}/estado", ['activo' => '0']);

        $this->assertSame(1, $this->activo($admin));
        $this->assertStringContainsString('No puedes desactivar tu propia cuenta.', $this->get('/administradores')->body());

        $otro = $this->crearAdministrador('ana.lopez@anahuac.mx', self::CLAVE, false);
        $this->expectExceptionMessage('Debe quedar al menos un administrador activo.');
        (new Usuario($this->app->db()))->cambiarActivo($admin, false, $otro);
    }

    public function testCambiarLaContrasenaDeOtroAdministrador(): void
    {
        $this->entrarComoAdministrador();
        $otro = $this->crearAdministrador('ana.lopez@anahuac.mx');
        $anterior = $this->hash($otro);

        $this->post("/administradores/{$otro}/password", ['password' => self::CLAVE_NUEVA, 'password_confirmacion' => 'otra-distinta-1']);
        $this->assertSame($anterior, $this->hash($otro));

        $this->post("/administradores/{$otro}/password", ['password' => self::CLAVE_NUEVA, 'password_confirmacion' => self::CLAVE_NUEVA]);
        $this->assertTrue(Password::verify(self::CLAVE_NUEVA, $this->hash($otro)));
        $this->assertStringContainsString('Contraseña cambiada. Compártela con Prueba', $this->get('/administradores')->body());
    }

    public function testParaCambiarLaPropiaSePideLaActualYSeRespetanLosEspacios(): void
    {
        $admin = $this->entrarComoAdministrador();
        $nueva = '  con espacios 2026 ';

        $this->post("/administradores/{$admin}/password", [
            'password_actual' => 'equivocada-123',
            'password' => $nueva,
            'password_confirmacion' => $nueva,
        ]);
        $this->assertStringContainsString('Tu contraseña actual no es correcta.', $this->get('/administradores')->body());
        $this->assertTrue(Password::verify(self::CLAVE, $this->hash($admin)));

        $this->post("/administradores/{$admin}/password", [
            'password_actual' => self::CLAVE,
            'password' => $nueva,
            'password_confirmacion' => $nueva,
        ]);
        $this->assertTrue(Password::verify($nueva, $this->hash($admin)));
        $this->assertFalse(Password::verify(trim($nueva), $this->hash($admin)));
    }

    public function testLaContrasenaSoloSeCambiaAAdministradores(): void
    {
        $this->entrarComoAdministrador();
        $anterior = $this->crearCuentaDeAuditor('auditor@anahuac.mx');

        $response = $this->post("/administradores/{$anterior}/password", [
            'password' => self::CLAVE_NUEVA,
            'password_confirmacion' => self::CLAVE_NUEVA,
        ]);

        $this->assertSame(404, $response->status());
        $this->assertSame('', $this->hash($anterior));
    }
}
