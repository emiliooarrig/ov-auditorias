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

    protected const CLAVE = 'Clave-segura-2026';

    /**
     * Crea un administrador (los únicos usuarios con cuenta). Devuelve su id.
     */
    protected function crearAdministrador(
        string $correo = 'admin@anahuac.mx',
        string $password = self::CLAVE,
        bool $activo = true,
    ): int {
        $id = (new Usuario($this->app->db()))->crear('Prueba', 'Usuario', $correo, Password::hash($password));
        if (!$activo) {
            $this->app->db()->execute('UPDATE usuarios SET activo = 0 WHERE id = :id', ['id' => $id]);
        }

        return $id;
    }

    /**
     * Cuenta de auditor como las de antes del 2026-10-10 (los auditores entraban sin contraseña).
     */
    protected function crearCuentaDeAuditor(string $correo, bool $activo = true): int
    {
        $db = $this->app->db();
        $db->execute("INSERT IGNORE INTO roles (nombre) VALUES ('auditor')");
        $db->execute(
            "INSERT INTO usuarios (rol_id, nombre, apellidos, correo, activo)
             VALUES ((SELECT id FROM roles WHERE nombre = 'auditor'), 'Antes', 'Auditor', :correo, :activo)",
            ['correo' => $correo, 'activo' => $activo]
        );

        return $db->lastInsertId();
    }

    /**
     * Crea un administrador (si no existe) e inicia su sesión. Devuelve su id.
     */
    protected function entrarComoAdministrador(string $correo = 'admin@anahuac.mx'): int
    {
        $existente = (new Usuario($this->app->db()))->buscarPorCorreo($correo);
        $id = $existente['id'] ?? $this->crearAdministrador($correo);
        $this->post('/logout');
        $this->post('/login', ['correo' => $correo, 'password' => self::CLAVE]);
        $this->assertSame($id, $this->usuarioEnSesion());

        return $id;
    }

    /**
     * Da de alta un alumno directamente en la base. Devuelve su id. $horario es el nombre del horario
     * (Matutino, Vespertino o Todo el día, de seeds.sql); null lo deja sin horario.
     */
    protected function crearAlumno(
        string $nombre = 'Ana',
        string $apellidos = 'López Ruiz',
        bool $activo = true,
        ?string $horario = 'Todo el día',
    ): int {
        $this->app->db()->execute(
            'INSERT INTO alumnos (nombre, apellidos, activo, horario_id)
             VALUES (:nombre, :apellidos, :activo, (SELECT id FROM horarios_alumno WHERE nombre = :horario))',
            ['nombre' => $nombre, 'apellidos' => $apellidos, 'activo' => $activo, 'horario' => $horario]
        );

        return $this->app->db()->lastInsertId();
    }

    /**
     * Id de un horario de alumno por su nombre (Matutino, Vespertino o Todo el día).
     */
    protected function horarioId(string $nombre): int
    {
        return $this->contar('SELECT id FROM horarios_alumno WHERE nombre = ?', [$nombre]);
    }

    /**
     * Inserta un taller directamente en la base (carreras, edificios y grupos vienen de seeds.sql).
     * El horario es el del grupo: 'grupo' es su número (1 a 6, Taller 1 por omisión).
     *
     * @param array<string, mixed> $datos
     */
    protected function crearActividad(int $creadoPor, array $datos = []): int
    {
        $grupo = $this->app->db()->fetchOne(
            'SELECT id, hora_inicio, hora_fin FROM grupos_taller WHERE numero = ?',
            [(int) ($datos['grupo'] ?? 1)]
        );
        $this->assertNotNull($grupo, 'El grupo existe en seeds.sql');
        unset($datos['grupo']);
        $datos += [
            'nombre' => 'Taller de prueba',
            'carrera_id' => 1,
            'edificio_id' => 1,
            'grupo_id' => $grupo['id'],
            'fecha' => '2026-10-15',
            'hora_inicio' => $grupo['hora_inicio'],
            'hora_fin' => $grupo['hora_fin'],
            'estado' => 'programado',
            'motivo_no_realizado' => null,
            'activo' => 1,
        ];
        $this->app->db()->execute(
            'INSERT INTO actividades (nombre, carrera_id, edificio_id, grupo_id, fecha, hora_inicio, hora_fin,
                                      estado, motivo_no_realizado, activo, creado_por)
             VALUES (:nombre, :carrera_id, :edificio_id, :grupo_id, :fecha, :hora_inicio, :hora_fin,
                     :estado, :motivo_no_realizado, :activo, :creado_por)',
            $datos + ['creado_por' => $creadoPor]
        );

        return $this->app->db()->lastInsertId();
    }

    protected function asignar(int $actividadId, int $alumnoId, int $asignadoPor, string $origen = 'manual'): int
    {
        $this->app->db()->execute(
            'INSERT INTO asignaciones (actividad_id, alumno_id, origen, asignado_por) VALUES (:a, :al, :o, :p)',
            ['a' => $actividadId, 'al' => $alumnoId, 'o' => $origen, 'p' => $asignadoPor]
        );

        return $this->app->db()->lastInsertId();
    }

    /**
     * @param array<int|string, mixed> $params
     */
    protected function contar(string $sql, array $params = []): int
    {
        return (int) $this->app->db()->fetchValue($sql, $params);
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
