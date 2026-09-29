<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Core\Router;
use App\Middleware\CsrfMiddleware;

/*
 * Rutas de la aplicación (sección 5.1 del SDD). Los ids numéricos usan {id:\d+}
 * para no chocar con rutas fijas como /actividades/nueva.
 */
return static function (Router $router): void {
    // Todo POST exige token CSRF.
    $router->middleware([CsrfMiddleware::class]);

    $router->get('/', [DashboardController::class, 'index']);
};
