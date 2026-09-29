<?php

declare(strict_types=1);

/*
 * Configuración de la aplicación. Los valores salen del archivo .env (ver .env.example).
 */

$dominios = array_values(array_filter(array_map(
    static fn (string $d): string => strtolower(trim($d)),
    explode(',', (string) env('AUTH_ALLOWED_DOMAINS', 'anahuac.mx'))
)));

return [
    'name' => (string) env('APP_NAME', 'Auditores de Talleres'),
    'env' => (string) env('APP_ENV', 'production'),
    'debug' => env_bool('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => (string) env('APP_TIMEZONE', 'America/Mexico_City'),

    'db' => [
        'host' => (string) env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => (string) env('DB_NAME', 'auditores_talleres'),
        'user' => (string) env('DB_USER', 'root'),
        'pass' => (string) env('DB_PASS', ''),
        'timezone' => (string) env('DB_TIMEZONE', '-06:00'),
    ],

    'session' => [
        'name' => 'auditores_sesion',
        'secure' => env_bool('SESSION_SECURE', true),
        'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 30),
    ],

    'auth' => [
        'allowed_domains' => $dominios,
        'max_fails' => (int) env('AUTH_MAX_FAILS', 5),
        'lock_minutes' => (int) env('AUTH_LOCK_MINUTES', 15),
        'password_min' => 10,
    ],

    'pagination' => [
        'per_page' => 20,
    ],
];
