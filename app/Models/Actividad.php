<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;

/**
 * Talleres (actividades): filtros del panel, alta, edición y desactivación.
 *
 * @phpstan-type Filtros array{nombre?: string, carrera_id?: int|null, edificio_id?: int|null}
 * @phpstan-type DatosTaller array{
 *     nombre: string, carrera_id: int, edificio_id: int, fecha: string, hora_inicio: string, hora_fin: string
 * }
 * @phpstan-type TallerFila array{
 *     id: int, nombre: string, carrera_id: int, carrera: string, edificio_id: int, edificio: int,
 *     fecha: string, hora_inicio: string, hora_fin: string, estado: string, motivo_no_realizado: ?string,
 *     activo: int, creado_por: int, creado_en: string, actualizado_en: string
 * }
 * @phpstan-type PanelFila array{
 *     id: int, nombre: string, carrera: string, edificio: int, fecha: string, hora_inicio: string,
 *     hora_fin: string, estado: string, motivo_no_realizado: ?string, auditores: ?string
 * }
 */
final class Actividad
{
    public const PROGRAMADO = 'programado';
    public const REALIZADO = 'realizado';
    public const NO_REALIZADO = 'no_realizado';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Consulta del panel (SDD 4.4). Cada filtro se agrega solo si llegó; con $usuarioSesion
     * (rol auditor) se limita a los talleres asignados a ese usuario, pero se muestran todos
     * sus auditores.
     *
     * @param Filtros $filtros
     * @return array{filas: list<PanelFila>, total: int, pagina: int, paginas: int}
     */
    public function filtrar(array $filtros, ?int $usuarioSesion, int $pagina = 1, int $porPagina = 20): array
    {
        [$where, $params] = $this->condiciones($filtros, $usuarioSesion);

        $total = (int) $this->db->fetchValue("SELECT COUNT(*) FROM actividades a WHERE {$where}", $params);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);

