<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Alumno;
use App\Models\Asignacion;
use App\Models\HorarioAlumno;
use DomainException;

/**
 * Alumnos auditores: lista con panel lateral. Sin alumno elegido el panel sirve para darlos de alta
 * (uno por uno o pegando una lista, siempre con su horario); con uno elegido muestra sus datos y los
 * talleres que audita.
 *
 * @phpstan-import-type AlumnoFila from Alumno
 */
final class AlumnoController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->pagina($request, null);
    }

    public function ver(Request $request): Response
    {
        $alumno = (new Alumno($this->db()))->buscarPorId($request->intParam('id'));
        if ($alumno === null) {
            $this->notFound();
        }

        return $this->pagina($request, $alumno);
    }

    public function guardar(Request $request): Response
    {
        $volver = $this->filtrosAlumnos($request, true);
        [$datos, $errores, $horarioId] = $this->validar($request);
        if ($errores === []) {
            try {
                (new Alumno($this->db()))->crear($datos['nombre'], $datos['apellidos'], $horarioId, $this->adminId());
            } catch (DomainException $e) {
                $errores = ['nombre' => $e->getMessage()];
            }
        }
        if ($errores !== []) {
            $this->session()->flashInput($datos + ['horario_id' => $request->inputString('horario_id')]);
            $this->session()->flashErrors($errores);

            return $this->redirect('/alumnos', $volver);
        }

        $this->session()->flash('exito', 'Se agregó a ' . $datos['nombre'] . ' ' . $datos['apellidos'] . '.', true);

        return $this->redirect('/alumnos', $volver);
    }

    /**
     * Alta de varios alumnos pegando una lista. Los renglones válidos se guardan; los que no se
     * pudieron leer regresan al cuadro de texto, con su error, para corregirlos.
     */
    public function importar(Request $request): Response
    {
        $volver = $this->filtrosAlumnos($request, true);
        $texto = $request->inputString('lista');
        $orden = $request->inputString('orden') === Alumno::NOMBRE_PRIMERO
            ? Alumno::NOMBRE_PRIMERO
            : Alumno::APELLIDOS_PRIMERO;
        [$horarioId, $errorHorario] = Alumno::validarHorario(
            $request->inputString('horario_id'),
            new HorarioAlumno($this->db())
        );

        $renglones = count(array_filter(
            preg_split('/\R/u', $texto) ?: [],
            static fn (string $r): bool => trim($r) !== ''
        ));
        $error = match (true) {
            $renglones === 0 => 'Pega al menos un alumno, uno por renglón.',
            $renglones > Alumno::LISTA_MAX => 'La lista tiene ' . $renglones . ' renglones; pega como máximo '
                . Alumno::LISTA_MAX . ' a la vez.',
            default => null,
        };
        if ($error !== null || $errorHorario !== null) {
            $this->session()->flashInput([
                'lista' => $texto,
                'orden' => $orden,
                'lista_horario_id' => $request->inputString('horario_id'),
            ]);
            $this->session()->flashErrors(array_filter(['lista' => $error, 'lista_horario_id' => $errorHorario]));

            return $this->redirect('/alumnos', $volver);
        }

        $lectura = Alumno::interpretarLista($texto, $orden);
        $resultado = $lectura['alumnos'] === []
            ? ['creados' => 0, 'repetidos' => []]
            : (new Alumno($this->db()))->crearVarios($lectura['alumnos'], $horarioId, $this->adminId());

        $partes = [match ($resultado['creados']) {
            0 => 'No se agregó ningún alumno.',
            1 => 'Se agregó 1 alumno.',
            default => 'Se agregaron ' . $resultado['creados'] . ' alumnos.',
        }];
        if ($resultado['repetidos'] !== []) {
            $partes[] = (count($resultado['repetidos']) === 1 ? 'Ya estaba registrado: ' : 'Ya estaban registrados: ')
                . implode(', ', $resultado['repetidos']) . '.';
        }
        if ($lectura['errores'] !== []) {
            $n = count($lectura['errores']);
            $partes[] = ($n === 1 ? '1 renglón no se pudo leer' : "{$n} renglones no se pudieron leer")
                . '; corrígelos en el cuadro de la lista.';
            $this->session()->flashInput([
                'lista' => implode("\n", array_column($lectura['errores'], 'texto')),
                'orden' => $orden,
                'lista_horario_id' => (string) $horarioId,
            ]);
            $this->session()->flashErrors([
                'lista' => $n === 1 ? 'Corrige este renglón:' : 'Corrige estos renglones:',
                'lista_renglones' => implode("\n", array_map(
                    static fn (array $e): string => '«' . $e['texto'] . '»: ' . $e['error'],
                    $lectura['errores']
                )),
            ]);
        }
        $this->session()->flash($resultado['creados'] > 0 ? 'exito' : 'aviso', implode(' ', $partes), true);

        return $this->redirect('/alumnos', $volver);
    }

    public function actualizar(Request $request): Response
    {
        $id = $this->buscar($request)['id'];
        $volver = $this->filtrosAlumnos($request, true);
        [$datos, $errores, $horarioId] = $this->validar($request);
        if ($errores === []) {
            try {
                (new Alumno($this->db()))->actualizar($id, $datos['nombre'], $datos['apellidos'], $horarioId);
            } catch (DomainException $e) {
                $campo = $e->getCode() === Alumno::ERROR_HORARIO ? 'horario_id' : 'nombre';
                $errores = [$campo => $e->getMessage()];
            }
        }
        if ($errores !== []) {
            $this->session()->flashInput($datos + ['horario_id' => $request->inputString('horario_id')]);
            $this->session()->flashErrors($errores);

            return $this->redirect('/alumnos/' . $id, $volver);
        }

        $this->session()->flash('exito', 'Datos actualizados.', true);

        return $this->redirect('/alumnos/' . $id, $volver);
    }

    /**
     * Dar de baja o reactivar. La baja le quita los talleres que siguen programados.
     */
    public function cambiarEstado(Request $request): Response
    {
        $alumno = $this->buscar($request);
        $volver = $this->filtrosAlumnos($request, true);
        $activo = $request->inputString('activo') === '1';
        $nombre = $alumno['nombre'] . ' ' . $alumno['apellidos'];

        try {
            $quitados = (new Alumno($this->db()))->cambiarActivo($alumno['id'], $activo);
        } catch (DomainException $e) {
            $this->session()->flash('error', $e->getMessage(), true);

            return $this->redirect('/alumnos/' . $alumno['id'], $volver);
        }

        $this->app->logger()->info($activo ? 'Alumno reactivado' : 'Alumno dado de baja', [
            'alumno_id' => $alumno['id'],
            'asignaciones_quitadas' => $quitados,
            'por' => $this->adminId(),
        ]);
        $mensaje = $activo
            ? 'Se reactivó a ' . $nombre . '. Ya se le pueden asignar talleres.'
            : 'Se dio de baja a ' . $nombre . '.' . match ($quitados) {
                0 => '',
                1 => ' Se le quitó 1 taller programado.',
                default => " Se le quitaron {$quitados} talleres programados.",
            };
        $this->session()->flash('exito', $mensaje, true);

        return $this->redirect('/alumnos/' . $alumno['id'], $volver);
    }

    /**
     * @param AlumnoFila|null $alumno
     */
    private function pagina(Request $request, ?array $alumno): Response
    {
        $datos = [
            'titulo' => $alumno === null ? 'Alumnos' : $alumno['nombre'] . ' ' . $alumno['apellidos'],
            'alumnos' => (new Alumno($this->db()))->todos(),
            'filtros' => $this->filtrosAlumnos($request, false),
            'seleccionado' => $alumno,
            'listaMax' => Alumno::LISTA_MAX,
            'horarios' => (new HorarioAlumno($this->db()))->todos(),
        ];
        if ($alumno !== null) {
            $asignaciones = new Asignacion($this->db());
            $datos['talleres'] = $asignaciones->talleresDe($alumno['id']);
            $datos['disponibles'] = (int) $alumno['activo'] === 1
                ? $asignaciones->talleresDisponiblesPara($alumno['id'])
                : [];
        }

        return $this->view('alumnos/index', $datos);
    }

    /**
     * Nombre, apellidos y horario del formulario de alta o de edición.
     *
     * @return array{0: array{nombre: string, apellidos: string}, 1: array<string, string>, 2: int}
     */
    private function validar(Request $request): array
    {
        [$datos, $errores] = Alumno::validar($request->inputString('nombre'), $request->inputString('apellidos'));
        [$horarioId, $errorHorario] = Alumno::validarHorario(
            $request->inputString('horario_id'),
            new HorarioAlumno($this->db())
        );
        if ($errorHorario !== null) {
            $errores['horario_id'] = $errorHorario;
        }

        return [$datos, $errores, $horarioId];
    }

    /**
     * @return AlumnoFila
     */
    private function buscar(Request $request): array
    {
        $alumno = (new Alumno($this->db()))->buscarPorId($request->intParam('id'));
        if ($alumno === null) {
            $this->notFound();
        }

        return $alumno;
    }
}
