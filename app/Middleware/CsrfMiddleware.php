<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Exige un token CSRF válido en toda petición que cambie datos (POST y demás métodos no seguros).
 */
final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$request->isSafeMethod()) {
            $token = $request->input(Csrf::FIELD) ?? $request->header(Csrf::HEADER);
            if (!$this->app->csrf()->validate($token)) {
                throw new HttpException(
                    403,
                    'El formulario expiró o no es válido. Recarga la página e inténtalo de nuevo.',
                );
            }
        }

        return $next($request);
    }
}
