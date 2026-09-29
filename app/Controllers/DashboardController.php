<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Actividad;
use App\Models\Carrera;
use App\Models\Edificio;

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
        $filtros = [
            'nombre' => mb_substr($request->queryString('nombre'), 0, 150),
            'carrera' => $this->idOpcional($request->queryString('carrera')),
            'edificio' => $this->idOpcional($request->queryString('edificio')),
        ];
        $pagina = $this->idOpcional($request->queryString('pagina')) ?? 1;

        $resultado = (new Actividad($this->db()))->filtrar(
            [
                'nombre' => $filtros['nombre'],
                'carrera_id' => $filtros['carrera'],
                'edificio_id' => $filtros['edificio'],
            ],
            $usuarioSesion,
            $pagina,
            (int) $this->app->config('pagination.per_page', 20)
        );

        return $this->view($vista, [
            'titulo' => $titulo,
            'ruta' => $ruta,
            'filtros' => $filtros,
            'hayFiltros' => $filtros['nombre'] !== ''
                || $filtros['carrera'] !== null
                || $filtros['edificio'] !== null,
            'resultado' => $resultado,
            'carreras' => (new Carrera($this->db()))->activas(),
            'edificios' => (new Edificio($this->db()))->activos(),
        ]);
    }

    private function idOpcional(string $valor): ?int
    {
        return ctype_digit($valor) && (int) $valor > 0 ? (int) $valor : null;
    }
}
