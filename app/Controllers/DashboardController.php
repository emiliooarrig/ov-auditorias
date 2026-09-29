<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    /**
     * Panel central del administrador (RF-03). El auditor no accede: va a "Mis talleres".
     * El listado con filtros (RF-08) se construye en la fase 3.
     */
    public function index(Request $request): Response
    {
        if (!$this->app->auth()->esAdministrador()) {
            return $this->redirect('/mis-talleres');
        }

        return $this->view('dashboard/index', ['titulo' => 'Panel central']);
    }

    /**
     * "Mis talleres" del auditor en sesión (RF-11). El listado se construye en la fase 4.
     */
    public function misTalleres(Request $request): Response
    {
        return $this->view('dashboard/mis-talleres', ['titulo' => 'Mis talleres']);
    }
}
