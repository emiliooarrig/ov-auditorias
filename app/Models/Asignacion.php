<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

/**
 * Asignaciones taller–auditor (RF-04, RF-05; reglas de negocio 1, 2 y 6).
 */
final class Asignacion
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Asigna varios talleres a un auditor en una sola transacción. Omite los que ya tenía
     * y los talleres inexistentes o desactivados.
     *
     * @param list<int> $actividadIds
     * @return array{creadas: list<string>, existentes: int, invalidas: int}
     *         Nombres de los talleres asignados, cuántos ya lo estaban y cuántos no se pudieron usar.
     */
    public function asignar(int $usuarioId, array $actividadIds, int $asignadoPor): array
    {
        $actividadIds = array_values(array_unique(array_filter($actividadIds, static fn (int $id): bool => $id > 0)));
        if ($actividadIds === []) {
            throw new DomainException('Elige al menos un taller.');
        }

        return $this->db->transaction(function (Database $db) use ($usuarioId, $actividadIds, $asignadoPor): array {
            // Bloquea al usuario para que no lo desactiven a media asignación.
            $usuario = $db->fetchOne(
                'SELECT u.activo, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id = u.rol_id
                 WHERE u.id = :id FOR UPDATE',
                ['id' => $usuarioId]
            );
            if ($usuario === null || $usuario['rol'] !== Usuario::AUDITOR) {
                throw new DomainException('Elige un auditor de la lista.');
            }
            if ((int) $usuario['activo'] !== 1) {
                throw new DomainException('No se pueden asignar talleres a un usuario desactivado.');
            }

            $marcadores = implode(', ', array_fill(0, count($actividadIds), '?'));
            /** @var list<array{id: int, nombre: string}> $talleres */
            $talleres = $db->fetchAll(
                "SELECT id, nombre FROM actividades
                 WHERE activo = 1 AND id IN ({$marcadores})
                 ORDER BY fecha, hora_inicio",
                $actividadIds
            );

            $creadas = [];
            foreach ($talleres as $taller) {
                // ON DUPLICATE KEY no cambia nada si la pareja ya existía: 0 filas afectadas (regla 2).
                $filas = $db->execute(
                    'INSERT INTO asignaciones (actividad_id, usuario_id, asignado_por)
                     VALUES (:actividad_id, :usuario_id, :asignado_por)
                     ON DUPLICATE KEY UPDATE id = id',
                    ['actividad_id' => $taller['id'], 'usuario_id' => $usuarioId, 'asignado_por' => $asignadoPor]
                );
                if ($filas === 1) {
                    $creadas[] = $taller['nombre'];
                }
            }

            return [
                'creadas' => $creadas,
                'existentes' => count($talleres) - count($creadas),
                'invalidas' => count($actividadIds) - count($talleres),
            ];
        });
    }

    /**
     * Quita una asignación solo si el taller sigue programado (regla 6).
     *
     * @return array{actividad_id: int, usuario_id: int}
     */
    public function quitar(int $asignacionId): array
    {
        return $this->db->transaction(function (Database $db) use ($asignacionId): array {
            $fila = $db->fetchOne(
                'SELECT s.actividad_id, s.usuario_id, a.estado
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

            return ['actividad_id' => (int) $fila['actividad_id'], 'usuario_id' => (int) $fila['usuario_id']];
        });
    }

    public function estaAsignado(int $actividadId, int $usuarioId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM asignaciones WHERE actividad_id = :a AND usuario_id = :u',
            ['a' => $actividadId, 'u' => $usuarioId]
        ) !== null;
    }

    /**
     * Auditores de un taller, con quién y cuándo los asignó.
     *
     * @return list<array{id: int, usuario_id: int, nombre: string, apellidos: string, correo: string,
     *     asignado_en: string, asignado_por: string}>
     */
    public function auditoresDe(int $actividadId): array
    {
        /** @var list<array{id: int, usuario_id: int, nombre: string, apellidos: string, correo: string, asignado_en: string, asignado_por: string}> */
        return $this->db->fetchAll(
            "SELECT s.id, s.usuario_id, u.nombre, u.apellidos, u.correo, s.asignado_en,
                    CONCAT(p.nombre, ' ', p.apellidos) AS asignado_por
             FROM asignaciones s
             JOIN usuarios u ON u.id = s.usuario_id
             JOIN usuarios p ON p.id = s.asignado_por
             WHERE s.actividad_id = :id
             ORDER BY u.apellidos, u.nombre",
            ['id' => $actividadId]
        );
    }

    /**
     * Talleres activos asignados a un usuario (para la pantalla de asignaciones).
     *
     * @return list<array{id: int, actividad_id: int, nombre: string, fecha: string, hora_inicio: string,
     *     hora_fin: string, estado: string}>
     */
    public function talleresDe(int $usuarioId): array
    {
        /** @var list<array{id: int, actividad_id: int, nombre: string, fecha: string, hora_inicio: string, hora_fin: string, estado: string}> */
        return $this->db->fetchAll(
            'SELECT s.id, a.id AS actividad_id, a.nombre, a.fecha, a.hora_inicio, a.hora_fin, a.estado
             FROM asignaciones s
             JOIN actividades a ON a.id = s.actividad_id
             WHERE s.usuario_id = :id AND a.activo = 1
             ORDER BY a.fecha, a.hora_inicio',
            ['id' => $usuarioId]
        );
    }
}
