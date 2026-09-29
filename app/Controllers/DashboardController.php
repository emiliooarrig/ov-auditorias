<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use Throwable;

final class DashboardController extends Controller
{
    /**
     * Fase 1: página de verificación de la base técnica. En la fase 3 se convierte
     * en el panel central con filtros (RF-03, RF-08).
     */
    public function index(Request $request): Response
    {
        try {
            $version = (string) $this->db()->fetchValue('SELECT VERSION()');
            $bd = ['ok' => true, 'detalle' => $version];
        } catch (Throwable $e) {
            $bd = ['ok' => false, 'detalle' => $this->app->debug() ? $e->getMessage() : 'Sin conexión'];
        }

        return $this->view('dashboard/index', [
            'titulo' => 'Panel central',
            'bd' => $bd,
            'php' => PHP_VERSION,
        ]);
    }
}
