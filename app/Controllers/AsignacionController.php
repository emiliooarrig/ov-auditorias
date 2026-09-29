<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Asignacion;
use App\Models\Usuario;
use DomainException;

/**
 * Asignación manual de talleres a auditores (RF-04, RF-05; CU-01). Solo administrador.
 */
final class AsignacionController extends Controller
{
    /**
     * Paso 1: elegir un auditor. Paso 2: marcar talleres de la lista filtrable y confirmar.
     */
    public function index(Request $request): Response
    {
        $auditores = (new Usuario($this->db()))->auditoresActivos();
        $usuarioId = $this->idOpcional($request->queryString('usuario'));
        $seleccionado = null;
        foreach ($auditores as $auditor) {
            if ($auditor['id'] === $usuarioId) {
                $seleccionado = $auditor;
            }
        }

        $datos = ['titulo' => 'Asignaciones', 'auditores' => $auditores, 'seleccionado' => $seleccionado];
        if ($seleccionado !== null) {
            $asignados = (new Asignacion($this->db()))->talleresDe($seleccionado['id']);
            $datos += $this->listadoTalleres($request, null) + [
                'asignados' => $asignados,
                'idsAsignados' => array_column($asignados, 'actividad_id'),
            ];
        }

        return $this->view('asignaciones/index', $datos);
    }

    public function asignar(Request $request): Response
    {
        $usuarioId = $this->idOpcional($request->inputString('usuario_id'));
        $volver = $this->consultaDeRegreso($request) + ['usuario' => $usuarioId];

        $seleccion = $request->input('actividades');
        $actividadIds = is_array($seleccion)
            ? array_values(array_filter(array_map(
                fn (mixed $id): ?int => is_string($id) ? $this->idOpcional($id) : null,
                $seleccion
            )))
            : [];

        try {
            $resultado = (new Asignacion($this->db()))->asignar(
                (int) $usuarioId,
                $actividadIds,
                (int) $this->app->auth()->id()
            );
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage());

            return $this->redirect('/asignaciones', $volver);
        }

        $this->session()->flash('exito', $this->resumen($resultado));

        return $this->redirect('/asignaciones', $volver);
    }

    /**
     * Quita una asignación mientras el taller sigue programado (regla 6).
     * Regresa a la pantalla desde la que se pidió: detalle del taller o asignaciones del auditor.
     */
    public function quitar(Request $request): Response
    {
        try {
            $asignacion = (new Asignacion($this->db()))->quitar($request->intParam('id'));
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage());

            return $this->regresar($request, null);
        }

        $this->session()->flash('exito', 'Auditor quitado del taller.');

        return $this->regresar($request, $asignacion);
    }

    /**
     * @param array{actividad_id: int, usuario_id: int}|null $asignacion
     */
    private function regresar(Request $request, ?array $asignacion): Response
    {
        $actividadId = $asignacion['actividad_id'] ?? $this->idOpcional($request->inputString('actividad_id'));
        $usuarioId = $asignacion['usuario_id'] ?? $this->idOpcional($request->inputString('usuario_id'));

        if ($request->inputString('volver') === 'detalle' && $actividadId !== null) {
            return $this->redirect('/actividades/' . $actividadId);
        }

        return $this->redirect('/asignaciones', $this->consultaDeRegreso($request) + ['usuario' => $usuarioId]);
    }

    /**
     * Filtros y página que tenía la lista, para volver a ella tras enviar el formulario.
     *
     * @return array<string, string|int|null>
     */
    private function consultaDeRegreso(Request $request): array
    {
        return [
            'nombre' => mb_substr($request->inputString('nombre'), 0, 150),
            'carrera' => $this->idOpcional($request->inputString('carrera')),
            'edificio' => $this->idOpcional($request->inputString('edificio')),
            'pagina' => $this->idOpcional($request->inputString('pagina')),
        ];
    }

    /**
     * @param array{creadas: list<string>, existentes: int, invalidas: int} $resultado
     */
    private function resumen(array $resultado): string
    {
        $creadas = count($resultado['creadas']);
        $partes = [];
        $partes[] = $creadas === 0
            ? 'No se creó ninguna asignación nueva.'
            : ($creadas === 1 ? 'Se asignó 1 taller: ' : "Se asignaron {$creadas} talleres: ")
                . implode(', ', $resultado['creadas']) . '.';
        if ($resultado['existentes'] > 0) {
            $partes[] = $resultado['existentes'] === 1
                ? '1 ya estaba asignado.'
                : "{$resultado['existentes']} ya estaban asignados.";
        }
        if ($resultado['invalidas'] > 0) {
            $partes[] = $resultado['invalidas'] === 1
                ? '1 taller ya no está disponible.'
                : "{$resultado['invalidas']} talleres ya no están disponibles.";
        }

        return implode(' ', $partes);
    }
}
