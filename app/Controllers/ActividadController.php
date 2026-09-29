<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;
use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Bitacora;
use App\Models\Carrera;
use App\Models\Edificio;
use DomainException;

/**
 * Talleres: detalle, cambio de estado, alta, edición y desactivación (RF-06, RF-07, RF-09).
 *
 * El detalle y el cambio de estado están abiertos al auditor, pero solo para talleres que tiene
 * asignados; para cualquier otro responden 404, como si no existieran.
 *
 * @phpstan-import-type TallerFila from Actividad
 * @phpstan-import-type DatosTaller from Actividad
 */
final class ActividadController extends Controller
{
    public function ver(Request $request): Response
    {
        $actividad = $this->buscarAccesible($request->intParam('id'));
        $esAdministrador = $this->app->auth()->esAdministrador();

        return $this->view('actividades/ver', [
            'titulo' => $actividad['nombre'],
            'actividad' => $actividad,
            'auditores' => (new Asignacion($this->db()))->auditoresDe($actividad['id']),
            'historial' => (new Bitacora($this->db()))->historial($actividad['id']),
            'esAdministrador' => $esAdministrador,
            'puedeMarcarNoRealizado' => $esAdministrador || $actividad['estado'] === Actividad::PROGRAMADO,
        ]);
    }

    /**
     * CU-02. El administrador puede pasar a cualquier estado; el auditor asignado solo puede
     * marcar como no realizado un taller que sigue programado (regla 8).
     */
    public function cambiarEstado(Request $request): Response
    {
        $actividad = $this->buscarAccesible($request->intParam('id'));
        $nuevo = $request->inputString('estado');
        $motivo = $request->inputString('motivo');
        $esAdministrador = $this->app->auth()->esAdministrador();

        if (!$esAdministrador && $nuevo !== Actividad::NO_REALIZADO) {
            throw new HttpException(403, 'Solo el administrador puede marcar un taller como realizado o programado.');
        }

        try {
            (new Actividad($this->db()))->cambiarEstado(
                $actividad['id'],
                $nuevo,
                $motivo === '' ? null : $motivo,
                (int) $this->app->auth()->id(),
                $esAdministrador ? null : Actividad::PROGRAMADO
            );
        } catch (DomainException $e) {
            $this->session()->flashInput($request->allInput());
            $this->session()->flashErrors(['motivo' => $e->getMessage()]);

            return $this->redirect('/actividades/' . $actividad['id']);
        }

        $this->session()->flash('exito', match ($nuevo) {
            Actividad::NO_REALIZADO => 'El taller quedó registrado como no realizado.',
            Actividad::REALIZADO => 'El taller quedó marcado como realizado.',
            default => 'El taller volvió a estar programado.',
        });

        return $this->redirect('/actividades/' . $actividad['id']);
    }

    public function crear(Request $request): Response
    {
        return $this->formulario(null);
    }

    public function guardar(Request $request): Response
    {
        [$datos, $errores] = $this->validar($request);
        if ($errores !== []) {
            return $this->volverConErrores('/actividades/nueva', $errores, $request);
        }

        $id = (new Actividad($this->db()))->crear($datos, (int) $this->app->auth()->id());
        $this->session()->flash('exito', 'Taller creado.');

        return $this->redirect('/actividades/' . $id);
    }

    public function editar(Request $request): Response
    {
        return $this->formulario($this->buscarActiva($request->intParam('id')));
    }

    public function actualizar(Request $request): Response
    {
        $actividad = $this->buscarActiva($request->intParam('id'));

        [$datos, $errores] = $this->validar($request);
        if ($errores !== []) {
            return $this->volverConErrores('/actividades/' . $actividad['id'] . '/editar', $errores, $request);
        }

        (new Actividad($this->db()))->actualizar($actividad['id'], $datos);
        $this->session()->flash('exito', 'Cambios guardados.');

        return $this->redirect('/actividades/' . $actividad['id']);
    }

    public function desactivar(Request $request): Response
    {
        $actividad = $this->buscarActiva($request->intParam('id'));

        if ($request->input('confirmar') !== '1') {
            $this->session()->flash('error', 'Marca la casilla de confirmación para desactivar el taller.');

            return $this->redirect('/actividades/' . $actividad['id']);
        }

        (new Actividad($this->db()))->desactivar($actividad['id']);
        $this->session()->flash('exito', 'Taller "' . $actividad['nombre'] . '" desactivado. Su registro se conserva.');

        return $this->redirect('/');
    }

    /**
     * @param TallerFila|null $actividad
     */
    private function formulario(?array $actividad): Response
    {
        return $this->view('actividades/formulario', [
            'titulo' => $actividad === null ? 'Nuevo taller' : 'Editar taller',
            'actividad' => $actividad,
            'carreras' => (new Carrera($this->db()))->activas(),
            'edificios' => (new Edificio($this->db()))->activos(),
        ]);
    }

    /**
     * @return array{0: DatosTaller, 1: array<string, string>}
     */
    private function validar(Request $request): array
    {
        return Actividad::validar($request->allInput(), new Carrera($this->db()), new Edificio($this->db()));
    }

    /**
     * Los talleres desactivados no se muestran ni se editan: responden 404.
     *
     * @return TallerFila
     */
    private function buscarActiva(int $id): array
    {
        $actividad = (new Actividad($this->db()))->buscarPorId($id);
        if ($actividad === null || (int) $actividad['activo'] !== 1) {
            $this->notFound();
        }

        return $actividad;
    }

    /**
     * El administrador accede a cualquier taller activo; el auditor solo a los que tiene asignados.
     * El id del auditor sale de la sesión, nunca de la petición.
     *
     * @return TallerFila
     */
    private function buscarAccesible(int $id): array
    {
        $actividad = $this->buscarActiva($id);
        $auth = $this->app->auth();
        if (!$auth->esAdministrador() && !(new Asignacion($this->db()))->estaAsignado($id, (int) $auth->id())) {
            $this->notFound();
        }

        return $actividad;
    }

    /**
     * @param array<string, string> $errores
     */
    private function volverConErrores(string $ruta, array $errores, Request $request): Response
    {
        $this->session()->flashInput($request->allInput());
        $this->session()->flashErrors($errores);

        return $this->redirect($ruta);
    }
}
