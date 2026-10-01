<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Catálogo de edificios (número y área, p. ej. 9 e "Ingeniería") para filtros y formularios.
 */
final class Edificio
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return list<array{id: int, numero: int, nombre: string}>
     */
    public function activos(): array
    {
        /** @var list<array{id: int, numero: int, nombre: string}> */
        return $this->db->fetchAll('SELECT id, numero, nombre FROM edificios WHERE activo = 1 ORDER BY numero');
    }

    public function existeActivo(int $id): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM edificios WHERE id = :id AND activo = 1', ['id' => $id]) !== null;
    }
}
