<?php

declare(strict_types=1);

namespace App\Core;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use LogicException;

use function FastRoute\simpleDispatcher;

/**
 * Enrutador sobre nikic/fast-route con middleware globales, por grupo y por ruta.
 *
 * Un middleware se declara como nombre de clase (App\Middleware\AuthMiddleware::class)
 * o como [clase, ...argumentos] (p. ej. [RoleMiddleware::class, 'administrador']).
 * Se instancia con la App como primer argumento del constructor.
 *
 * @phpstan-type MiddlewareSpec class-string<Middleware>|array{0: class-string<Middleware>, 1?: mixed, 2?: mixed}
 * @phpstan-type Handler array{0: class-string, 1: string}
 */
final class Router
{
    /** @var list<array{method: string, path: string, handler: Handler, middleware: list<MiddlewareSpec>}> */
    private array $routes = [];

    /** @var list<MiddlewareSpec> */
    private array $global = [];

    /** @var list<MiddlewareSpec> */
    private array $group = [];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * Middleware que se aplica a todas las rutas (antes que los de grupo y ruta).
     *
     * @param list<MiddlewareSpec> $middleware
     */
    public function middleware(array $middleware): self
    {
        $this->global = [...$this->global, ...$middleware];

        return $this;
    }

    /**
     * @param Handler              $handler
     * @param list<MiddlewareSpec> $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param Handler              $handler
     * @param list<MiddlewareSpec> $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param Handler              $handler
     * @param list<MiddlewareSpec> $middleware
     */
    public function add(string $method, string $path, array $handler, array $middleware = []): self
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => [...$this->group, ...$middleware],
        ];

        return $this;
    }

    /**
     * Las rutas definidas dentro del callback heredan los middleware indicados.
     *
     * @param list<MiddlewareSpec>   $middleware
     * @param callable(self): void   $callback
     */
    public function group(array $middleware, callable $callback): void
    {
        $previous = $this->group;
        $this->group = [...$previous, ...$middleware];
        try {
            $callback($this);
        } finally {
            $this->group = $previous;
        }
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $collector): void {
            foreach ($this->routes as $index => $route) {
                $collector->addRoute($route['method'], $route['path'], $index);
            }
        });

        $info = $dispatcher->dispatch($request->method, $request->path);

        if ($info[0] === Dispatcher::NOT_FOUND) {
            throw new HttpException(404);
        }
        if ($info[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            throw new HttpException(405, '', ['Allow' => implode(', ', $info[1])]);
        }

        $route = $this->routes[$info[1]];
        /** @var array<string, string> $params */
        $params = $info[2];
        $request = $request->withParams($params);

        $next = fn (Request $r): Response => $this->callHandler($route['handler'], $r);
        foreach (array_reverse([...$this->global, ...$route['middleware']]) as $spec) {
            $middleware = $this->resolveMiddleware($spec);
            $next = static fn (Request $r): Response => $middleware->process($r, $next);
        }

        return $next($request);
    }

    /**
     * @param Handler $handler
     */
    private function callHandler(array $handler, Request $request): Response
    {
        [$class, $method] = $handler;
        $controller = new $class($this->app);
        if (!method_exists($controller, $method)) {
            throw new LogicException(sprintf('Acción inexistente: %s::%s', $class, $method));
        }

        $response = $controller->$method($request);
        if (!$response instanceof Response) {
            throw new LogicException(sprintf('%s::%s debe devolver una Response.', $class, $method));
        }

        return $response;
    }

    /**
     * @param MiddlewareSpec $spec
     */
    private function resolveMiddleware(string|array $spec): Middleware
    {
        [$class, $args] = is_array($spec) ? [$spec[0], array_slice($spec, 1)] : [$spec, []];

        return new $class($this->app, ...$args);
    }
}
