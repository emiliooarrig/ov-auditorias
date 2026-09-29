<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Actividad;
use App\Models\Bitacora;
use App\Models\Carrera;
use App\Models\Edificio;

/**
 * Catálogo de talleres: detalle, alta, edición y desactivación (RF-06, RF-09).
 *
 * @phpstan-import-type TallerFila from Actividad
 * @phpstan-import-type DatosTaller from Actividad
 */
final class ActividadController extends Controller
{
    public function ver(Request $request): Response
    {
        $actividad = $this->buscarActiva($request->intParam('id'));

        return $this->view('actividades/ver', [
            'titulo' => $actividad['nombre'],
            'actividad' => $actividad,
            'historial' => (new Bitacora($this->db()))->historial($actividad['id']),
        ]);
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
     * @param array<string, string> $errores
     */
    private function volverConErrores(string $ruta, array $errores, Request $request): Response
    {
        $this->session()->flashInput($request->allInput());
        $this->session()->flashErrors($errores);

        return $this->redirect($ruta);
    }
}
