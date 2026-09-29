<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;

/**
 * Funciones globales de apoyo para configuración y vistas.
 */

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return $value === false || $value === '' ? $default : $value;
}

function env_bool(string $key, bool $default): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
}

/**
 * Escapa cualquier valor para imprimirlo en HTML. Toda salida de las vistas pasa por aquí.
 */
function e(mixed $value): string
{
    if ($value === null || is_array($value) || (is_object($value) && !$value instanceof Stringable)) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function app(): App
{
    return App::instance();
}

/**
 * @param array<string, mixed> $query
 */
function url(string $path = '/', array $query = []): string
{
    return app()->url($path, $query);
}

function asset(string $path): string
{
    $path = '/assets/' . ltrim($path, '/');
    $file = app()->basePath('public' . $path);

    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function csrf_token(): string
{
    return app()->csrf()->token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . e(csrf_token()) . '">';
}

/**
 * Valor enviado en el formulario anterior (tras un error de validación).
 */
function old(string $key, string $default = ''): string
{
    $value = app()->session()->old($key);

    return is_scalar($value) ? (string) $value : $default;
}
