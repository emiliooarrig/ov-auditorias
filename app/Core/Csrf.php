<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Token CSRF por sesión. Se envía en un campo oculto y se compara con hash_equals().
 */
final class Csrf
{
    public const FIELD = '_csrf';
    public const HEADER = 'X-CSRF-Token';
    private const KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function validate(mixed $token): bool
    {
        $expected = $this->session->get(self::KEY);

        return is_string($expected) && $expected !== ''
            && is_string($token) && hash_equals($expected, $token);
    }

    /**
     * Descarta el token actual; se llama al iniciar y cerrar sesión.
     */
    public function rotate(): void
    {
        $this->session->forget(self::KEY);
    }
}
