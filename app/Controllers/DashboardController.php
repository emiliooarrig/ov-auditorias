<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Panel central del administrador y "Mis talleres" del auditor (RF-03, RF-08, RF-11; CU-03).
 */
final class DashboardController extends Controller
{
    /**
     * Panel central con todos los talleres. El auditor no accede: va a "Mis talleres".
     */
    public function index(Request $request): Response
    {
        if (!$this->app->auth()->esAdministrador()) {
            return $this->redirect('/mis-talleres');
        }

        return $this->listado($request, '/', 'dashboard/index', 'Panel central', null);
    }

    /**
     * Talleres asignados al usuario en sesión. El id sale siempre de la sesión, nunca del cliente.
     */
    public function misTalleres(Request $request): Response
    {
        $usuario = $this->app->auth()->id();

        return $this->listado($request, '/mis-talleres', 'dashboard/mis-talleres', 'Mis talleres', $usuario);
    }

    private function listado(
        Request $request,
        string $ruta,
        string $vista,
        string $titulo,
        ?int $usuarioSesion,
    ): Response {
        return $this->view($vista, [
            'titulo' => $titulo,
            'ruta' => $ruta,
        ] + $this->listadoTalleres($request, $usuarioSesion));
    }
}
