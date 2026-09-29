<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Crea la base de datos y ejecuta database/schema.sql y database/seeds.sql.
 * Lo usan bin/instalar-bd.php y las pruebas de integración.
 */
final class SchemaInstaller
{
    /**
     * @param array<string, mixed> $config Configuración 'db' de la aplicación (sin importar 'name').
     */
    public function __construct(private readonly array $config, private readonly string $databaseDir)
    {
    }

    public function databaseExists(string $name): bool
    {
        $statement = $this->server()->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$name]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Crea la base y el esquema. Con $reset elimina antes la base existente (se pierden los datos).
     */
    public function install(string $name, bool $reset = false, bool $seeds = true): void
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
            throw new InvalidArgumentException('Nombre de base de datos no válido: ' . $name);
        }

        $pdo = $this->server();
        if ($reset) {
            $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        } elseif ($this->databaseExists($name)) {
            throw new RuntimeException("La base de datos '{$name}' ya existe. Usa --reiniciar para recrearla.");
        }

        $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");

        $this->runFile($pdo, $this->databaseDir . '/schema.sql');
        if ($seeds) {
            $this->runFile($pdo, $this->databaseDir . '/seeds.sql');
        }
    }

    /**
     * Ejecuta un archivo SQL sentencia por sentencia (separadas por ';' al final de línea).
     */
    private function runFile(PDO $pdo, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('No se pudo leer ' . $file);
        }

        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
    }

    private function server(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 3306),
        );

        return new PDO($dsn, (string) ($this->config['user'] ?? ''), (string) ($this->config['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
