<?php

declare(strict_types=1);

use App\Controllers\ActividadController;
use App\Controllers\AsignacionController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UsuarioController;
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

    // Administrador, o auditor solo si el taller está asignado a él: el controlador responde 404
    // para talleres ajenos (RF-07, RF-09).
    $autenticado = [AuthMiddleware::class];
    $router->get('/actividades/{id:\d+}', [ActividadController::class, 'ver'], $autenticado);
    $router->post('/actividades/{id:\d+}/estado', [ActividadController::class, 'cambiarEstado'], $autenticado);

    // Administrador
    $soloAdministrador = [AuthMiddleware::class, [RoleMiddleware::class, Usuario::ADMINISTRADOR]];
    $router->group($soloAdministrador, static function (Router $r): void {
        // Talleres (RF-06)
        $r->get('/actividades/nueva', [ActividadController::class, 'crear']);
        $r->post('/actividades', [ActividadController::class, 'guardar']);
        $r->get('/actividades/{id:\d+}/editar', [ActividadController::class, 'editar']);
        $r->post('/actividades/{id:\d+}', [ActividadController::class, 'actualizar']);
        $r->post('/actividades/{id:\d+}/desactivar', [ActividadController::class, 'desactivar']);

        // Asignaciones (RF-04, RF-05)
        $r->get('/asignaciones', [AsignacionController::class, 'index']);
        $r->post('/asignaciones', [AsignacionController::class, 'asignar']);
        $r->post('/asignaciones/{id:\d+}/quitar', [AsignacionController::class, 'quitar']);

        // Usuarios (RF-10)
        $r->get('/usuarios', [UsuarioController::class, 'index']);
        $r->post('/usuarios/{id:\d+}/rol', [UsuarioController::class, 'cambiarRol']);
        $r->post('/usuarios/{id:\d+}/estado', [UsuarioController::class, 'cambiarEstado']);
    });
};
