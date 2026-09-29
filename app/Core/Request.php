<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Petición HTTP inmutable: método, ruta, parámetros GET/POST y parámetros de la ruta.
 */
final class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, mixed>  $server
     * @param array<string, string> $params
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $server = [],
        private readonly array $params = [],
    ) {
    }

    public static function fromGlobals(string $urlPath = ''): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            $method,
            self::normalizePath(is_string($uriPath) ? rawurldecode($uriPath) : '/', $urlPath),
            $_GET,
            $_POST,
            $_SERVER,
        );
    }

    /**
     * Quita la subcarpeta de la app, asegura la diagonal inicial y elimina la final.
     */
    public static function normalizePath(string $path, string $urlPath = ''): string
    {
        if ($urlPath !== '' && ($path === $urlPath || str_starts_with($path, $urlPath . '/'))) {
            $path = substr($path, strlen($urlPath));
        }
        $path = '/' . trim($path, '/');

        return preg_replace('#/+#', '/', $path) ?? '/';
    }

    /**
     * @param array<string, string> $params
     */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->body, $this->server, $params);
    }

    public function param(string $name): ?string
    {
        return $this->params[$name] ?? null;
    }

    public function intParam(string $name): int
    {
        return (int) ($this->params[$name] ?? 0);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Parámetro GET como texto recortado; vacío si no llegó o no es texto.
     */
    public function queryString(string $key): string
    {
        $value = $this->query[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Campo POST como texto recortado; vacío si no llegó o no es texto.
     */
    public function inputString(string $key): string
    {
        $value = $this->body[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function allInput(): array
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * IP del cliente. No se confía en cabeceras de proxy como X-Forwarded-For.
     */
    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '';

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): ?string
    {
        $agent = $this->server['HTTP_USER_AGENT'] ?? null;

        return is_string($agent) && $agent !== '' ? mb_substr($agent, 0, 255) : null;
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return (is_string($https) && $https !== '' && strtolower($https) !== 'off')
            || ($this->server['SERVER_PORT'] ?? null) == 443;
    }

    public function isSafeMethod(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
