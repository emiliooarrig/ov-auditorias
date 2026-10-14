<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Pantalla de ingreso: quien ya tiene sesión va directo al panel central.
 */
final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($this->app->auth()->check()) {
            return Response::redirect($this->app->url('/'));
        }

        return $next($request);
    }
}
