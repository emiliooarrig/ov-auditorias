<?php

declare(strict_types=1);

use App\Controllers\ActividadController;
use App\Controllers\AdministradorController;
use App\Controllers\AlumnoController;
use App\Controllers\AsignacionController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ReporteController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

/*
 * Rutas de la aplicación. Solo los administradores ingresan: todo, salvo el ingreso, exige su sesión.
 * Los ids numéricos usan {id:\d+} para no chocar con rutas fijas como /actividades/nueva.
 */
return static function (Router $router): void {
    // Todo POST exige token CSRF.
    $router->middleware([CsrfMiddleware::class]);

    // Ingreso (RF-01)
    $router->group([GuestMiddleware::class], static function (Router $r): void {
        $r->get('/login', [AuthController::class, 'mostrarLogin']);
        $r->post('/login', [AuthController::class, 'login']);
    });
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

    $router->group([AuthMiddleware::class], static function (Router $r): void {
        // Panel central (RF-03, RF-08)
        $r->get('/', [DashboardController::class, 'index']);

        // Talleres (RF-06, RF-07, RF-09)
        $r->get('/actividades/nueva', [ActividadController::class, 'crear']);
        $r->post('/actividades', [ActividadController::class, 'guardar']);
        $r->get('/actividades/{id:\d+}', [ActividadController::class, 'ver']);
        $r->get('/actividades/{id:\d+}/editar', [ActividadController::class, 'editar']);
        $r->post('/actividades/{id:\d+}', [ActividadController::class, 'actualizar']);
        $r->post('/actividades/{id:\d+}/estado', [ActividadController::class, 'cambiarEstado']);
        $r->post('/actividades/{id:\d+}/desactivar', [ActividadController::class, 'desactivar']);

        // Reportes en PDF: todos los talleres con sus auditores, y el directorio de alumnos por apellido
        $r->get('/reportes/talleres.pdf', [ReporteController::class, 'talleres']);
        $r->get('/reportes/alumnos.pdf', [ReporteController::class, 'alumnos']);

        // Alumnos auditores: lista con panel, alta uno por uno o por lista, edición y baja
        $r->get('/alumnos', [AlumnoController::class, 'index']);
        $r->post('/alumnos', [AlumnoController::class, 'guardar']);
        $r->post('/alumnos/lista', [AlumnoController::class, 'importar']);
        $r->get('/alumnos/{id:\d+}', [AlumnoController::class, 'ver']);
        $r->post('/alumnos/{id:\d+}', [AlumnoController::class, 'actualizar']);
        $r->post('/alumnos/{id:\d+}/estado', [AlumnoController::class, 'cambiarEstado']);

        // Asignaciones: cobertura y asignación aleatoria; manuales desde el alumno o el taller (RF-04, RF-05)
        $r->get('/asignaciones', [AsignacionController::class, 'index']);
        $r->post('/asignaciones/aleatoria', [AsignacionController::class, 'sortear']);
        $r->post('/asignaciones/fuera-de-horario/quitar', [AsignacionController::class, 'quitarFueraDeHorario']);
        $r->post('/asignaciones/quitar-todas', [AsignacionController::class, 'quitarTodas']);
        $r->post('/asignaciones', [AsignacionController::class, 'asignar']);
        $r->post('/asignaciones/{id:\d+}/quitar', [AsignacionController::class, 'quitar']);

        // Administradores (RF-10)
        $r->get('/administradores', [AdministradorController::class, 'index']);
        $r->post('/administradores', [AdministradorController::class, 'guardar']);
        $r->post('/administradores/{id:\d+}/estado', [AdministradorController::class, 'cambiarEstado']);
        $r->post('/administradores/{id:\d+}/password', [AdministradorController::class, 'cambiarPassword']);
    });
};
