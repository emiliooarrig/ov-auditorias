<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Corta el flujo de una petición y responde con el código HTTP indicado (403, 404, 405...).
 */
final class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }
}
