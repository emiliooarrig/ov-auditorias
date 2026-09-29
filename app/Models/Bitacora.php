<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Bitácora de cambios de estado de los talleres (regla de negocio 5).
 * La creación de un taller se registra con estado_anterior NULL.
 */
final class Bitacora
{
    public function __construct(private readonly Database $db)
    {
    }

    public function registrar(int $actividadId, ?string $anterior, string $nuevo, ?string $motivo, int $usuarioId): void
    {
        $this->db->execute(
            'INSERT INTO bitacora_estados (actividad_id, estado_anterior, estado_nuevo, motivo, usuario_id)
             VALUES (:actividad_id, :anterior, :nuevo, :motivo, :usuario_id)',
            [
                'actividad_id' => $actividadId,
                'anterior' => $anterior,
                'nuevo' => $nuevo,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
            ]
        );
    }

    /**
     * Historial de un taller, del más reciente al más antiguo, con quién hizo cada cambio.
     *
     * @return list<array{
     *     estado_anterior: ?string, estado_nuevo: string, motivo: ?string, creado_en: string,
     *     usuario: string, correo: string
     * }>
     */
    public function historial(int $actividadId): array
    {
        /** @var list<array{estado_anterior: ?string, estado_nuevo: string, motivo: ?string, creado_en: string, usuario: string, correo: string}> */
        return $this->db->fetchAll(
            "SELECT b.estado_anterior, b.estado_nuevo, b.motivo, b.creado_en,
                    CONCAT(u.nombre, ' ', u.apellidos) AS usuario, u.correo
             FROM bitacora_estados b
             JOIN usuarios u ON u.id = b.usuario_id
             WHERE b.actividad_id = :id
             ORDER BY b.creado_en DESC, b.id DESC",
            ['id' => $actividadId]
        );
    }
}
