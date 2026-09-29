<?php

declare(strict_types=1);

use App\Controllers\ActividadController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\Usuario;

/*
 * Rutas de la aplicación (sección 5.1 del SDD). Los ids numéricos usan {id:\d+}
 * para no chocar con rutas fijas como /actividades/nueva.
 */
return static function (Router $router): void {
    // Todo POST exige token CSRF.
    $router->middleware([CsrfMiddleware::class]);

    $soloAuditor = [AuthMiddleware::class, [RoleMiddleware::class, Usuario::AUDITOR]];

    // Público (RF-01, RF-02)
    $router->group([GuestMiddleware::class], static function (Router $r): void {
        $r->get('/login', [AuthController::class, 'mostrarLogin']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->get('/registro', [AuthController::class, 'mostrarRegistro']);
        $r->post('/registro', [AuthController::class, 'registrar']);
    });
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

    // Panel central: el controlador redirige al auditor a /mis-talleres (RF-03)
    $router->get('/', [DashboardController::class, 'index'], [AuthMiddleware::class]);

    // Auditor (RF-11)
    $router->get('/mis-talleres', [DashboardController::class, 'misTalleres'], $soloAuditor);

    // Administrador
    $soloAdministrador = [AuthMiddleware::class, [RoleMiddleware::class, Usuario::ADMINISTRADOR]];
    $router->group($soloAdministrador, static function (Router $r): void {
        // Talleres (RF-06, RF-09). En la fase 4 el detalle se abre también al auditor asignado.
        $r->get('/actividades/nueva', [ActividadController::class, 'crear']);
        $r->post('/actividades', [ActividadController::class, 'guardar']);
        $r->get('/actividades/{id:\d+}', [ActividadController::class, 'ver']);
        $r->get('/actividades/{id:\d+}/editar', [ActividadController::class, 'editar']);
        $r->post('/actividades/{id:\d+}', [ActividadController::class, 'actualizar']);
        $r->post('/actividades/{id:\d+}/desactivar', [ActividadController::class, 'desactivar']);
    });
};
