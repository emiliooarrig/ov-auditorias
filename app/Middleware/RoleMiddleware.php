<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Permite la ruta solo a los roles indicados; a cualquier otro responde 403.
 * Se declara después de AuthMiddleware: [RoleMiddleware::class, 'administrador'].
 */
final class RoleMiddleware implements Middleware
{
    /** @var list<string> */
    private array $roles;

    public function __construct(private readonly App $app, string ...$roles)
    {
        $this->roles = array_values($roles);
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($this->app->auth()->rol(), $this->roles, true)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}
