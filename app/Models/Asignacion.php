<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;
use Random\Randomizer;

/**
 * Asignaciones taller–alumno: manuales (desde el panel del alumno o el detalle del taller) y
 * aleatorias (Sorteo). Reglas: la pareja es única; un alumno no puede tener dos talleres a la misma
 * hora; el taller debe caber en el horario del alumno (Matutino, Vespertino o Todo el día), y un alumno
 * sin horario registrado no recibe talleres; solo se asigna o se quita mientras el taller está programado.
 *
 * @phpstan-type FueraDeHorario array{id: int, alumno_id: int, alumno_nombre: string, alumno_apellidos: string,
 *     horario: string, horario_inicio: string, horario_fin: string, actividad_id: int, nombre: string,
 *     fecha: string, hora_inicio: string, hora_fin: string, grupo_nombre: string}
 */
final class Asignacion
{
    public const MANUAL = 'manual';
    public const ALEATORIA = 'aleatoria';

    /** Auditores por taller que propone la asignación al azar, y el máximo que acepta. */
    public const AUDITORES_POR_TALLER = 2;
    public const MAX_AUDITORES_POR_TALLER = 10;

    /**
     * Condición SQL: el taller (a) cabe en el horario (h) del alumno.
     */
    private const EN_HORARIO = 'h.hora_inicio <= a.hora_inicio AND a.hora_fin <= h.hora_fin';

