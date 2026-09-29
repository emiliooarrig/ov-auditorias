<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro de errores en storage/logs/app-AAAA-MM-DD.log (una línea JSON por evento).
 */
final class Logger
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode([
            'fecha' => date('c'),
            'nivel' => $level,
            'mensaje' => $message,
            'contexto' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $file = $this->directory . '/app-' . date('Y-m-d') . '.log';
        if (@file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log((string) $line);
        }
    }
}
