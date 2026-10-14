<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Asignacion;
use DomainException;

/**
 * Asignaciones taller–alumno. La pantalla /asignaciones muestra qué falta por cubrir y lanza la
 * asignación aleatoria; la asignación manual se hace desde el panel del alumno o el detalle del taller.
 */
final class AsignacionController extends Controller
{
    /**
     * Cobertura y asignación al azar. ?auditores= trae los auditores por taller del último sorteo para
     * que el formulario los conserve; sin él (o fuera de rango) se proponen Asignacion::AUDITORES_POR_TALLER.
     */
    public function index(Request $request): Response
    {
        $auditores = $this->entero($request->queryString('auditores'));

        return $this->view('asignaciones/index', [
            'titulo' => 'Asignaciones',
            'cobertura' => (new Asignacion($this->db()))->cobertura(),
            'auditoresPorTaller' => $auditores >= 1 && $auditores <= Asignacion::MAX_AUDITORES_POR_TALLER
                ? $auditores
                : Asignacion::AUDITORES_POR_TALLER,
            'maxAuditoresPorTaller' => Asignacion::MAX_AUDITORES_POR_TALLER,
        ]);
    }

    /**
     * Asignación aleatoria y balanceada: conserva lo asignado y completa lo que falta para que cada taller
     * tenga los auditores pedidos (campo auditores_por_taller).
     */
    public function sortear(Request $request): Response
    {
        $auditores = $this->entero($request->inputString('auditores_por_taller'));
        try {
            $resultado = (new Asignacion($this->db()))->sortear($this->adminId(), $auditores);
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage(), true);

            return $this->redirect('/asignaciones');
        }

        $this->app->logger()->info('Asignación aleatoria', [
            'auditores_por_taller' => $auditores,
            'creadas' => $resultado['creadas'],
            'talleres_sin_cubrir' => count($resultado['talleresSinCubrir']),
            'talleres_incompletos' => count($resultado['talleresIncompletos']),
            'por' => $this->adminId(),
        ]);
        $hayPendientes = $resultado['talleresSinCubrir'] !== [] || $resultado['talleresIncompletos'] !== []
            || $resultado['alumnosSinTaller'] !== [];
        $this->session()->flash($hayPendientes ? 'aviso' : 'exito', $this->resumenSorteo($resultado), true);

