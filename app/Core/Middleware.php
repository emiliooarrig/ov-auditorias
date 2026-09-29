<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Filtro que se ejecuta antes del controlador. Devuelve $next($request) para continuar
 * o una respuesta propia (redirección, error) para cortar la petición.
 */
interface Middleware
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