    /**
     * Condición SQL: asignación (alumno al con horario h, taller a) programada y fuera de ese horario.
     */
    private const FUERA_DE_HORARIO = "a.activo = 1 AND a.estado = 'programado' AND al.activo = 1
        AND NOT (" . self::EN_HORARIO . ')';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Asigna un taller a un alumno a mano.
     *
     * @return array{alumno: string, taller: string} Nombres, para el mensaje de confirmación.
     */
    public function asignar(int $alumnoId, int $actividadId, int $asignadoPor): array
    {
        return $this->db->transaction(function (Database $db) use ($alumnoId, $actividadId, $asignadoPor): array {
            // Bloquea al alumno para que no lo den de baja ni le asignen otro taller a la misma hora a la vez.
            $alumno = $db->fetchOne(
                "SELECT activo, CONCAT(nombre, ' ', apellidos) AS nombre, horario_id
                 FROM alumnos WHERE id = :id FOR UPDATE",
                ['id' => $alumnoId]
            );
            if ($alumno === null) {
                throw new DomainException('Elige un alumno de la lista.');
            }
            if ((int) $alumno['activo'] !== 1) {
                throw new DomainException('No se pueden asignar talleres a un alumno dado de baja.');
            }

            $taller = $db->fetchOne(
                'SELECT id, nombre, fecha, grupo_id, hora_inicio, hora_fin, estado
                 FROM actividades WHERE id = :id AND activo = 1',
                ['id' => $actividadId]
            );
            if ($taller === null) {
                throw new DomainException('Elige un taller de la lista.');
            }
            if ($taller['estado'] !== Actividad::PROGRAMADO) {
                throw new DomainException(
                    'Ese taller ya no está programado: sus auditores quedan fijos como registro.'
                );
            }
            $this->exigirHorario($db, $alumno, $taller);

            $mismaHora = $db->fetchOne(
                'SELECT a.id, a.nombre FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                 WHERE s.alumno_id = :alumno AND a.activo = 1 AND a.fecha = :fecha AND a.grupo_id = :grupo',
                ['alumno' => $alumnoId, 'fecha' => $taller['fecha'], 'grupo' => $taller['grupo_id']]
            );
            if ($mismaHora !== null) {
                throw new DomainException((int) $mismaHora['id'] === $actividadId
                    ? $alumno['nombre'] . ' ya audita ese taller.'
                    : $alumno['nombre'] . ' ya tiene otro taller a esa hora: ' . $mismaHora['nombre'] . '.');
            }

            $this->insertar($db, $alumnoId, $actividadId, $asignadoPor, self::MANUAL);

            return ['alumno' => (string) $alumno['nombre'], 'taller' => (string) $taller['nombre']];
        });
    }

    /**
     * Quita una asignación solo si el taller sigue programado (regla 6).
     *
     * @return array{actividad_id: int, alumno_id: int}
     */
    public function quitar(int $asignacionId): array
    {
        return $this->db->transaction(function (Database $db) use ($asignacionId): array {
            $fila = $db->fetchOne(
                'SELECT s.actividad_id, s.alumno_id, a.estado
                 FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                 WHERE s.id = :id FOR UPDATE',
                ['id' => $asignacionId]
            );
            if ($fila === null) {
                throw new DomainException('La asignación no existe.');
            }
            if ($fila['estado'] !== Actividad::PROGRAMADO) {
                throw new DomainException(
                    'Solo se puede quitar un auditor mientras el taller está programado; '
                    . 'después la asignación queda como registro de quién auditó.'
                );
            }
            $db->execute('DELETE FROM asignaciones WHERE id = :id', ['id' => $asignacionId]);

            return ['actividad_id' => (int) $fila['actividad_id'], 'alumno_id' => (int) $fila['alumno_id']];
        });
    }

    /**
     * Asignación aleatoria (ver Sorteo): completa lo que falta para que cada alumno activo tenga al menos
     * un taller y cada taller programado tenga $auditoresPorTaller auditores, sin tocar lo que ya está
     * asignado.
     *
     * Los alumnos sin horario registrado no participan: se devuelven aparte para avisar.
     *
     * @return array{creadas: int, auditoresPorTaller: int, alumnosCubiertos: int, talleresCubiertos: int,
     *     talleresSinCubrir: list<string>, talleresIncompletos: list<string>, alumnosSinTaller: list<string>,
     *     alumnosSinHorario: list<string>, cargaMinima: int, cargaMaxima: int} La carga es cuántos talleres
     *     audita cada alumno con horario al terminar.
     */
    public function sortear(int $asignadoPor, int $auditoresPorTaller, ?Randomizer $azar = null): array
    {
        if ($auditoresPorTaller < 1 || $auditoresPorTaller > self::MAX_AUDITORES_POR_TALLER) {
            throw new DomainException(
                'Indica cuántos auditores quieres por taller: un número del 1 al '
                . self::MAX_AUDITORES_POR_TALLER . '.'
            );
        }
        $azar ??= new Randomizer();

        return $this->db->transaction(function (Database $db) use ($asignadoPor, $auditoresPorTaller, $azar): array {
            // Bloquea alumnos y talleres: otro sorteo o una asignación manual esperan a que termine este.
            /** @var list<array{id: int, nombre: string, horario_id: ?int}> $alumnos */
            $alumnos = $db->fetchAll(
                "SELECT id, CONCAT(nombre, ' ', apellidos) AS nombre, horario_id FROM alumnos
                 WHERE activo = 1 ORDER BY id FOR UPDATE"
            );
            /** @var list<array{id: int, nombre: string, fecha: string, grupo_id: int, hora_inicio: string, hora_fin: string}> $talleres */
            $talleres = $db->fetchAll(
                "SELECT id, nombre, fecha, grupo_id, hora_inicio, hora_fin FROM actividades
                 WHERE activo = 1 AND estado = 'programado' ORDER BY id FOR UPDATE"
            );
            if ($alumnos === []) {
                throw new DomainException('No hay alumnos activos. Agrégalos en Alumnos antes de asignar al azar.');
            }
            if ($talleres === []) {
                throw new DomainException('No hay talleres programados que asignar.');
            }
            // Solo participan los alumnos con horario: sin él no se sabe a qué hora pueden auditar.
            $horarios = array_column(
                $db->fetchAll('SELECT id, hora_inicio, hora_fin FROM horarios_alumno'),
                null,
                'id'
            );
            $conHorario = [];
            $sinHorario = [];
            foreach ($alumnos as $alumno) {
                $horario = $horarios[$alumno['horario_id'] ?? 0] ?? null;
                if ($horario === null) {
                    $sinHorario[] = $alumno['nombre'];
                } else {
                    $conHorario[] = [
                        'id' => $alumno['id'],
                        'inicio' => (string) $horario['hora_inicio'],
                        'fin' => (string) $horario['hora_fin'],
                    ];
                }
            }
            if ($conHorario === []) {
                throw new DomainException(
                    'Ningún alumno activo tiene horario registrado. Regístralo en Alumnos antes de asignar al azar.'
                );
            }
            /** @var list<array{alumno_id: int, actividad_id: int, fecha: string, grupo_id: int}> $actuales */
            $actuales = $db->fetchAll(
                'SELECT s.alumno_id, s.actividad_id, a.fecha, a.grupo_id
                 FROM asignaciones s
                 JOIN actividades a ON a.id = s.actividad_id
                 JOIN alumnos al ON al.id = s.alumno_id
                 WHERE a.activo = 1 AND al.activo = 1'
            );

            $reparto = Sorteo::repartir(
                array_map(static fn (array $t): array => [
                    'id' => $t['id'],
                    'franja' => Sorteo::franja($t['fecha'], $t['grupo_id']),
                    'inicio' => $t['hora_inicio'],
                    'fin' => $t['hora_fin'],
                ], $talleres),
                $conHorario,
                array_map(static fn (array $s): array => [
                    'alumno_id' => $s['alumno_id'],
                    'actividad_id' => $s['actividad_id'],
                    'franja' => Sorteo::franja($s['fecha'], $s['grupo_id']),
                ], $actuales),
                $azar,
                $auditoresPorTaller
            );

            foreach ($reparto['nuevas'] as $nueva) {
                $this->insertar($db, $nueva['alumno_id'], $nueva['actividad_id'], $asignadoPor, self::ALEATORIA);
            }

            $conTaller = array_flip(array_column($actuales, 'alumno_id'));
            $cubiertos = array_flip(array_column($actuales, 'actividad_id'));
            $nombresAlumnos = array_column($alumnos, 'nombre', 'id');
            $nombresTalleres = array_column($talleres, 'nombre', 'id');
            $nombreTaller = static fn (int $id): string => (string) $nombresTalleres[$id];
            $carga = array_fill_keys(array_column($conHorario, 'id'), 0);
            foreach ([...$actuales, ...$reparto['nuevas']] as $a) {
                if (isset($carga[$a['alumno_id']])) {
                    $carga[$a['alumno_id']]++;
                }
            }

            return [
                'creadas' => count($reparto['nuevas']),
                'auditoresPorTaller' => $auditoresPorTaller,
                'alumnosCubiertos' => count(array_diff_key(
                    array_flip(array_column($reparto['nuevas'], 'alumno_id')),
                    $conTaller
                )),
                'talleresCubiertos' => count(array_diff_key(
                    array_flip(array_column($reparto['nuevas'], 'actividad_id')),
                    $cubiertos
                )),
                'talleresSinCubrir' => array_map($nombreTaller, $reparto['talleresSinCubrir']),
                'talleresIncompletos' => array_map($nombreTaller, $reparto['talleresIncompletos']),
                'alumnosSinTaller' => array_map(
                    static fn (int $id): string => (string) $nombresAlumnos[$id],
                    $reparto['alumnosSinTaller']
                ),
                'alumnosSinHorario' => $sinHorario,
                'cargaMinima' => min($carga),
                'cargaMaxima' => max($carga),
            ];
        });
    }

    /**
     * Situación de la cobertura: alumnos activos sin ningún taller, talleres programados sin auditor activo,
     * alumnos activos sin horario registrado y asignaciones programadas fuera del horario del alumno
     * (de antes de que existiera la regla, o porque después cambió el grupo del taller).
     *
     * @return array{
     *     alumnos: int, talleres: int,
     *     alumnosSinTaller: list<array{id: int, nombre: string, apellidos: string}>,
     *     talleresSinAuditor: list<array{id: int, nombre: string, fecha: string, hora_inicio: string,
     *         hora_fin: string, grupo_nombre: string, edificio: int, edificio_nombre: string}>,
     *     alumnosSinHorario: list<array{id: int, nombre: string, apellidos: string}>,
     *     fueraDeHorario: list<FueraDeHorario>,
     *     auditoresPorTaller: list<array{auditores: int, talleres: int}>,
     *     talleresPorAlumno: list<array{talleres: int, alumnos: int}>,
     *     asignacionesProgramadas: int
     * } auditoresPorTaller: cuántos talleres programados tienen 0, 1, 2… auditores activos;
     *   talleresPorAlumno: cuántos alumnos activos auditan 0, 1, 2… talleres activos;
     *   asignacionesProgramadas: las que quitaría quitarProgramadas().
     */
    public function cobertura(): array
    {
        /** @var list<array{id: int, nombre: string, apellidos: string}> $alumnosSinTaller */
        $alumnosSinTaller = $this->db->fetchAll(
            'SELECT al.id, al.nombre, al.apellidos FROM alumnos al
             WHERE al.activo = 1 AND NOT EXISTS (
                 SELECT 1 FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                 WHERE s.alumno_id = al.id AND a.activo = 1)
             ORDER BY al.apellidos, al.nombre, al.id'
        );
        /** @var list<array{id: int, nombre: string, fecha: string, hora_inicio: string, hora_fin: string, grupo_nombre: string, edificio: int, edificio_nombre: string}> $talleresSinAuditor */
        $talleresSinAuditor = $this->db->fetchAll(
            "SELECT a.id, a.nombre, a.fecha, a.hora_inicio, a.hora_fin, g.nombre AS grupo_nombre,
                    e.numero AS edificio, e.nombre AS edificio_nombre
             FROM actividades a
             JOIN grupos_taller g ON g.id = a.grupo_id
             JOIN edificios e ON e.id = a.edificio_id
             WHERE a.activo = 1 AND a.estado = 'programado' AND NOT EXISTS (
                 SELECT 1 FROM asignaciones s JOIN alumnos al ON al.id = s.alumno_id
                 WHERE s.actividad_id = a.id AND al.activo = 1)
             ORDER BY a.fecha, a.hora_inicio, a.nombre, a.id"
        );

        /** @var list<array{id: int, nombre: string, apellidos: string}> $alumnosSinHorario */
        $alumnosSinHorario = $this->db->fetchAll(
            'SELECT id, nombre, apellidos FROM alumnos WHERE activo = 1 AND horario_id IS NULL
             ORDER BY apellidos, nombre, id'
        );
        /** @var list<FueraDeHorario> $fueraDeHorario */
        $fueraDeHorario = $this->db->fetchAll(
            "SELECT s.id, al.id AS alumno_id, al.nombre AS alumno_nombre, al.apellidos AS alumno_apellidos,
                    h.nombre AS horario, h.hora_inicio AS horario_inicio, h.hora_fin AS horario_fin,
                    a.id AS actividad_id, a.nombre, a.fecha, a.hora_inicio, a.hora_fin, g.nombre AS grupo_nombre
             FROM asignaciones s
             JOIN alumnos al ON al.id = s.alumno_id
             JOIN horarios_alumno h ON h.id = al.horario_id
             JOIN actividades a ON a.id = s.actividad_id
             JOIN grupos_taller g ON g.id = a.grupo_id
             WHERE " . self::FUERA_DE_HORARIO . "
             ORDER BY al.apellidos, al.nombre, al.id, a.fecha, a.hora_inicio, a.id"
        );
        /** @var list<array{auditores: int, talleres: int}> $auditoresPorTaller */
        $auditoresPorTaller = $this->db->fetchAll(
            "SELECT auditores, COUNT(*) AS talleres FROM (
                 SELECT (SELECT COUNT(*) FROM asignaciones s JOIN alumnos al ON al.id = s.alumno_id
                         WHERE s.actividad_id = a.id AND al.activo = 1) AS auditores
                 FROM actividades a WHERE a.activo = 1 AND a.estado = 'programado') t
             GROUP BY auditores ORDER BY auditores"
        );
        /** @var list<array{talleres: int, alumnos: int}> $talleresPorAlumno */
        $talleresPorAlumno = $this->db->fetchAll(
            'SELECT talleres, COUNT(*) AS alumnos FROM (
                 SELECT (SELECT COUNT(*) FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                         WHERE s.alumno_id = al.id AND a.activo = 1) AS talleres
                 FROM alumnos al WHERE al.activo = 1) t
             GROUP BY talleres ORDER BY talleres'
        );

