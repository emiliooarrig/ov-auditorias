<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Grupos de talleres (Taller 1 … Taller 6). Cada grupo tiene un horario fijo y cada taller
 * pertenece a uno: el grupo que se elige define la hora de inicio y de fin del taller.
 *
 * @phpstan-type GrupoFila array{id: int, numero: int, nombre: string, hora_inicio: string, hora_fin: string}
 */
final class GrupoTaller
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return list<GrupoFila>
     */
    public function activos(): array
    {
        /** @var list<GrupoFila> */
        return $this->db->fetchAll(
            'SELECT id, numero, nombre, hora_inicio, hora_fin FROM grupos_taller WHERE activo = 1 ORDER BY numero'
        );
    }

    /**
     * @return GrupoFila|null
     */
    public function buscarActivo(int $id): ?array
    {
        /** @var GrupoFila|null */
        return $this->db->fetchOne(
            'SELECT id, numero, nombre, hora_inicio, hora_fin FROM grupos_taller WHERE id = :id AND activo = 1',
            ['id' => $id]
        );
    }
}