        return $this->redirect('/asignaciones', ['auditores' => $auditores]);
    }

    /**
     * Quita de una vez las asignaciones de talleres programados que no caben en el horario del alumno
     * (hechas antes de que existiera la regla, o que quedaron fuera al cambiar el grupo del taller).
     */
    public function quitarFueraDeHorario(Request $request): Response
    {
        $quitadas = (new Asignacion($this->db()))->quitarFueraDeHorario();
        $this->app->logger()->info('Asignaciones fuera de horario quitadas', [
            'quitadas' => $quitadas,
            'por' => $this->adminId(),
        ]);
        $this->session()->flash($quitadas > 0 ? 'exito' : 'aviso', match ($quitadas) {
            0 => 'No había asignaciones fuera de horario.',
            1 => 'Se quitó 1 asignación fuera de horario. Usa la asignación al azar para cubrir su lugar.',
            default => "Se quitaron {$quitadas} asignaciones fuera de horario. "
                . 'Usa la asignación al azar para cubrir su lugar.',
        }, true);

        return $this->redirect('/asignaciones');
    }

    /**
     * Quita todas las asignaciones de talleres programados (manuales y al azar) para volver a repartir.
     * Sin JavaScript se confirma con la casilla; con él, la marca el diálogo.
     */
    public function quitarTodas(Request $request): Response
    {
        if ($request->input('confirmar') !== '1') {
            $this->session()->flash(
                'error',
                'Marca la casilla de confirmación para quitar todas las asignaciones.',
                true
            );

            return $this->redirect('/asignaciones');
        }

        $quitadas = (new Asignacion($this->db()))->quitarProgramadas();
        $this->app->logger()->info('Asignaciones de talleres programados quitadas', [
            'quitadas' => $quitadas,
            'por' => $this->adminId(),
        ]);
        $this->session()->flash($quitadas > 0 ? 'exito' : 'aviso', match ($quitadas) {
            0 => 'No había asignaciones de talleres programados que quitar.',
            1 => 'Se quitó 1 asignación. Usa la asignación al azar para volver a repartir.',
            default => "Se quitaron {$quitadas} asignaciones. Usa la asignación al azar para volver a repartir.",
        }, true);

        return $this->redirect('/asignaciones');
    }

    /**
     * Asignación manual de un taller a un alumno. Regresa a la pantalla desde la que se pidió:
     * el panel del alumno (con los filtros de su lista) o el detalle del taller.
     */
    public function asignar(Request $request): Response
    {
        $alumnoId = $this->idOpcional($request->inputString('alumno_id')) ?? 0;
        $actividadId = $this->idOpcional($request->inputString('actividad_id')) ?? 0;

        try {
            $nombres = (new Asignacion($this->db()))->asignar($alumnoId, $actividadId, $this->adminId());
            $mensaje = 'Se asignó «' . $nombres['taller'] . '» a ' . $nombres['alumno'] . '.';
            $this->session()->flash('exito', $mensaje, true);
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage(), true);
        }

        return $this->regresar($request, $alumnoId, $actividadId);
    }

    /**
     * Quita una asignación mientras el taller sigue programado (regla 6).
     */
    public function quitar(Request $request): Response
    {
        $alumnoId = $this->idOpcional($request->inputString('alumno_id')) ?? 0;
        $actividadId = $this->idOpcional($request->inputString('actividad_id')) ?? 0;

        try {
            $asignacion = (new Asignacion($this->db()))->quitar($request->intParam('id'));
            ['alumno_id' => $alumnoId, 'actividad_id' => $actividadId] = $asignacion;
            $this->session()->flash('exito', 'Se quitó al alumno del taller.', true);
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage(), true);
        }

        return $this->regresar($request, $alumnoId, $actividadId);
    }

    private function regresar(Request $request, int $alumnoId, int $actividadId): Response
    {
        if ($request->inputString('volver') === 'taller' && $actividadId > 0) {
            return $this->redirect('/actividades/' . $actividadId);
        }
        if ($alumnoId > 0) {
            return $this->redirect('/alumnos/' . $alumnoId, $this->filtrosAlumnos($request, true));
        }

        return $this->redirect('/asignaciones');
    }

    /**
     * Número entero sin signo a partir de texto; 0 si no lo es (el modelo lo rechaza con su mensaje).
     */
    private function entero(string $valor): int
    {
        $valor = trim($valor);

        return ctype_digit($valor) && strlen($valor) <= 3 ? (int) $valor : 0;
    }

    /**
     * @param array{creadas: int, auditoresPorTaller: int, alumnosCubiertos: int, talleresCubiertos: int,
     *     talleresSinCubrir: list<string>, talleresIncompletos: list<string>, alumnosSinTaller: list<string>,
     *     alumnosSinHorario: list<string>, cargaMinima: int, cargaMaxima: int} $r
     */
    private function resumenSorteo(array $r): string
    {
        $auditores = cantidad($r['auditoresPorTaller'], 'auditor', 'auditores');
        if (
            $r['creadas'] === 0 && $r['talleresSinCubrir'] === [] && $r['talleresIncompletos'] === []
            && $r['alumnosSinTaller'] === [] && $r['alumnosSinHorario'] === []
        ) {
            return 'No hizo falta asignar nada: cada alumno tiene al menos un taller y cada taller programado '
                . 'tiene ' . $auditores . ' o más.';
        }

        $partes = [$r['creadas'] === 1
            ? 'Se creó 1 asignación al azar.'
            : 'Se crearon ' . $r['creadas'] . ' asignaciones al azar.'];
        if ($r['talleresCubiertos'] > 0) {
            $partes[] = $r['talleresCubiertos'] === 1
                ? '1 taller recibió su primer auditor.'
                : $r['talleresCubiertos'] . ' talleres recibieron su primer auditor.';
        }
        if ($r['alumnosCubiertos'] > 0) {
            $partes[] = $r['alumnosCubiertos'] === 1
                ? '1 alumno recibió su primer taller.'
                : $r['alumnosCubiertos'] . ' alumnos recibieron su primer taller.';
        }
        if ($r['creadas'] > 0) {
            $partes[] = $r['cargaMinima'] === $r['cargaMaxima']
                ? 'Ahora cada alumno audita ' . cantidad($r['cargaMaxima'], 'taller', 'talleres') . '.'
                : 'Ahora cada alumno audita entre ' . $r['cargaMinima'] . ' y ' . $r['cargaMaxima'] . ' talleres.';
        }
        if ($r['talleresSinCubrir'] !== []) {
            $partes[] = $this->nombres(
                $r['talleresSinCubrir'],
                'Quedó sin auditor ',
                'Quedaron sin auditor ',
                'Quedaron %d talleres sin auditor'
            ) . ': a esa hora no hay alumnos libres cuyo horario la cubra.';
        }
        if ($r['talleresIncompletos'] !== []) {
            $partes[] = $this->nombres(
                $r['talleresIncompletos'],
                'Quedó con menos de ' . $auditores . ' ',
                'Quedaron con menos de ' . $auditores . ' ',
                'Quedaron %d talleres con menos de ' . $auditores
            ) . ': a esa hora no hay más alumnos libres cuyo horario la cubra.';
        }
        if ($r['alumnosSinTaller'] !== []) {
            $partes[] = $this->nombres(
                $r['alumnosSinTaller'],
                'Quedó sin taller ',
                'Quedaron sin taller ',
                'Quedaron %d alumnos sin taller'
            ) . ': no hay talleres dentro de su horario a horas que tengan libres.';
        }
        if ($r['alumnosSinHorario'] !== []) {
            $partes[] = $this->nombres(
                $r['alumnosSinHorario'],
                'No participó por no tener horario registrado ',
                'No participaron por no tener horario registrado ',
                'No participaron %d alumnos por no tener horario registrado'
            ) . '.';
        }

        return implode(' ', $partes);
    }

    /**
     * "Quedaron sin auditor A, B, C", o solo cuántos si son más de cinco (la lista completa está en la
     * página de asignaciones). $muchos lleva %d en el lugar de la cantidad.
     *
     * @param list<string> $nombres
     */
    private function nombres(array $nombres, string $uno, string $varios, string $muchos): string
    {
        $n = count($nombres);

        return match (true) {
            $n === 1 => $uno . $nombres[0],
            $n <= 5 => $varios . implode(', ', $nombres),
            default => sprintf($muchos, $n),
        };
    }
}