        return [
            'alumnos' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM alumnos WHERE activo = 1'),
            'talleres' => (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM actividades WHERE activo = 1 AND estado = 'programado'"
            ),
            'alumnosSinTaller' => $alumnosSinTaller,
            'talleresSinAuditor' => $talleresSinAuditor,
            'alumnosSinHorario' => $alumnosSinHorario,
            'fueraDeHorario' => $fueraDeHorario,
            'auditoresPorTaller' => $auditoresPorTaller,
            'talleresPorAlumno' => $talleresPorAlumno,
            'asignacionesProgramadas' => (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                 WHERE a.activo = 1 AND a.estado = 'programado'"
            ),
        ];
    }

    /**
     * Quita las asignaciones de talleres programados que no caben en el horario del alumno.
     * Las de talleres realizados o no realizados se conservan como registro.
     *
     * @return int Asignaciones quitadas.
     */
    public function quitarFueraDeHorario(): int
    {
        return $this->db->execute(
            'DELETE s FROM asignaciones s
             JOIN alumnos al ON al.id = s.alumno_id
             JOIN horarios_alumno h ON h.id = al.horario_id
             JOIN actividades a ON a.id = s.actividad_id
             WHERE ' . self::FUERA_DE_HORARIO
        );
    }

    /**
     * Quita todas las asignaciones de talleres programados, manuales y al azar, para volver a repartir.
     * Las de talleres realizados o no realizados se conservan como registro (regla 6).
     *
     * @return int Asignaciones quitadas.
     */
    public function quitarProgramadas(): int
    {
        return $this->db->execute(
            "DELETE s FROM asignaciones s
             JOIN actividades a ON a.id = s.actividad_id
             WHERE a.activo = 1 AND a.estado = 'programado'"
        );
    }

    /**
     * Talleres programados sin ningún auditor activo (aviso del panel central).
     */
    public function talleresSinAuditor(): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM actividades a
             WHERE a.activo = 1 AND a.estado = 'programado' AND NOT EXISTS (
                 SELECT 1 FROM asignaciones s JOIN alumnos al ON al.id = s.alumno_id
                 WHERE s.actividad_id = a.id AND al.activo = 1)"
        );
    }

    /**
     * Alumnos de un taller, con quién y cuándo los asignó, si fue al azar, su horario y si el taller cabe
     * en él (en_horario: 1 sí, 0 no, null si el alumno no tiene horario registrado).
     *
     * @return list<array{id: int, alumno_id: int, nombre: string, apellidos: string, activo: int,
     *     origen: string, asignado_en: string, asignado_por: string, horario: ?string,
     *     horario_inicio: ?string, horario_fin: ?string, en_horario: ?int}>
     */
    public function auditoresDe(int $actividadId): array
    {
        /** @var list<array{id: int, alumno_id: int, nombre: string, apellidos: string, activo: int, origen: string, asignado_en: string, asignado_por: string, horario: ?string, horario_inicio: ?string, horario_fin: ?string, en_horario: ?int}> */
        return $this->db->fetchAll(
            "SELECT s.id, s.alumno_id, al.nombre, al.apellidos, al.activo, s.origen, s.asignado_en,
                    CONCAT(p.nombre, ' ', p.apellidos) AS asignado_por,
                    h.nombre AS horario, h.hora_inicio AS horario_inicio, h.hora_fin AS horario_fin,
                    CASE WHEN h.id IS NULL THEN NULL WHEN " . self::EN_HORARIO . " THEN 1 ELSE 0 END AS en_horario
             FROM asignaciones s
             JOIN actividades a ON a.id = s.actividad_id
             JOIN alumnos al ON al.id = s.alumno_id
             LEFT JOIN horarios_alumno h ON h.id = al.horario_id
             JOIN usuarios p ON p.id = s.asignado_por
             WHERE s.actividad_id = :id
             ORDER BY al.apellidos, al.nombre",
            ['id' => $actividadId]
        );
    }

    /**
     * Talleres activos de un alumno (panel del alumno), con si caben en su horario (en_horario: 1 sí,
     * 0 no, null si el alumno no tiene horario registrado).
     *
     * @return list<array{id: int, actividad_id: int, nombre: string, fecha: string, hora_inicio: string,
     *     hora_fin: string, grupo_nombre: string, edificio: int, edificio_nombre: string, carrera: string,
     *     estado: string, origen: string, asignado_en: string, asignado_por: string, en_horario: ?int}>
     */
    public function talleresDe(int $alumnoId): array
    {
        /** @var list<array{id: int, actividad_id: int, nombre: string, fecha: string, hora_inicio: string, hora_fin: string, grupo_nombre: string, edificio: int, edificio_nombre: string, carrera: string, estado: string, origen: string, asignado_en: string, asignado_por: string, en_horario: ?int}> */
        return $this->db->fetchAll(
            "SELECT s.id, a.id AS actividad_id, a.nombre, a.fecha, a.hora_inicio, a.hora_fin,
                    g.nombre AS grupo_nombre, e.numero AS edificio, e.nombre AS edificio_nombre,
                    c.nombre AS carrera, a.estado, s.origen, s.asignado_en,
                    CONCAT(p.nombre, ' ', p.apellidos) AS asignado_por,
                    CASE WHEN h.id IS NULL THEN NULL WHEN " . self::EN_HORARIO . " THEN 1 ELSE 0 END AS en_horario
             FROM asignaciones s
             JOIN actividades a ON a.id = s.actividad_id
             JOIN alumnos al ON al.id = s.alumno_id
             LEFT JOIN horarios_alumno h ON h.id = al.horario_id
             JOIN grupos_taller g ON g.id = a.grupo_id
             JOIN edificios e ON e.id = a.edificio_id
             JOIN carreras c ON c.id = a.carrera_id
             JOIN usuarios p ON p.id = s.asignado_por
             WHERE s.alumno_id = :id AND a.activo = 1
             ORDER BY a.fecha, a.hora_inicio, a.nombre",
            ['id' => $alumnoId]
        );
    }

    /**
     * Talleres programados que se le pueden asignar a un alumno: los que caben en su horario, en franjas
     * en las que está libre. Sin horario registrado no hay ninguno.
     *
     * @return list<array{id: int, nombre: string, fecha: string, hora_inicio: string, hora_fin: string,
     *     grupo_nombre: string, edificio: int, edificio_nombre: string, auditores: int}>
     */
    public function talleresDisponiblesPara(int $alumnoId): array
    {
        /** @var list<array{id: int, nombre: string, fecha: string, hora_inicio: string, hora_fin: string, grupo_nombre: string, edificio: int, edificio_nombre: string, auditores: int}> */
        return $this->db->fetchAll(
            "SELECT a.id, a.nombre, a.fecha, a.hora_inicio, a.hora_fin, g.nombre AS grupo_nombre,
                    e.numero AS edificio, e.nombre AS edificio_nombre,
                    (SELECT COUNT(*) FROM asignaciones s2 JOIN alumnos al2 ON al2.id = s2.alumno_id
                     WHERE s2.actividad_id = a.id AND al2.activo = 1) AS auditores
             FROM actividades a
             JOIN grupos_taller g ON g.id = a.grupo_id
             JOIN edificios e ON e.id = a.edificio_id
             JOIN alumnos al ON al.id = :alumno_horario
             JOIN horarios_alumno h ON h.id = al.horario_id
             WHERE a.activo = 1 AND a.estado = 'programado' AND " . self::EN_HORARIO . " AND NOT EXISTS (
                 SELECT 1 FROM asignaciones s JOIN actividades b ON b.id = s.actividad_id
                 WHERE s.alumno_id = :alumno AND b.activo = 1 AND b.fecha = a.fecha AND b.grupo_id = a.grupo_id)
             ORDER BY a.fecha, a.hora_inicio, a.nombre, a.id",
            ['alumno' => $alumnoId, 'alumno_horario' => $alumnoId]
        );
    }

    /**
     * Alumnos activos que pueden auditar un taller: aquellos en cuyo horario cabe y que están libres a esa hora.
     *
     * @return list<array{id: int, nombre: string, apellidos: string, horario: string, talleres: int}>
     */
    public function alumnosDisponiblesPara(int $actividadId): array
    {
        /** @var list<array{id: int, nombre: string, apellidos: string, horario: string, talleres: int}> */
        return $this->db->fetchAll(
            'SELECT al.id, al.nombre, al.apellidos, h.nombre AS horario,
                    (SELECT COUNT(*) FROM asignaciones s2 JOIN actividades a2 ON a2.id = s2.actividad_id
                     WHERE s2.alumno_id = al.id AND a2.activo = 1) AS talleres
             FROM alumnos al
             JOIN horarios_alumno h ON h.id = al.horario_id
             JOIN actividades t ON t.id = :actividad
             WHERE al.activo = 1 AND h.hora_inicio <= t.hora_inicio AND t.hora_fin <= h.hora_fin AND NOT EXISTS (
                 SELECT 1 FROM asignaciones s JOIN actividades a ON a.id = s.actividad_id
                 WHERE s.alumno_id = al.id AND a.activo = 1 AND a.fecha = t.fecha AND a.grupo_id = t.grupo_id)
             ORDER BY al.apellidos, al.nombre, al.id',
            ['actividad' => $actividadId]
        );
    }

    /**
     * El taller debe caber en el horario del alumno; sin horario registrado no se le asignan talleres.
     *
     * @param array{nombre: string, horario_id: ?int} $alumno
     * @param array{hora_inicio: string, hora_fin: string} $taller
     */
    private function exigirHorario(Database $db, array $alumno, array $taller): void
    {
        /** @var array{nombre: string, hora_inicio: string, hora_fin: string}|null $horario */
        $horario = $alumno['horario_id'] === null ? null : $db->fetchOne(
            'SELECT nombre, hora_inicio, hora_fin FROM horarios_alumno WHERE id = :id',
            ['id' => $alumno['horario_id']]
        );
        if ($horario === null) {
            throw new DomainException(
                'Registra el horario de ' . $alumno['nombre'] . ' (matutino, vespertino o todo el día) '
                . 'antes de asignarle talleres.'
            );
        }
        [$desde, $hasta] = [$horario['hora_inicio'], $horario['hora_fin']];
        if (!HorarioAlumno::cubre($desde, $hasta, $taller['hora_inicio'], $taller['hora_fin'])) {
            throw new DomainException(
                $alumno['nombre'] . ' tiene horario ' . HorarioAlumno::etiqueta($horario['nombre'], $desde, $hasta)
                . ' y el taller es de ' . substr($taller['hora_inicio'], 0, 5)
                . ' a ' . substr($taller['hora_fin'], 0, 5) . ': elige un taller dentro de su horario.'
            );
        }
    }

    private function insertar(Database $db, int $alumnoId, int $actividadId, int $asignadoPor, string $origen): void
    {
        $db->execute(
            'INSERT INTO asignaciones (actividad_id, alumno_id, origen, asignado_por)
             VALUES (:actividad_id, :alumno_id, :origen, :asignado_por)',
            [
                'actividad_id' => $actividadId,
                'alumno_id' => $alumnoId,
                'origen' => $origen,
                'asignado_por' => $asignadoPor,
            ]
        );
    }
}
