<?php

declare(strict_types=1);

namespace App\Models;

use Random\Randomizer;

/**
 * Reparto aleatorio y balanceado de talleres entre alumnos. No toca la base: recibe la situación
 * actual y devuelve las asignaciones nuevas (Asignacion::sortear las guarda).
 *
 * Conserva todo lo que ya existe y solo completa:
 *   1. Cada taller recibe auditores hasta tener los que se piden por taller, por vueltas: en la primera
 *      cada taller sin auditor recibe uno, en la segunda cada taller con uno recibe el segundo, y así.
 *      Cada lugar va al alumno con menos talleres que pueda auditarlo. Si no alcanzan los alumnos, así
 *      ningún taller recibe otro auditor mientras uno de su franja siga con menos.
 *   2. Cada alumno sin taller recibe el taller con menos auditores que pueda auditar, aunque pase de los
 *      que se piden. Si hay más alumnos que lugares, así cada taller recibe un número parecido de auditores.
 * Un alumno puede auditar un taller si el taller cabe en su horario (Matutino, Vespertino o Todo el día)
 * y si no tiene otro taller en la misma franja (misma fecha y grupo, es decir, a la misma hora).
 * Los talleres se recorren franja por franja: así cada franja toma a los alumnos con menos carga. Sin
 * diferencias de horario, la diferencia entre el que más y el que menos tiene no pasa de uno (si ya
 * existían asignaciones, se acerca lo más posible); con ellas, un alumno de horario corto recibe menos
 * talleres porque caben menos franjas en su horario. El azar decide el orden de las franjas y los
 * empates: alumnos, talleres y franjas se barajan antes de empezar.
 *
 * Un taller solo se queda con menos auditores de los pedidos si todos los alumnos cuyo horario lo cubre
 * tienen ya otro taller a esa hora; un alumno solo queda sin taller si no hay ningún taller dentro de su
 * horario.
 */
final class Sorteo
{
    /**
     * @param list<array{id: int, franja: string, inicio: string, fin: string}> $talleres Talleres que se
     *        reparten (programados y activos), con su hora de inicio y fin (HH:MM:SS).
     * @param list<array{id: int, inicio: string, fin: string}> $alumnos Alumnos activos con su horario.
     * @param list<array{alumno_id: int, actividad_id: int, franja: string}> $asignaciones Asignaciones
     *        actuales de esos alumnos en talleres activos (también los ya realizados: cuentan como carga
     *        y ocupan su franja).
     * @param int $auditoresPorTaller Auditores que debe tener cada taller (1 o más).
     * @return array{
     *     nuevas: list<array{alumno_id: int, actividad_id: int}>,
     *     talleresSinCubrir: list<int>,
     *     talleresIncompletos: list<int>,
     *     alumnosSinTaller: list<int>
     * } talleresSinCubrir: sin ningún auditor; talleresIncompletos: con alguno, pero menos de los pedidos.
     */
    public static function repartir(
        array $talleres,
        array $alumnos,
        array $asignaciones,
        Randomizer $azar,
        int $auditoresPorTaller = 1,
    ): array {
        $carga = array_fill_keys(array_column($alumnos, 'id'), 0);
        $horario = array_column($alumnos, null, 'id');
        $puede = static fn (int $alumno, array $taller): bool => HorarioAlumno::cubre(
            $horario[$alumno]['inicio'],
            $horario[$alumno]['fin'],
            $taller['inicio'],
            $taller['fin']
        );
        $auditores = array_fill_keys(array_column($talleres, 'id'), 0);
        /** @var array<int, array<string, true>> $ocupado */
        $ocupado = [];
        foreach ($asignaciones as $a) {
            if (!isset($carga[$a['alumno_id']])) {
                continue;
            }
            $carga[$a['alumno_id']]++;
            $ocupado[$a['alumno_id']][$a['franja']] = true;
            if (isset($auditores[$a['actividad_id']])) {
                $auditores[$a['actividad_id']]++;
            }
        }

        /** @var list<int> $ordenAlumnos */
        $ordenAlumnos = $azar->shuffleArray(array_keys($carga));
        /** @var list<array{id: int, franja: string, inicio: string, fin: string}> $ordenTalleres */
        $ordenTalleres = $azar->shuffleArray($talleres);
        // Agrupados por franja, en un orden de franjas también al azar (usort es estable).
        $ordenFranjas = array_flip($azar->shuffleArray(array_values(array_unique(array_column($talleres, 'franja')))));
        usort(
            $ordenTalleres,
            static fn (array $a, array $b): int => $ordenFranjas[$a['franja']] <=> $ordenFranjas[$b['franja']]
        );
        $nuevas = [];
        $alumnosSinTaller = [];

        $asignar = static function (int $alumno, array $taller) use (&$nuevas, &$carga, &$auditores, &$ocupado): void {
            $nuevas[] = ['alumno_id' => $alumno, 'actividad_id' => $taller['id']];
            $carga[$alumno]++;
            $auditores[$taller['id']]++;
            $ocupado[$alumno][$taller['franja']] = true;
        };

        // 1. Cada taller hasta los auditores pedidos, una vuelta por lugar: el alumno libre y en horario
        //    con menos carga. Un taller que no se llena en una vuelta ya no se llena en las siguientes.
        for ($vuelta = 1; $vuelta <= $auditoresPorTaller; $vuelta++) {
            foreach ($ordenTalleres as $taller) {
                if ($auditores[$taller['id']] >= $vuelta) {
                    continue;
                }
                $elegido = null;
                foreach ($ordenAlumnos as $alumno) {
                    if (
                        !isset($ocupado[$alumno][$taller['franja']])
                        && $puede($alumno, $taller)
                        && ($elegido === null || $carga[$alumno] < $carga[$elegido])
                    ) {
                        $elegido = $alumno;
                    }
                }
                if ($elegido !== null) {
                    $asignar($elegido, $taller);
                }
            }
        }

        // 2. Ningún alumno sin taller: el taller libre y en su horario con menos auditores.
        foreach ($ordenAlumnos as $alumno) {
            if ($carga[$alumno] > 0) {
                continue;
            }
            $elegido = null;
            foreach ($ordenTalleres as $taller) {
                if (
                    !isset($ocupado[$alumno][$taller['franja']])
                    && $puede($alumno, $taller)
                    && ($elegido === null || $auditores[$taller['id']] < $auditores[$elegido['id']])
                ) {
                    $elegido = $taller;
                }
            }
            if ($elegido === null) {
                $alumnosSinTaller[] = $alumno;
            } else {
                $asignar($alumno, $elegido);
            }
        }

        $talleresSinCubrir = [];
        $talleresIncompletos = [];
        foreach ($talleres as $taller) {
            if ($auditores[$taller['id']] === 0) {
                $talleresSinCubrir[] = $taller['id'];
            } elseif ($auditores[$taller['id']] < $auditoresPorTaller) {
                $talleresIncompletos[] = $taller['id'];
            }
        }

        return [
            'nuevas' => $nuevas,
            'talleresSinCubrir' => $talleresSinCubrir,
            'talleresIncompletos' => $talleresIncompletos,
            'alumnosSinTaller' => $alumnosSinTaller,
        ];
    }

    /**
     * Franja horaria de un taller: misma fecha y mismo grupo (Taller 1 … 6) es la misma hora.
     */
    public static function franja(string $fecha, int $grupoId): string
    {
        return substr($fecha, 0, 10) . '|' . $grupoId;
    }
}
