<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Pantallas de ingreso y registro: quien ya tiene sesión va directo a su inicio.
 */
final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $auth = $this->app->auth();
        if ($auth->check()) {
            return Response::redirect($this->app->url($auth->inicio()));
        }

        return $next($request);
    }
}
