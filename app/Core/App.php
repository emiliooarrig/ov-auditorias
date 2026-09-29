<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use LogicException;
use Throwable;

/**
 * Contenedor de la aplicación: configuración, servicios compartidos y ciclo de una petición.
 */
final class App
{
    private static ?self $instance = null;

    private ?Database $database = null;
    private Session $session;
    private Csrf $csrf;
    private View $view;
    private Logger $logger;
    private string $urlPath;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly string $basePath, private readonly array $config)
    {
        $this->urlPath = rtrim((string) parse_url((string) ($config['url'] ?? ''), PHP_URL_PATH), '/');
        $this->session = new Session([
            'name' => (string) $this->config('session.name', 'sesion'),
            'secure' => (bool) $this->config('session.secure', true),
            'idle_minutes' => (int) $this->config('session.idle_minutes', 30),
            'path' => $this->urlPath === '' ? '/' : $this->urlPath . '/',
        ]);
        $this->csrf = new Csrf($this->session);
        $this->view = new View($basePath . '/app/Views');
        $this->logger = new Logger($basePath . '/storage/logs');

        self::$instance = $this;
    }

    public static function boot(string $basePath): self
    {
        Dotenv::createImmutable($basePath)->safeLoad();

        /** @var array<string, mixed> $config */
        $config = require $basePath . '/app/Config/app.php';

        date_default_timezone_set((string) $config['timezone']);
        error_reporting(E_ALL);
        ini_set('display_errors', $config['debug'] ? '1' : '0');
        ini_set('log_errors', '1');

        return new self($basePath, $config);
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new LogicException('La aplicación no se ha iniciado.');
    }

    public function handle(Request $request): Response
    {
        try {
            $this->session->start();

            $router = new Router($this);
            /** @var callable(Router): void $routes */
            $routes = require $this->basePath('app/Config/routes.php');
            $routes($router);

            $response = $router->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->errorResponse($e->status, $e->getMessage())->withHeaders($e->headers);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->path,
            ]);
            $response = $this->errorResponse(500, $this->debug() ? $e->getMessage() : '');
        }

        return $response->withSecurityHeaders($request->isSecure() || !$this->debug());
    }

    /**
     * Página de error con el layout de la aplicación; si la vista falla, texto plano.
     */
    public function errorResponse(int $status, string $detalle = ''): Response
    {
        $template = in_array($status, [403, 404, 405, 500], true) ? (string) $status : '500';

        try {
            return Response::html($this->view->render('errors/' . $template, [
                'titulo' => 'Error ' . $status,
                'detalle' => $detalle,
            ]), $status);
        } catch (Throwable) {
            return new Response('Error ' . $status, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function debug(): bool
    {
        return (bool) $this->config('debug', false);
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /**
     * Ruta de la subcarpeta pública (vacía si la app vive en la raíz del dominio).
     */
    public function urlPath(): string
    {
        return $this->urlPath;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function url(string $path = '/', array $query = []): string
    {
        $query = array_filter($query, static fn (mixed $v): bool => $v !== null && $v !== '');
        $url = $this->urlPath . '/' . ltrim($path, '/');

        return $url . ($query === [] ? '' : '?' . http_build_query($query));
    }

    public function db(): Database
    {
        return $this->database ??= new Database((array) $this->config('db', []));
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function csrf(): Csrf
    {
        return $this->csrf;
    }

    public function view(): View
    {
        return $this->view;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }
}
