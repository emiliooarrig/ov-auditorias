<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Exige sesión activa; sin ella redirige a /login.
 */
final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->app->auth()->check()) {
            $this->app->session()->flash('aviso', 'Ingresa con tu correo institucional para continuar.');

            return Response::redirect($this->app->url('/login'));
        }

        return $next($request);
    }
}
