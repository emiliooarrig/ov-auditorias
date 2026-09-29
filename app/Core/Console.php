<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Entrada y salida para los scripts de bin/.
 */
final class Console
{
    /** @var resource */
    private $input;

    /**
     * @param resource|null $input
     */
    public function __construct($input = null)
    {
        $this->input = $input ?? STDIN;
    }

    public function interactive(): bool
    {
        return stream_isatty($this->input);
    }

    public function line(string $text = ''): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    public function error(string $text): void
    {
        fwrite(STDERR, 'Error: ' . $text . PHP_EOL);
    }

    public function ask(string $question, string $default = ''): string
    {
        fwrite(STDOUT, $question . ($default !== '' ? " [{$default}]" : '') . ': ');
        $answer = $this->readLine();

        return $answer === '' ? $default : $answer;
    }

    /**
     * Pregunta sin mostrar lo que se escribe (contraseñas) cuando hay terminal.
     */
    public function askHidden(string $question): string
    {
        fwrite(STDOUT, $question . ': ');
        $hide = $this->interactive() && DIRECTORY_SEPARATOR === '/';
        if ($hide) {
            shell_exec('stty -echo');
        }
        try {
            return $this->readLine(false);
        } finally {
            if ($hide) {
                shell_exec('stty echo');
                fwrite(STDOUT, PHP_EOL);
            }
        }
    }

    public function confirm(string $question): bool
    {
        return in_array(mb_strtolower($this->ask($question . ' (s/N)')), ['s', 'si', 'sí'], true);
    }

    private function readLine(bool $trim = true): string
    {
        $line = fgets($this->input);
        if ($line === false) {
            return '';
        }
        $line = rtrim($line, "\r\n");

        return $trim ? trim($line) : $line;
    }
}
