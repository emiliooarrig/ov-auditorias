<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\App;

/**
 * Crea una App con configuración de prueba, sin leer .env ni abrir la base de datos.
 */
final class TestApp
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function make(array $overrides = []): App
    {
        $_SESSION = [];

        return new App(dirname(__DIR__, 2), array_replace_recursive([
            'name' => 'Pruebas',
            'env' => 'testing',
            'debug' => false,
            'url' => 'http://localhost',
            'timezone' => 'America/Mexico_City',
            'db' => [],
            'session' => ['name' => 'prueba', 'secure' => false, 'idle_minutes' => 30],
            'auth' => ['allowed_domains' => ['anahuac.mx'], 'max_fails' => 5, 'lock_minutes' => 15],
            'pagination' => ['per_page' => 20],
            'log_dir' => self::logDir(),
        ], $overrides));
    }

    /**
     * Carpeta temporal para los logs de las pruebas (no ensucia storage/logs/).
     */
    public static function logDir(): string
    {
        $dir = sys_get_temp_dir() . '/auditores-talleres-pruebas';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }
}
