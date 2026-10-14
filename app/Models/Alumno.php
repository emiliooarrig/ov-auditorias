<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

/**
 * Alumnos que auditan talleres. No tienen cuenta ni ingresan al sistema: el administrador los da de
 * alta con su nombre, apellidos y horario (Matutino, Vespertino o Todo el día), uno por uno o pegando
 * una lista. Solo auditan talleres que caben en su horario.
 *
 * @phpstan-type AlumnoFila array{
 *     id: int, nombre: string, apellidos: string, horario_id: ?int, activo: int, creado_por: ?int,
 *     creado_en: string, actualizado_en: string, registrado_por: ?string, horario: ?string,
 *     horario_inicio: ?string, horario_fin: ?string
 * }
 * @phpstan-type AlumnoListado array{
 *     id: int, nombre: string, apellidos: string, activo: int, horario_id: ?int, horario: ?string,
 *     talleres: int, programados: int
 * }
 */
final class Alumno
{
    public const NOMBRE_MAX = 80;
    public const APELLIDOS_MAX = 120;
    /** Renglones que se aceptan de una sola vez al pegar una lista. */
    public const LISTA_MAX = 500;

    /** Código de la excepción cuando el error es del horario (y no del nombre). */
    public const ERROR_HORARIO = 1;

    public const NOMBRE_PRIMERO = 'nombre';
    public const APELLIDOS_PRIMERO = 'apellidos';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return AlumnoFila|null
     */
    public function buscarPorId(int $id): ?array
    {
        /** @var AlumnoFila|null */
        return $this->db->fetchOne(
            "SELECT al.*, CONCAT(u.nombre, ' ', u.apellidos) AS registrado_por,
                    h.nombre AS horario, h.hora_inicio AS horario_inicio, h.hora_fin AS horario_fin
             FROM alumnos al
             LEFT JOIN usuarios u ON u.id = al.creado_por
             LEFT JOIN horarios_alumno h ON h.id = al.horario_id
             WHERE al.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Todos los alumnos con su horario, cuántos talleres activos tienen y cuántos siguen programados.
     * La búsqueda y los filtros se aplican en el navegador.
     *
     * @return list<AlumnoListado>
     */
    public function todos(): array
    {
        /** @var list<AlumnoListado> */
        return $this->db->fetchAll(
            "SELECT al.id, al.nombre, al.apellidos, al.activo, al.horario_id, h.nombre AS horario,
                    COUNT(a.id) AS talleres,
                    COUNT(CASE WHEN a.estado = 'programado' THEN 1 END) AS programados
             FROM alumnos al
             LEFT JOIN horarios_alumno h ON h.id = al.horario_id
             LEFT JOIN asignaciones s ON s.alumno_id = al.id
             LEFT JOIN actividades a ON a.id = s.actividad_id AND a.activo = 1
             GROUP BY al.id, al.nombre, al.apellidos, al.activo, al.horario_id, h.nombre
             ORDER BY al.apellidos, al.nombre, al.id"
        );
    }

    /**
     * Da de alta un alumno y devuelve su id.
     */
    public function crear(string $nombre, string $apellidos, int $horarioId, int $creadoPor): int
    {
        $this->exigirNombreLibre($this->db, $nombre, $apellidos, null);
        $this->db->execute(
            'INSERT INTO alumnos (nombre, apellidos, horario_id, creado_por)
             VALUES (:nombre, :apellidos, :horario_id, :creado_por)',
            ['nombre' => $nombre, 'apellidos' => $apellidos, 'horario_id' => $horarioId, 'creado_por' => $creadoPor]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Da de alta varios alumnos con el mismo horario en una transacción. Omite los que ya existen
     * (también si aparecen dos veces en la misma lista) y devuelve sus nombres.
     *
     * @param list<array{nombre: string, apellidos: string}> $alumnos
     * @return array{creados: int, repetidos: list<string>}
     */
    public function crearVarios(array $alumnos, int $horarioId, int $creadoPor): array
    {
        return $this->db->transaction(function (Database $db) use ($alumnos, $horarioId, $creadoPor): array {
            $creados = 0;
            $repetidos = [];
            foreach ($alumnos as $alumno) {
                if ($this->idConNombre($db, $alumno['nombre'], $alumno['apellidos'], null) !== null) {
                    $repetidos[] = $alumno['nombre'] . ' ' . $alumno['apellidos'];
                    continue;
                }
                $db->execute(
                    'INSERT INTO alumnos (nombre, apellidos, horario_id, creado_por)
                     VALUES (:nombre, :apellidos, :horario_id, :creado_por)',
                    $alumno + ['horario_id' => $horarioId, 'creado_por' => $creadoPor]
                );
                $creados++;
            }

            return ['creados' => $creados, 'repetidos' => $repetidos];
        });
    }

    /**
     * Corrige nombre, apellidos y horario. Un cambio de horario no puede dejar fuera de él a talleres
     * programados que ya audita: hay que quitárselos antes.
     */
    public function actualizar(int $id, string $nombre, string $apellidos, int $horarioId): void
    {
        $this->db->transaction(function (Database $db) use ($id, $nombre, $apellidos, $horarioId): void {
            $actual = $this->bloquear($db, $id);
            $this->exigirNombreLibre($db, $nombre, $apellidos, $id);
            if ($actual['horario_id'] !== $horarioId) {
                $this->exigirTalleresEnHorario($db, $id, $horarioId);
            }
            $db->execute(
                'UPDATE alumnos SET nombre = :nombre, apellidos = :apellidos, horario_id = :horario_id WHERE id = :id',
                ['nombre' => $nombre, 'apellidos' => $apellidos, 'horario_id' => $horarioId, 'id' => $id]
            );
        });
    }

    /**
     * Da de baja o reactiva a un alumno (regla 4: no se borran). Al darlo de baja se le quitan los
     * talleres que siguen programados, porque ya no los auditará; los realizados y no realizados
     * se conservan como registro.
     *
     * @return int Asignaciones quitadas.
     */
    public function cambiarActivo(int $id, bool $activo): int
    {
        return $this->db->transaction(function (Database $db) use ($id, $activo): int {
            $actual = $this->bloquear($db, $id);
            if ((int) $actual['activo'] === (int) $activo) {
                throw new DomainException($activo ? 'El alumno ya está activo.' : 'El alumno ya está dado de baja.');
            }
            $db->execute('UPDATE alumnos SET activo = :activo WHERE id = :id', ['activo' => $activo, 'id' => $id]);
            if ($activo) {
                return 0;
            }

            return $db->execute(
                "DELETE s FROM asignaciones s
                 JOIN actividades a ON a.id = s.actividad_id
                 WHERE s.alumno_id = :id AND a.activo = 1 AND a.estado = 'programado'",
                ['id' => $id]
            );
        });
    }

    /**
     * Valida nombre y apellidos de un formulario. Devuelve los valores normalizados y los errores por campo.
     *
     * @return array{0: array{nombre: string, apellidos: string}, 1: array<string, string>}
     */
    public static function validar(string $nombre, string $apellidos): array
    {
        $datos = [
            'nombre' => Usuario::normalizarNombre($nombre),
            'apellidos' => Usuario::normalizarNombre($apellidos),
        ];
        $errores = array_filter([
            'nombre' => Usuario::validarNombre($datos['nombre'], self::NOMBRE_MAX),
            'apellidos' => Usuario::validarNombre($datos['apellidos'], self::APELLIDOS_MAX),
        ]);

        return [$datos, $errores];
    }

    /**
     * Interpreta una lista pegada: un alumno por renglón, con las columnas separadas por tabuladores
     * (copiadas de Excel) o por comas. Con $orden 'apellidos' la última columna es el nombre y las
     * anteriores los apellidos ("López, Ruiz, Ana" o "López Ruiz, Ana"); con 'nombre', la primera
     * columna es el nombre y las demás los apellidos. Se ignoran los renglones vacíos y un encabezado
     * "Nombre / Apellidos".
     *
     * @return array{alumnos: list<array{nombre: string, apellidos: string}>,
     *     errores: list<array{renglon: int, texto: string, error: string}>}
     */
    public static function interpretarLista(string $texto, string $orden): array
    {
        $renglones = preg_split('/\R/u', $texto) ?: [];
        $alumnos = [];
        $errores = [];

        foreach ($renglones as $i => $renglon) {
            $renglon = trim($renglon);
            if ($renglon === '') {
                continue;
            }
            // Tabuladores (columnas de Excel) y comas separan igual: un nombre no puede llevar comas.
            if (preg_match('/[\t,]/', $renglon) !== 1) {
                $errores[] = [
                    'renglon' => $i + 1,
                    'texto' => $renglon,
                    'error' => 'Separa el nombre de los apellidos con una coma o en dos columnas.',
                ];
                continue;
            }
            $columnas = array_values(array_filter(
                array_map([Usuario::class, 'normalizarNombre'], preg_split('/[\t,]/', $renglon) ?: []),
                static fn (string $c): bool => $c !== ''
            ));
            if (count($columnas) < 2) {
                $errores[] = ['renglon' => $i + 1, 'texto' => $renglon, 'error' => 'Falta el nombre o los apellidos.'];
                continue;
            }

            if ($orden === self::NOMBRE_PRIMERO) {
                $nombre = (string) array_shift($columnas);
            } else {
                $nombre = (string) array_pop($columnas);
            }
            $apellidos = implode(' ', $columnas);

            if ($alumnos === [] && $errores === [] && self::esEncabezado($nombre, $apellidos)) {
                continue;
            }
            [$datos, $erroresCampo] = self::validar($nombre, $apellidos);
            if ($erroresCampo !== []) {
                $etiquetas = ['nombre' => 'Nombre', 'apellidos' => 'Apellidos'];
                $mensaje = implode(' ', array_map(
                    static fn (string $campo, string $error): string => $etiquetas[$campo] . ': ' . $error,
                    array_keys($erroresCampo),
                    $erroresCampo
                ));
                $errores[] = ['renglon' => $i + 1, 'texto' => $renglon, 'error' => $mensaje];
                continue;
            }
            $alumnos[] = $datos;
        }

        return ['alumnos' => $alumnos, 'errores' => $errores];
    }

    private static function esEncabezado(string $nombre, string $apellidos): bool
    {
        $nombre = mb_strtolower($nombre);
        $apellidos = mb_strtolower($apellidos);

        return in_array($nombre, ['nombre', 'nombres', 'nombre(s)'], true) && str_starts_with($apellidos, 'apellido')
            || in_array($apellidos, ['nombre', 'nombres', 'nombre(s)'], true) && str_starts_with($nombre, 'apellido');
    }

    /**
     * Horario elegido en un formulario: devuelve su id o el mensaje de error.
     *
     * @return array{0: int, 1: ?string}
     */
    public static function validarHorario(string $valor, HorarioAlumno $horarios): array
    {
        $id = ctype_digit($valor) ? (int) $valor : 0;

        return $id > 0 && $horarios->existe($id)
            ? [$id, null]
            : [0, 'Elige el horario en el que puede auditar.'];
    }

    /**
     * @return array{activo: int, horario_id: ?int}
     */
    private function bloquear(Database $db, int $id): array
    {
        $fila = $db->fetchOne('SELECT activo, horario_id FROM alumnos WHERE id = :id FOR UPDATE', ['id' => $id]);
        if ($fila === null) {
            throw new DomainException('El alumno no existe.');
        }

        /** @var array{activo: int, horario_id: ?int} $fila */
        return $fila;
    }

    /**
     * Talleres programados del alumno que no cabrían en el horario nuevo.
     */
    private function exigirTalleresEnHorario(Database $db, int $id, int $horarioId): void
    {
        /** @var list<string> $fuera */
        $fuera = array_column($db->fetchAll(
            "SELECT a.nombre FROM asignaciones s
             JOIN actividades a ON a.id = s.actividad_id
             JOIN horarios_alumno h ON h.id = :horario
             WHERE s.alumno_id = :id AND a.activo = 1 AND a.estado = 'programado'
               AND NOT (h.hora_inicio <= a.hora_inicio AND a.hora_fin <= h.hora_fin)
             ORDER BY a.fecha, a.hora_inicio, a.nombre",
            ['horario' => $horarioId, 'id' => $id]
        ), 'nombre');
        if ($fuera !== []) {
            throw new DomainException(
                (count($fuera) === 1
                    ? 'Con ese horario quedaría fuera el taller '
                    : 'Con ese horario quedarían fuera los talleres ')
                . implode(', ', $fuera) . '. Quítaselos antes de cambiar su horario.',
                self::ERROR_HORARIO
            );
        }
    }

    /**
     * Dos alumnos no pueden llamarse igual: sin correo ni matrícula, el nombre es lo único que los distingue.
     * La comparación ignora mayúsculas y acentos (intercalación utf8mb4_unicode_ci).
     */
    private function exigirNombreLibre(Database $db, string $nombre, string $apellidos, ?int $exceptoId): void
    {
        $existente = $this->idConNombre($db, $nombre, $apellidos, $exceptoId);
        if ($existente === null) {
            return;
        }
        $activo = (int) $db->fetchValue('SELECT activo FROM alumnos WHERE id = ?', [$existente]) === 1;

        throw new DomainException($activo
            ? 'Ya hay un alumno con ese nombre. Si es otra persona, agrega su segundo nombre o apellido '
                . 'para distinguirlos.'
            : 'Ya hay un alumno dado de baja con ese nombre. Búscalo en la lista y reactívalo.');
    }

    private function idConNombre(Database $db, string $nombre, string $apellidos, ?int $exceptoId): ?int
    {
        $id = $db->fetchValue(
            'SELECT id FROM alumnos WHERE nombre = :nombre AND apellidos = :apellidos AND id <> :excepto LIMIT 1',
            ['nombre' => $nombre, 'apellidos' => $apellidos, 'excepto' => $exceptoId ?? 0]
        );

        return $id === null ? null : (int) $id;
    }
}
