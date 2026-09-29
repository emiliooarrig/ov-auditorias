<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\App;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Middleware de prueba que corta la petición con el texto recibido como argumento.
 */
final class BloqueaMiddleware implements Middleware
{
    public function __construct(private readonly App $app, private readonly string $texto)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        return new Response($this->texto, 403);
    }
}
