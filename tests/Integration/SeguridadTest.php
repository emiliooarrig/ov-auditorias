<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Request;
use App\Models\Usuario;
use Tests\Support\IntegrationTestCase;
use Tests\Support\TestApp;
use Tests\Support\TestDatabase;

/**
 * Controles de la sección 6 del SDD que no cubren las demás pruebas.
 */
final class SeguridadTest extends IntegrationTestCase
{
    public function testLaSesionSeCierraPorInactividad(): void
    {
        $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $this->post('/login', ['correo' => 'ana@anahuac.mx']);
        $this->assertSame(200, $this->get('/mis-talleres')->status());

        $_SESSION['_ultima_actividad'] = time() - 31 * 60;

        $this->assertRedirige('/login', $this->get('/mis-talleres'));
        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('Tu sesión se cerró por inactividad', $this->get('/login')->body());
    }

    public function testEnProduccionNoHayDepuracionYLaCookieEsSecure(): void
    {
        $app = TestApp::make(['env' => 'production', 'debug' => true, 'session' => ['secure' => false]]);

        $this->assertFalse($app->debug());
        $this->assertTrue($app->config('session.secure'));
    }

    public function testEnProduccionUnErrorNoRevelaDetallesYQuedaEnElLog(): void
    {
        $app = TestApp::make([
            'env' => 'production',
            'db' => ['port' => 1] + TestDatabase::config(),  // conexión imposible
        ]);
        $log = TestApp::logDir() . '/app-' . date('Y-m-d') . '.log';
        $antes = is_file($log) ? (string) file_get_contents($log) : '';

        $response = $app->handle(new Request('POST', '/login', [], [
            'correo' => 'ana@anahuac.mx',
            '_csrf' => $app->csrf()->token(),
        ], ['REMOTE_ADDR' => '10.0.0.1']));

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('SQLSTATE', $response->body());
        $this->assertStringContainsString('Ocurrió un error', $response->body());
        $this->assertStringContainsString('max-age=31536000', (string) $response->header('Strict-Transport-Security'));
        $this->assertGreaterThan(strlen($antes), strlen((string) file_get_contents($log)));
    }

    public function testTodasLasRespuestasLlevanCabecerasDeSeguridad(): void
    {
        $response = $this->get('/login');

        foreach (
            [
                'Content-Security-Policy',
                'X-Content-Type-Options',
                'X-Frame-Options',
                'Referrer-Policy',
                'Permissions-Policy',
                'Cache-Control',
            ] as $cabecera
        ) {
            $this->assertNotNull($response->header($cabecera), $cabecera);
        }
    }

    public function testLoginDeUnaIpConDemasiadosFallosSeBloqueaParaCualquierCorreo(): void
    {
        $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        for ($i = 0; $i < 20; $i++) {
            $this->app->db()->execute(
                "INSERT INTO accesos (correo, evento, ip) VALUES (:c, 'login_fallido', '10.0.0.1')",
                ['c' => "intento{$i}@anahuac.mx"]
            );
        }

        $this->post('/login', ['correo' => 'ana@anahuac.mx']);

        $this->assertNull($this->usuarioEnSesion());
        $this->assertStringContainsString('Demasiados intentos fallidos', $this->get('/login')->body());
    }
}
