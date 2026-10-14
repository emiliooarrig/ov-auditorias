<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\SchemaInstaller;
use Tests\Support\IntegrationTestCase;
use Tests\Support\TestDatabase;

/**
 * Migración del 2026-10-10: los auditores pasan a la tabla alumnos y las asignaciones apuntan a ella.
 */
final class MigracionAlumnosTest extends IntegrationTestCase
{
    public function testPasaLosAuditoresAAlumnosConSusAsignacionesYEsIdempotente(): void
    {
        $db = $this->app->db();
        $config = TestDatabase::config();
        $instalador = new SchemaInstaller($config, dirname(__DIR__, 2) . '/database');
        $admin = $this->crearAdministrador();
        $taller = $this->crearActividad($admin);

        try {
            // Esquema anterior: sin tabla alumnos y con las asignaciones apuntando a usuarios.
            $db->execute('ALTER TABLE asignaciones DROP FOREIGN KEY fk_asignaciones_alumno');
            $db->execute(
                'ALTER TABLE asignaciones DROP INDEX idx_asignaciones_alumno, DROP COLUMN origen,
                 CHANGE alumno_id usuario_id INT UNSIGNED NOT NULL'
            );
            $db->execute(
                'ALTER TABLE asignaciones ADD KEY idx_asignaciones_usuario (usuario_id),
                 ADD CONSTRAINT fk_asignaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)'
            );
            $db->execute('DROP TABLE alumnos');
            $ana = $this->crearCuentaDeAuditor('ana@anahuac.mx');
            $luis = $this->crearCuentaDeAuditor('luis@anahuac.mx', false);
            $db->execute(
                'INSERT INTO asignaciones (actividad_id, usuario_id, asignado_por) VALUES (?, ?, ?)',
                [$taller, $ana, $admin]
            );

            $instalador->migrate((string) $config['name']);
            $aplicadas = $instalador->migrate((string) $config['name']);

            $this->assertContains('2026-10-10_alumnos_sin_acceso.sql', $aplicadas);
            $this->assertSame(
                [
                    ['id' => $ana, 'nombre' => 'Antes', 'apellidos' => 'Auditor', 'activo' => 1, 'creado_por' => null],
                    ['id' => $luis, 'nombre' => 'Antes', 'apellidos' => 'Auditor', 'activo' => 0, 'creado_por' => null],
                ],
                $db->fetchAll('SELECT id, nombre, apellidos, activo, creado_por FROM alumnos ORDER BY id')
            );
            $this->assertSame(
                [['actividad_id' => $taller, 'alumno_id' => $ana, 'origen' => 'manual']],
                $db->fetchAll('SELECT actividad_id, alumno_id, origen FROM asignaciones')
            );
            // Las cuentas de auditor quedan desactivadas; el administrador, intacto.
            $this->assertSame(
                [$admin => 1, $ana => 0, $luis => 0],
                array_map('intval', array_column($db->fetchAll('SELECT id, activo FROM usuarios'), 'activo', 'id'))
            );
            // La llave foránea nueva protege la integridad.
            $this->expectException(\PDOException::class);
            $db->execute(
                'INSERT INTO asignaciones (actividad_id, alumno_id, asignado_por) VALUES (?, 99999, ?)',
                [$taller, $admin]
            );
        } finally {
            TestDatabase::reinstalar();
        }
    }

    public function testEnUnaBaseNuevaNoCambiaNada(): void
    {
        $config = TestDatabase::config();
        $admin = $this->crearAdministrador();
        $ana = $this->crearAlumno();
        $this->asignar($this->crearActividad($admin), $ana, $admin, 'aleatoria');

        (new SchemaInstaller($config, dirname(__DIR__, 2) . '/database'))->migrate((string) $config['name']);

        $this->assertSame(1, $this->contar('SELECT activo FROM usuarios WHERE id = ?', [$admin]));
        $this->assertSame(
            [['alumno_id' => $ana, 'origen' => 'aleatoria']],
            $this->app->db()->fetchAll('SELECT alumno_id, origen FROM asignaciones')
        );
    }
}
