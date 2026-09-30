<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesión PHP endurecida: modo estricto, cookie HttpOnly/SameSite, cierre por inactividad,
 * mensajes flash y datos del formulario anterior.
 */
final class Session
{
    private const ULTIMA_ACTIVIDAD = '_ultima_actividad';
    private const FLASH = '_flash';
    private const OLD = '_old';
    private const ERRORS = '_errores';

    /** @var array<string, mixed> */
    private array $oldInput = [];

    /** @var array<string, mixed> */
    private array $errors = [];

    /**
     * @param array{name: string, secure: bool, idle_minutes: int, path: string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // En consola (pruebas, scripts de bin/) no hay cookies: la sesión es un arreglo en memoria.
        if (PHP_SAPI === 'cli') {
            $_SESSION ??= [];
        } else {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            session_name($this->config['name']);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $this->config['path'],
                'secure' => $this->config['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $this->expireIfIdle();

        // Los datos y errores del formulario anterior viven solo durante esta petición.
        $old = $_SESSION[self::OLD] ?? [];
        $this->oldInput = is_array($old) ? $old : [];
        $errors = $_SESSION[self::ERRORS] ?? [];
        $this->errors = is_array($errors) ? $errors : [];
        unset($_SESSION[self::OLD], $_SESSION[self::ERRORS]);
    }

    private function expireIfIdle(): void
    {
        $now = time();
        $last = $_SESSION[self::ULTIMA_ACTIVIDAD] ?? null;

        if (is_int($last) && $now - $last > $this->config['idle_minutes'] * 60) {
            $teniaUsuario = isset($_SESSION['usuario_id']);
            $_SESSION = [];
            $this->regenerate();
            if ($teniaUsuario) {
                $this->flash('aviso', 'Tu sesión se cerró por inactividad. Vuelve a ingresar.');
            }
        }

        $_SESSION[self::ULTIMA_ACTIVIDAD] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Nuevo identificador de sesión (tras iniciar o cerrar sesión) para evitar fijación.
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Vacía la sesión y emite un identificador nuevo; se usa al cerrar sesión.
     */
    public function clear(): void
    {
        $_SESSION = [];
        $this->regenerate();
        $_SESSION[self::ULTIMA_ACTIVIDAD] = time();
    }

    /**
     * Mensaje para la siguiente petición. Con $alerta, app.js lo muestra como ventana de SweetAlert;
     * sin JavaScript se ve como aviso normal.
     */
    public function flash(string $type, string $message, bool $alerta = false): void
    {
        $flash = $_SESSION[self::FLASH] ?? [];
        $flash = is_array($flash) ? $flash : [];
        $flash[] = ['tipo' => $type, 'mensaje' => $message, 'alerta' => $alerta];
        $_SESSION[self::FLASH] = $flash;
    }

    /**
     * Devuelve y borra los mensajes flash pendientes.
     *
     * @return list<array{tipo: string, mensaje: string, alerta?: bool}>
     */
    public function pullFlash(): array
    {
        $flash = $_SESSION[self::FLASH] ?? [];
        unset($_SESSION[self::FLASH]);

        return is_array($flash) ? array_values($flash) : [];
    }

    /**
     * Guarda los campos enviados para volver a llenar el formulario en la siguiente petición.
     *
     * @param array<string, mixed> $input
     */
    public function flashInput(array $input): void
    {
        unset($input['_csrf'], $input['password'], $input['password_confirmacion']);
        $_SESSION[self::OLD] = $input;
    }

    public function old(string $key): mixed
    {
        return $this->oldInput[$key] ?? null;
    }

    /**
     * Guarda errores de validación por campo para mostrarlos en la siguiente petición.
     *
     * @param array<string, string> $errors
     */
    public function flashErrors(array $errors): void
    {
        $_SESSION[self::ERRORS] = $errors;
    }

    public function error(string $field): ?string
    {
        $error = $this->errors[$field] ?? null;

        return is_string($error) ? $error : null;
    }
}