        /** @var list<PanelFila> $filas */
        $filas = $this->db->fetchAll(
            "SELECT a.id, a.nombre, c.nombre AS carrera, e.numero AS edificio,
                    a.fecha, a.hora_inicio, a.hora_fin, a.estado, a.motivo_no_realizado,
                    GROUP_CONCAT(CONCAT(u.nombre, ' ', u.apellidos)
                                 ORDER BY u.apellidos, u.nombre SEPARATOR ', ') AS auditores
             FROM actividades a
             JOIN carreras  c ON c.id = a.carrera_id
             JOIN edificios e ON e.id = a.edificio_id
             LEFT JOIN asignaciones s ON s.actividad_id = a.id
             LEFT JOIN usuarios     u ON u.id = s.usuario_id
             WHERE {$where}
             GROUP BY a.id, c.nombre, e.numero
             ORDER BY a.fecha, a.hora_inicio, a.id
             LIMIT :limite OFFSET :desplazamiento",
            $params + ['limite' => $porPagina, 'desplazamiento' => ($pagina - 1) * $porPagina]
        );

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /**
     * @param Filtros $filtros
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function condiciones(array $filtros, ?int $usuarioSesion): array
    {
        $where = ['a.activo = 1'];
        $params = [];

        $nombre = trim($filtros['nombre'] ?? '');
        if ($nombre !== '') {
            // '!' escapa los comodines para que % y _ se busquen como texto.
            $where[] = "a.nombre LIKE :nombre ESCAPE '!'";
            $params['nombre'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $nombre) . '%';
        }
        if (($filtros['carrera_id'] ?? null) !== null) {
            $where[] = 'a.carrera_id = :carrera_id';
            $params['carrera_id'] = $filtros['carrera_id'];
        }
        if (($filtros['edificio_id'] ?? null) !== null) {
            $where[] = 'a.edificio_id = :edificio_id';
            $params['edificio_id'] = $filtros['edificio_id'];
        }
        if ($usuarioSesion !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM asignaciones m
                                WHERE m.actividad_id = a.id AND m.usuario_id = :usuario_sesion)';
            $params['usuario_sesion'] = $usuarioSesion;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @return TallerFila|null
     */
    public function buscarPorId(int $id): ?array
    {
        /** @var TallerFila|null */
        return $this->db->fetchOne(
            'SELECT a.*, c.nombre AS carrera, e.numero AS edificio
             FROM actividades a
             JOIN carreras  c ON c.id = a.carrera_id
             JOIN edificios e ON e.id = a.edificio_id
             WHERE a.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Crea el taller en estado programado y deja la primera entrada de la bitácora.
     *
     * @param DatosTaller $datos
     */
    public function crear(array $datos, int $usuarioId): int
    {
        return $this->db->transaction(function (Database $db) use ($datos, $usuarioId): int {
            $db->execute(
                'INSERT INTO actividades (nombre, carrera_id, edificio_id, fecha, hora_inicio, hora_fin, creado_por)
                 VALUES (:nombre, :carrera_id, :edificio_id, :fecha, :hora_inicio, :hora_fin, :creado_por)',
                $datos + ['creado_por' => $usuarioId]
            );
            $id = $db->lastInsertId();
            (new Bitacora($db))->registrar($id, null, self::PROGRAMADO, null, $usuarioId);

            return $id;
        });
    }

    /**
     * @param DatosTaller $datos
     */
    public function actualizar(int $id, array $datos): void
    {
        $this->db->execute(
            'UPDATE actividades
             SET nombre = :nombre, carrera_id = :carrera_id, edificio_id = :edificio_id,
                 fecha = :fecha, hora_inicio = :hora_inicio, hora_fin = :hora_fin
             WHERE id = :id AND activo = 1',
            $datos + ['id' => $id]
        );
    }

    /**
     * Los talleres no se borran (regla 4): se desactivan y dejan de aparecer en el panel.
     */
    public function desactivar(int $id): void
    {
        $this->db->execute('UPDATE actividades SET activo = 0 WHERE id = :id', ['id' => $id]);
    }

    /**
     * Valida el formulario de taller. Devuelve los datos listos para guardar y los errores por campo.
     *
     * @param array<string, mixed> $input
     * @return array{0: DatosTaller, 1: array<string, string>}
     */
    public static function validar(array $input, Carrera $carreras, Edificio $edificios): array
    {
        $texto = static fn (string $campo): string => is_string($input[$campo] ?? null) ? trim($input[$campo]) : '';
        $errores = [];

        $nombre = trim((string) preg_replace('/\s+/u', ' ', $texto('nombre')));
        if ($nombre === '') {
            $errores['nombre'] = 'Escribe el nombre del taller.';
        } elseif (mb_strlen($nombre) > 150) {
            $errores['nombre'] = 'El nombre no puede pasar de 150 caracteres.';
        }

        $carreraId = ctype_digit($texto('carrera_id')) ? (int) $texto('carrera_id') : 0;
        if ($carreraId === 0 || !$carreras->existeActiva($carreraId)) {
            $errores['carrera_id'] = 'Elige una carrera de la lista.';
        }

        $edificioId = ctype_digit($texto('edificio_id')) ? (int) $texto('edificio_id') : 0;
        if ($edificioId === 0 || !$edificios->existeActivo($edificioId)) {
            $errores['edificio_id'] = 'Elige un edificio de la lista.';
        }

        $fecha = self::normalizarFecha($texto('fecha'));
        if ($fecha === null) {
            $errores['fecha'] = 'Escribe una fecha válida.';
        }

        $inicio = self::normalizarHora($texto('hora_inicio'));
        $fin = self::normalizarHora($texto('hora_fin'));
        if ($inicio === null) {
            $errores['hora_inicio'] = 'Escribe una hora válida (HH:MM).';
        }
        if ($fin === null) {
            $errores['hora_fin'] = 'Escribe una hora válida (HH:MM).';
        } elseif ($inicio !== null && $fin <= $inicio) {
            $errores['hora_fin'] = 'La hora de fin debe ser posterior a la de inicio.';
        }

        return [[
            'nombre' => $nombre,
            'carrera_id' => $carreraId,
            'edificio_id' => $edificioId,
            'fecha' => (string) $fecha,
            'hora_inicio' => (string) $inicio,
            'hora_fin' => (string) $fin,
        ], $errores];
    }

    private static function normalizarFecha(string $valor): ?string
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $fecha !== false && $fecha->format('Y-m-d') === $valor ? $valor : null;
    }

    /**
     * Acepta HH:MM o HH:MM:SS y devuelve HH:MM:00.
     */
    private static function normalizarHora(string $valor): ?string
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $valor, $m) !== 1) {
            return null;
        }

        return $m[1] . ':' . $m[2] . ':00';
    }
}
