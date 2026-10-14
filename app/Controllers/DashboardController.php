<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Asignacion;

/**
 * Panel central con todos los talleres (RF-03, RF-08; CU-03).
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('dashboard/index', [
            'titulo' => 'Panel central',
            'ruta' => '/',
            'sinAuditor' => (new Asignacion($this->db()))->talleresSinAuditor(),
        ] + $this->listadoTalleres($request));
    }
}
