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
 * Ícono decorativo del sprite de partials/iconos.php. Siempre acompaña a un texto visible o para lector.
 */
function icono(string $nombre, string $clase = ''): string
{
    return '<svg class="icon' . ($clase !== '' ? ' ' . e($clase) : '') . '" aria-hidden="true" focusable="false">'
        . '<use href="#i-' . e($nombre) . '"></use></svg>';
}

/**
 * Insignia de estado de un taller: ícono + texto + color, para no depender solo del color.
 */
function estado_insignia(string $estado): string
{
    [$icono, $texto] = match ($estado) {
        'realizado' => ['check-circulo', 'Realizado'],
        'no_realizado' => ['x-circulo', 'No realizado'],
        default => ['reloj', 'Programado'],
    };

    return '<span class="badge badge--' . estado_clase($estado) . '">' . icono($icono) . ' ' . e($texto) . '</span>';
}

/**
 * Mensaje de error bajo un campo de formulario, con su ícono.
 */
function error_campo(string $id, string $mensaje): string
{
    return '<span class="field__error" id="' . e($id) . '">' . icono('alerta') . ' ' . e($mensaje) . '</span>';
}

/**
 * Fecha AAAA-MM-DD como "lun 5 oct 2026".
 */
function fecha_corta(string $fecha): string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', substr($fecha, 0, 10));
    if ($dt === false) {
        return $fecha;
    }
    $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    return $dias[(int) $dt->format('w')] . ' ' . $dt->format('j') . ' ' . $meses[(int) $dt->format('n') - 1]
        . ' ' . $dt->format('Y');
}

/**
 * Partes de una fecha AAAA-MM-DD para la hoja de calendario: ['dia' => 'lun', 'numero' => '5', 'mes' => 'oct'].
 *
 * @return array{dia: string, numero: string, mes: string}
 */
function fecha_partes(string $fecha): array
{
    $partes = explode(' ', fecha_corta($fecha));

    return ['dia' => $partes[0], 'numero' => $partes[1] ?? $fecha, 'mes' => $partes[2] ?? ''];
}

/**
 * Clase CSS de un estado ("no_realizado" → "no-realizado").
 */
function estado_clase(string $estado): string
{
    return in_array($estado, ['realizado', 'no_realizado'], true) ? str_replace('_', '-', $estado) : 'programado';
}

/**
 * Fecha y hora AAAA-MM-DD HH:MM:SS como "lun 5 oct 2026, 10:30".
 */
function fecha_hora(string $valor): string
{
    return fecha_corta($valor) . ', ' . substr($valor, 11, 5);
}

/**
 * Horario "10:00–12:00" a partir de dos columnas TIME.
 */
function horario(string $inicio, string $fin): string
{
    return substr($inicio, 0, 5) . '–' . substr($fin, 0, 5);
}

/**
 * Error de validación de un campo del formulario anterior, o null.
 */
function error_de(string $campo): ?string
{
    return app()->session()->error($campo);
}

/**
 * Valor enviado en el formulario anterior (tras un error de validación).
 */
function old(string $key, string $default = ''): string
{
    $value = app()->session()->old($key);

    return is_scalar($value) ? (string) $value : $default;
}
