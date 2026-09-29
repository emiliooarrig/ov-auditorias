<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\SchemaInstaller;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Base de datos de pruebas: se recrea una vez por corrida y se vacía antes de cada prueba.
 */
final class TestDatabase
{
    private static bool $instalada = false;
    private static ?string $error = null;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'host' => (string) \env('DB_HOST', '127.0.0.1'),
            'port' => (int) \env('DB_PORT', 3306),
            'name' => (string) \env('DB_TEST_NAME', 'auditores_talleres_test'),
            'user' => (string) \env('DB_USER', 'root'),
            'pass' => (string) \env('DB_PASS', ''),
            'timezone' => (string) \env('DB_TIMEZONE', '-06:00'),
        ];
    }

    /**
     * Deja la base lista y vacía. Devuelve un mensaje si MySQL no está disponible.
     */
    public static function preparar(): ?string
    {
        $config = self::config();
        $nombre = (string) $config['name'];
        if (!str_ends_with($nombre, '_test')) {
            throw new RuntimeException("Por seguridad, la base de pruebas debe terminar en _test ({$nombre}).");
        }

        if (!self::$instalada && self::$error === null) {
            try {
                (new SchemaInstaller($config, dirname(__DIR__, 2) . '/database'))->install($nombre, true);
                self::$instalada = true;
            } catch (PDOException $e) {
                self::$error = 'MySQL no disponible para pruebas de integración: ' . $e->getMessage();
            }
        }
        if (self::$error !== null) {
            return self::$error;
        }

        self::vaciar($config);

        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function vaciar(array $config): void
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']),
            (string) $config['user'],
            (string) $config['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['accesos', 'bitacora_estados', 'asignaciones', 'actividades', 'usuarios'] as $tabla) {
            $pdo->exec("TRUNCATE TABLE {$tabla}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
