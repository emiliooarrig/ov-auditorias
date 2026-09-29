<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contraseñas de administrador: Argon2id si el servidor lo soporta, si no bcrypt.
 */
final class Password
{
    public const MIN_LENGTH = 10;

    public static function hash(string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    public static function verify(string $password, ?string $hash): bool
    {
        return $hash !== null && $hash !== '' && password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    /**
     * Mensaje de error si la contraseña no cumple la política; null si es válida.
     */
    public static function validate(string $password, string $confirmation): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'La contraseña debe tener al menos ' . self::MIN_LENGTH . ' caracteres.';
        }
        if (strlen($password) > 4096) {
            return 'La contraseña es demasiado larga.';
        }
        if (!hash_equals($password, $confirmation)) {
            return 'Las contraseñas no coinciden.';
        }

        return null;
    }

    private static function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
