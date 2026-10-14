<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Horarios en los que un alumno puede auditar: Matutino (07:00–14:00), Vespertino (13:00–19:00) y
 * Todo el día (07:00–19:00). Un alumno solo audita talleres que empiezan y terminan dentro del suyo;
 * los de 13:00 a 14:00 caben en el matutino y en el vespertino.
 *
 * @phpstan-type HorarioFila array{id: int, nombre: string, hora_inicio: string, hora_fin: string}
 */
final class HorarioAlumno
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return list<HorarioFila>
     */
    public function todos(): array
    {
        /** @var list<HorarioFila> */
        return $this->db->fetchAll(
            'SELECT id, nombre, hora_inicio, hora_fin FROM horarios_alumno ORDER BY hora_inicio, hora_fin'
        );
    }

    public function existe(int $id): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM horarios_alumno WHERE id = ?', [$id]) !== null;
    }

    /**
     * "Matutino (07:00–14:00)".
     */
    public static function etiqueta(string $nombre, string $inicio, string $fin): string
    {
        return $nombre . ' (' . substr($inicio, 0, 5) . '–' . substr($fin, 0, 5) . ')';
    }

    /**
     * ¿El taller de $inicio a $fin cabe en el horario de $desde a $hasta? Las horas llegan como TIME
     * (HH:MM:SS), así que se comparan como texto.
     */
    public static function cubre(string $desde, string $hasta, string $inicio, string $fin): bool
    {
        return strcmp($desde, $inicio) <= 0 && strcmp($fin, $hasta) <= 0;
    }
}
