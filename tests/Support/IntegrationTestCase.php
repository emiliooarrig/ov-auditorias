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

    /**
     * Crea un administrador e inicia su sesión. Devuelve su id.
     */
    protected function entrarComoAdministrador(string $correo = 'admin@anahuac.mx'): int
    {
        $id = $this->crearUsuario(Usuario::ADMINISTRADOR, $correo, 'Clave-segura-2026');
        $this->post('/login', ['correo' => $correo]);
        $this->post('/login', ['password' => 'Clave-segura-2026']);
        $this->assertSame($id, $this->usuarioEnSesion());

        return $id;
    }

    /**
     * Crea (si no existe) un auditor e inicia su sesión. Devuelve su id.
     */
    protected function entrarComoAuditor(string $correo = 'auditor@anahuac.mx'): int
    {
        $existente = (new Usuario($this->app->db()))->buscarPorCorreo($correo);
        $id = $existente['id'] ?? $this->crearUsuario(Usuario::AUDITOR, $correo);
        $this->post('/logout');
        $this->post('/login', ['correo' => $correo]);
        $this->assertSame($id, $this->usuarioEnSesion());

        return $id;
    }

    /**
     * Inserta un taller directamente en la base (carreras y edificios vienen de seeds.sql).
     *
     * @param array<string, mixed> $datos
     */
    protected function crearActividad(int $creadoPor, array $datos = []): int
    {
        $datos += [
            'nombre' => 'Taller de prueba',
            'carrera_id' => 1,
            'edificio_id' => 1,
            'fecha' => '2026-10-15',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'programado',
            'motivo_no_realizado' => null,
            'activo' => 1,
        ];
        $this->app->db()->execute(
            'INSERT INTO actividades (nombre, carrera_id, edificio_id, fecha, hora_inicio, hora_fin, estado,
                                      motivo_no_realizado, activo, creado_por)
             VALUES (:nombre, :carrera_id, :edificio_id, :fecha, :hora_inicio, :hora_fin, :estado,
                     :motivo_no_realizado, :activo, :creado_por)',
            $datos + ['creado_por' => $creadoPor]
        );

        return $this->app->db()->lastInsertId();
    }

    protected function asignar(int $actividadId, int $usuarioId, int $asignadoPor): void
    {
        $this->app->db()->execute(
            'INSERT INTO asignaciones (actividad_id, usuario_id, asignado_por) VALUES (:a, :u, :p)',
            ['a' => $actividadId, 'u' => $usuarioId, 'p' => $asignadoPor]
        );
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
