<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\App;
use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\Usuario;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas que recorren la aplicación completa (rutas, middleware, controladores y base de datos).
 * Cada petición pasa por App::handle(); la sesión persiste entre peticiones de la misma prueba.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected App $app;

    protected function setUp(): void
    {
        $error = TestDatabase::preparar();
        if ($error !== null) {
            $this->markTestSkipped($error);
        }
        $this->app = TestApp::make(['db' => TestDatabase::config()]);
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query, [], $this->server()));
    }

    /**
     * POST con el token CSRF de la sesión actual (salvo que se indique lo contrario).
     *
     * @param array<string, mixed> $data
     */
    protected function post(string $path, array $data = [], bool $conCsrf = true): Response
    {
        if ($conCsrf) {
            $data['_csrf'] = $this->app->csrf()->token();
        }

        return $this->app->handle(new Request('POST', $path, [], $data, $this->server()));
    }

    protected function crearUsuario(string $rol, string $correo, ?string $password = null, bool $activo = true): int
    {
        $usuarios = new Usuario($this->app->db());
        $id = $usuarios->crear($rol, 'Prueba', 'Usuario', $correo, $password === null ? null : Password::hash($password));
        if (!$activo) {
            $this->app->db()->execute('UPDATE usuarios SET activo = 0 WHERE id = :id', ['id' => $id]);
        }

        return $id;
    }

    protected function usuarioEnSesion(): ?int
    {
        $id = $_SESSION['usuario_id'] ?? null;

        return is_int($id) ? $id : null;
    }

    protected function assertRedirige(string $ruta, Response $response): void
    {
        $this->assertSame(303, $response->status(), 'Se esperaba una redirección');
        $this->assertSame($ruta, $response->header('Location'));
    }

    /**
     * @return array<string, string>
     */
    private function server(): array
    {
        return ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_USER_AGENT' => 'PHPUnit'];
    }
}
