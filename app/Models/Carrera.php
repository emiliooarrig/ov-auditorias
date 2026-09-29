<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Catálogo de carreras para filtros y formularios.
 */
final class Carrera
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public function activas(): array
    {
        /** @var list<array{id: int, nombre: string}> */
        return $this->db->fetchAll('SELECT id, nombre FROM carreras WHERE activo = 1 ORDER BY nombre');
    }

    public function existeActiva(int $id): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM carreras WHERE id = :id AND activo = 1', ['id' => $id]) !== null;
    }
}
