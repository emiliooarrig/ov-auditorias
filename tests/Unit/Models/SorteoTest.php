<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Sorteo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Reparto aleatorio sin base de datos: se prueban sus garantías con muchas semillas.
 */
final class SorteoTest extends TestCase
{
    private const MATUTINO = ['07:00:00', '14:00:00'];
    private const VESPERTINO = ['13:00:00', '19:00:00'];
    private const TODO_EL_DIA = ['07:00:00', '19:00:00'];

    /**
     * Alumnos con el mismo horario (todo el día, salvo que se indique otro).
     *
     * @param list<int> $ids
     * @param array{string, string} $horario
     * @return list<array{id: int, inicio: string, fin: string}>
     */
    private static function alumnos(array $ids, array $horario = self::TODO_EL_DIA): array
    {
        return array_map(
            static fn (int $id): array => ['id' => $id, 'inicio' => $horario[0], 'fin' => $horario[1]],
            $ids
        );
    }

    /**
     * Talleres de 10:00 a 11:00 salvo que traigan su hora: dentro de cualquier horario.
     *
     * @param list<array{id: int, franja: string, inicio?: string, fin?: string}> $talleres
     * @return list<array{id: int, franja: string, inicio: string, fin: string}>
     */
    private static function talleres(array $talleres): array
    {
        return array_map(static fn (array $t): array => $t + ['inicio' => '10:00:00', 'fin' => '11:00:00'], $talleres);
    }

    /**
     * Cuántas asignaciones nuevas hay por alumno o por taller, ordenado por id.
     *
     * @param list<array{alumno_id: int, actividad_id: int}> $nuevas
     * @return array<int, int>
     */
    private static function conteo(array $nuevas, string $columna): array
    {
        $conteo = array_count_values(array_column($nuevas, $columna));
        ksort($conteo);

        return $conteo;
    }

    /**
     * @param array<int, int> $valores
     * @return list<int>
     */
    private static function ordenados(array $valores): array
    {
        sort($valores);

        return $valores;
    }

    /**
     * @return array<string, array{int, int, int}> alumnos, talleres, franjas distintas
     */
    public static function escenarios(): array
    {
        return [
            'más talleres que alumnos' => [4, 13, 13],
            'más alumnos que talleres' => [13, 4, 4],
            'iguales' => [6, 6, 6],
            'un alumno' => [1, 5, 5],
            'un taller' => [5, 1, 1],
            'talleres en pocas franjas' => [5, 12, 3],
        ];
    }

    #[DataProvider('escenarios')]
    public function testCubreTodoYRepartePareoSinChoques(int $nAlumnos, int $nTalleres, int $nFranjas): void
    {
        $alumnos = range(1, $nAlumnos);
        $talleres = array_map(
            static fn (int $i): array => ['id' => 100 + $i, 'franja' => 'f' . ($i % $nFranjas)],
            range(1, $nTalleres)
        );

        foreach (range(1, 25) as $semilla) {
            $r = Sorteo::repartir(
                self::talleres($talleres),
                self::alumnos($alumnos),
                [],
                new Randomizer(new Mt19937($semilla))
            );

            $carga = array_count_values(array_column($r['nuevas'], 'alumno_id'));
            $auditores = array_count_values(array_column($r['nuevas'], 'actividad_id'));
            $this->assertSame([], $r['alumnosSinTaller']);
            $this->assertCount($nAlumnos, $carga, 'Cada alumno recibe al menos un taller');

            // Un taller solo queda sin cubrir si en su franja hay más talleres que alumnos.
            $porFranja = array_count_values(array_column($talleres, 'franja'));
            $esperadosSinCubrir = array_sum(array_map(static fn (int $n): int => max(0, $n - $nAlumnos), $porFranja));
            $this->assertCount($esperadosSinCubrir, $r['talleresSinCubrir']);
            $this->assertCount($nTalleres - $esperadosSinCubrir, $auditores);

            if ($nTalleres >= $nAlumnos) {
                $this->assertSame([1], array_values(array_unique($auditores)), 'Un auditor por taller basta');
                if ($esperadosSinCubrir === 0) {
                    $this->assertLessThanOrEqual(1, max($carga) - min($carga));
                }
            } else {
                $this->assertSame([1], array_values(array_unique($carga)), 'Un taller por alumno basta');
                $this->assertLessThanOrEqual(1, max($auditores) - min($auditores));
            }

            $franjaDe = array_column($talleres, 'franja', 'id');
            $ocupadas = [];
            foreach ($r['nuevas'] as $n) {
                $clave = $n['alumno_id'] . '|' . $franjaDe[$n['actividad_id']];
                $this->assertArrayNotHasKey($clave, $ocupadas, 'Nadie queda en dos talleres a la misma hora');
                $ocupadas[$clave] = true;
            }
        }
    }

    public function testGarantiasConCombinacionesAlAzar(): void
    {
        $generador = new Randomizer(new Mt19937(2026));
        foreach (range(1, 300) as $vuelta) {
            $nAlumnos = $generador->getInt(1, 15);
            $nTalleres = $generador->getInt(1, 30);
            $nFranjas = $generador->getInt(1, 8);
            $talleres = array_map(
                static fn (int $i): array => ['id' => $i, 'franja' => 'f' . $generador->getInt(1, $nFranjas)],
                range(1, $nTalleres)
            );

            $r = Sorteo::repartir(
                self::talleres($talleres),
                self::alumnos(range(1, $nAlumnos)),
                [],
                new Randomizer(new Mt19937($vuelta))
            );

            $carga = array_count_values(array_column($r['nuevas'], 'alumno_id'));
            $auditores = array_count_values(array_column($r['nuevas'], 'actividad_id'));
            $porFranja = array_count_values(array_column($talleres, 'franja'));
            $sinCubrir = array_sum(array_map(static fn (int $n): int => max(0, $n - $nAlumnos), $porFranja));
            $caso = "vuelta {$vuelta}: {$nAlumnos} alumnos, {$nTalleres} talleres, {$nFranjas} franjas";

            $this->assertCount($nAlumnos, $carga, $caso);
            $this->assertCount($sinCubrir, $r['talleresSinCubrir'], $caso);
            $this->assertLessThanOrEqual(1, max($carga) - min($carga), $caso);
            $this->assertLessThanOrEqual(1, max($auditores) - min($auditores), $caso);
        }
    }

    public function testRespetaLasAsignacionesActualesYSuCarga(): void
    {
        $talleres = [['id' => 1, 'franja' => 'a'], ['id' => 2, 'franja' => 'b'], ['id' => 3, 'franja' => 'c']];
        // El alumno 10 ya audita el taller 1 y otro ya realizado (que no se reparte) en la franja c.
        $actuales = [
            ['alumno_id' => 10, 'actividad_id' => 1, 'franja' => 'a'],
            ['alumno_id' => 10, 'actividad_id' => 99, 'franja' => 'c'],
        ];

        $r = Sorteo::repartir(self::talleres($talleres), self::alumnos([10, 20]), $actuales, new Randomizer(new Mt19937(1)));

        // Los talleres 2 y 3 están vacíos; el alumno 20 (sin carga) recibe ambos antes que el 10.
        $this->assertEqualsCanonicalizing(
            [['alumno_id' => 20, 'actividad_id' => 2], ['alumno_id' => 20, 'actividad_id' => 3]],
            $r['nuevas']
        );
    }

    public function testElAzarCambiaElRepartoPeroNoSusGarantias(): void
    {
        $talleres = array_map(static fn (int $i): array => ['id' => $i, 'franja' => "f{$i}"], range(1, 6));
        $repartos = [];
        foreach (range(1, 10) as $semilla) {
            $r = Sorteo::repartir(
                self::talleres($talleres),
                self::alumnos([1, 2, 3, 4, 5, 6]),
                [],
                new Randomizer(new Mt19937($semilla))
            );
            $repartos[json_encode($r['nuevas'])] = true;
        }

        $this->assertGreaterThan(1, count($repartos), 'Distintas semillas dan repartos distintos');
    }

    public function testSinTalleresTodosLosAlumnosQuedanSinTaller(): void
    {
        $r = Sorteo::repartir([], self::alumnos([1, 2]), [], new Randomizer(new Mt19937(1)));

        $this->assertSame([], $r['nuevas']);
        $this->assertEqualsCanonicalizing([1, 2], $r['alumnosSinTaller']);
    }

    public function testCadaAlumnoSoloRecibeTalleresDentroDeSuHorario(): void
    {
        // Tres matutinos, tres vespertinos y uno de todo el día. Cinco talleres a las 10:00, cinco a las
        // 15:00 y uno a las 20:00, fuera de todos los horarios.
        $alumnos = array_merge(
            self::alumnos([1, 2, 3], self::MATUTINO),
            self::alumnos([4, 5, 6], self::VESPERTINO),
            self::alumnos([7])
        );
        $horario = array_column($alumnos, null, 'id');
        $talleres = array_merge(
            array_map(static fn (int $i): array => ['id' => $i, 'franja' => 'diez', 'inicio' => '10:00:00', 'fin' => '11:00:00'], range(1, 5)),
            array_map(static fn (int $i): array => ['id' => $i, 'franja' => 'tres', 'inicio' => '15:00:00', 'fin' => '16:00:00'], range(6, 10)),
            [['id' => 11, 'franja' => 'noche', 'inicio' => '20:00:00', 'fin' => '21:00:00']]
        );
        $tallerDe = array_column($talleres, null, 'id');

        foreach (range(1, 25) as $semilla) {
            $r = Sorteo::repartir($talleres, $alumnos, [], new Randomizer(new Mt19937($semilla)));

            foreach ($r['nuevas'] as $n) {
                $t = $tallerDe[$n['actividad_id']];
                $h = $horario[$n['alumno_id']];
                $this->assertTrue(
                    $h['inicio'] <= $t['inicio'] && $t['fin'] <= $h['fin'],
                    "Alumno {$n['alumno_id']} fuera de su horario en el taller {$n['actividad_id']}"
                );
            }
            // A cada hora caben cuatro alumnos (tres de ese turno y el de todo el día): de cada bloque de
            // cinco queda uno sin cubrir, y el de las 20:00 no lo puede auditar nadie.
            $this->assertCount(3, $r['talleresSinCubrir']);
            $this->assertContains(11, $r['talleresSinCubrir']);
            $this->assertSame([], $r['alumnosSinTaller']);
            $carga = array_count_values(array_column($r['nuevas'], 'alumno_id'));
            $this->assertSame(2, $carga[7], 'El de todo el día cubre una franja de cada turno');
        }
    }

    public function testUnAlumnoSinTalleresEnSuHorarioQuedaSinTaller(): void
    {
        $talleres = [['id' => 1, 'franja' => 'a', 'inicio' => '10:00:00', 'fin' => '11:00:00']];
        $alumnos = array_merge(self::alumnos([1], self::MATUTINO), self::alumnos([2], self::VESPERTINO));

        $r = Sorteo::repartir($talleres, $alumnos, [], new Randomizer(new Mt19937(1)));

        $this->assertSame([['alumno_id' => 1, 'actividad_id' => 1]], $r['nuevas']);
        $this->assertSame([2], $r['alumnosSinTaller']);
        $this->assertSame([], $r['talleresSinCubrir']);
    }

    public function testElHorarioIncluyeSusLimites(): void
    {
        // El matutino termina a las 14:00 y el vespertino empieza a las 13:00: el taller de 13:00 a 14:00
        // cabe en los dos; el de 14:00 a 15:00 ya no es matutino y el de 12:00 a 13:00 no es vespertino.
        $talleres = [
            ['id' => 1, 'franja' => 'a', 'inicio' => '12:00:00', 'fin' => '13:00:00'],
            ['id' => 2, 'franja' => 'b', 'inicio' => '13:00:00', 'fin' => '14:00:00'],
            ['id' => 3, 'franja' => 'c', 'inicio' => '14:00:00', 'fin' => '15:00:00'],
        ];

        $matutino = Sorteo::repartir($talleres, self::alumnos([1], self::MATUTINO), [], new Randomizer(new Mt19937(1)), 1);
        $this->assertEqualsCanonicalizing(
            [['alumno_id' => 1, 'actividad_id' => 1], ['alumno_id' => 1, 'actividad_id' => 2]],
            $matutino['nuevas']
        );
        $this->assertSame([3], $matutino['talleresSinCubrir']);

        $vespertino = Sorteo::repartir($talleres, self::alumnos([2], self::VESPERTINO), [], new Randomizer(new Mt19937(1)), 1);
        $this->assertEqualsCanonicalizing(
            [['alumno_id' => 2, 'actividad_id' => 2], ['alumno_id' => 2, 'actividad_id' => 3]],
            $vespertino['nuevas']
        );
        $this->assertSame([1], $vespertino['talleresSinCubrir']);
    }

    public function testConHorariosAlAzarCubreTodoLoQueSePuede(): void
    {
        // Grupos con su horario fijo, como Taller 1 … 6; la franja es el grupo.
        $grupos = [
            1 => ['10:00:00', '11:00:00'], 2 => ['11:00:00', '12:00:00'], 3 => ['12:00:00', '13:00:00'],
            4 => ['13:00:00', '14:00:00'], 5 => ['15:00:00', '16:00:00'], 6 => ['16:00:00', '17:00:00'],
        ];
        $turnos = [self::MATUTINO, self::VESPERTINO, self::TODO_EL_DIA];
        $generador = new Randomizer(new Mt19937(15));
        foreach (range(1, 200) as $vuelta) {
            $alumnos = array_map(
                static fn (int $id): array => self::alumnos([$id], $turnos[$generador->getInt(0, 2)])[0],
                range(1, $generador->getInt(1, 12))
            );
            $talleres = array_map(static function (int $id) use ($generador, $grupos): array {
                $g = $generador->getInt(1, 6);

                return ['id' => $id, 'franja' => "g{$g}", 'inicio' => $grupos[$g][0], 'fin' => $grupos[$g][1]];
            }, range(1, $generador->getInt(1, 30)));
            $caso = 'vuelta ' . $vuelta;

            $r = Sorteo::repartir($talleres, $alumnos, [], new Randomizer(new Mt19937($vuelta)));

            $tallerDe = array_column($talleres, null, 'id');
            $horario = array_column($alumnos, null, 'id');
            $cabe = static fn (array $h, array $t): bool => $h['inicio'] <= $t['inicio'] && $t['fin'] <= $h['fin'];
            $ocupadas = [];
            foreach ($r['nuevas'] as $n) {
                $t = $tallerDe[$n['actividad_id']];
                $this->assertTrue($cabe($horario[$n['alumno_id']], $t), $caso);
                $clave = $n['alumno_id'] . '|' . $t['franja'];
                $this->assertArrayNotHasKey($clave, $ocupadas, $caso);
                $ocupadas[$clave] = true;
            }
            // En cada franja se cubren tantos talleres como alumnos puedan auditarla.
            $sinCubrir = 0;
            foreach (array_count_values(array_column($talleres, 'franja')) as $franja => $n) {
                $muestra = $tallerDe[array_search($franja, array_column($talleres, 'franja', 'id'), true)];
                $pueden = count(array_filter($alumnos, static fn (array $h): bool => $cabe($h, $muestra)));
                $sinCubrir += max(0, $n - $pueden);
            }
            $this->assertCount($sinCubrir, $r['talleresSinCubrir'], $caso);
            // Solo queda sin taller quien no tiene ningún taller dentro de su horario.
            $sinTaller = array_values(array_filter(
                array_column($alumnos, 'id'),
                static fn (int $id): bool => array_filter($talleres, static fn (array $t): bool => $cabe($horario[$id], $t)) === []
            ));
            $this->assertEqualsCanonicalizing($sinTaller, $r['alumnosSinTaller'], $caso);
        }
    }

    public function testConAlumnosDeSobraCadaTallerRecibeLosAuditoresPedidos(): void
    {
        // Nueve talleres en tres franjas y diez alumnos: tres por taller caben (nueve por franja).
        $talleres = array_map(static fn (int $i): array => ['id' => $i, 'franja' => 'f' . ($i % 3)], range(1, 9));

        foreach (range(1, 25) as $semilla) {
            $r = Sorteo::repartir(self::talleres($talleres), self::alumnos(range(1, 10)), [], new Randomizer(new Mt19937($semilla)), 3);

            $auditores = self::conteo($r['nuevas'], 'actividad_id');
            $this->assertSame(array_fill_keys(range(1, 9), 3), $auditores);
            $this->assertSame([], $r['talleresSinCubrir']);
            $this->assertSame([], $r['talleresIncompletos']);
            $this->assertSame([], $r['alumnosSinTaller']);
            // 27 lugares entre 10 alumnos: 2 o 3 cada uno.
            $carga = self::conteo($r['nuevas'], 'alumno_id');
            $this->assertCount(10, $carga);
            $this->assertLessThanOrEqual(1, max($carga) - min($carga));
        }
    }

    public function testSiNoAlcanzanTodosRecibenUnoAntesDelSegundo(): void
    {
        // Cinco talleres a la misma hora y siete alumnos, con dos por taller: todos reciben uno y solo
        // dos reciben el segundo.
        $talleres = array_map(static fn (int $i): array => ['id' => $i, 'franja' => 'a'], range(1, 5));

        foreach (range(1, 25) as $semilla) {
            $r = Sorteo::repartir(self::talleres($talleres), self::alumnos(range(1, 7)), [], new Randomizer(new Mt19937($semilla)), 2);

            $auditores = self::conteo($r['nuevas'], 'actividad_id');
            $this->assertCount(5, $auditores);
            $this->assertSame([1, 1, 1, 2, 2], self::ordenados($auditores));
            $this->assertSame([], $r['talleresSinCubrir']);
            $this->assertCount(3, $r['talleresIncompletos']);
            $this->assertEqualsCanonicalizing(
                array_keys(array_filter($auditores, static fn (int $n): bool => $n === 1)),
                $r['talleresIncompletos']
            );
        }
    }

    public function testSoloCompletaLosAuditoresQueFaltan(): void
    {
        // El taller 1 ya tiene dos auditores, el 2 uno y el 3 ninguno; se piden dos por taller.
        $talleres = [['id' => 1, 'franja' => 'a'], ['id' => 2, 'franja' => 'b'], ['id' => 3, 'franja' => 'c']];
        $actuales = [
            ['alumno_id' => 1, 'actividad_id' => 1, 'franja' => 'a'],
            ['alumno_id' => 2, 'actividad_id' => 1, 'franja' => 'a'],
            ['alumno_id' => 3, 'actividad_id' => 2, 'franja' => 'b'],
        ];

        $r = Sorteo::repartir(self::talleres($talleres), self::alumnos(range(1, 4)), $actuales, new Randomizer(new Mt19937(3)), 2);

        $auditores = self::conteo($r['nuevas'], 'actividad_id');
        $this->assertSame([2 => 1, 3 => 2], $auditores);
        $this->assertArrayNotHasKey(1, $auditores, 'El taller lleno no recibe más');
        $this->assertSame([], $r['talleresIncompletos']);
        // El alumno 4 no tenía taller: recibe uno de los que faltaban.
        $this->assertContains(4, array_column($r['nuevas'], 'alumno_id'));
    }

    public function testConVariosAuditoresYHorariosAlAzarLlenaTodoLoQueSePuede(): void
    {
        $grupos = [
            1 => ['10:00:00', '11:00:00'], 2 => ['11:00:00', '12:00:00'], 3 => ['12:00:00', '13:00:00'],
            4 => ['13:00:00', '14:00:00'], 5 => ['15:00:00', '16:00:00'], 6 => ['16:00:00', '17:00:00'],
        ];
        $turnos = [self::MATUTINO, self::VESPERTINO, self::TODO_EL_DIA];
        $generador = new Randomizer(new Mt19937(42));
        foreach (range(1, 200) as $vuelta) {
            $pedidos = $generador->getInt(1, 4);
            $alumnos = array_map(
                static fn (int $id): array => self::alumnos([$id], $turnos[$generador->getInt(0, 2)])[0],
                range(1, $generador->getInt(1, 20))
            );
            $talleres = array_map(static function (int $id) use ($generador, $grupos): array {
                $g = $generador->getInt(1, 6);

                return ['id' => $id, 'franja' => "g{$g}", 'inicio' => $grupos[$g][0], 'fin' => $grupos[$g][1]];
            }, range(1, $generador->getInt(1, 25)));
            $caso = "vuelta {$vuelta}: {$pedidos} por taller";

            $r = Sorteo::repartir($talleres, $alumnos, [], new Randomizer(new Mt19937($vuelta)), $pedidos);

            $tallerDe = array_column($talleres, null, 'id');
            $horario = array_column($alumnos, null, 'id');
            $cabe = static fn (array $h, array $t): bool => $h['inicio'] <= $t['inicio'] && $t['fin'] <= $h['fin'];
            $ocupadas = [];
            foreach ($r['nuevas'] as $n) {
                $t = $tallerDe[$n['actividad_id']];
                $this->assertTrue($cabe($horario[$n['alumno_id']], $t), $caso);
                $clave = $n['alumno_id'] . '|' . $t['franja'];
                $this->assertArrayNotHasKey($clave, $ocupadas, $caso);
                $ocupadas[$clave] = true;
            }
            $auditores = array_count_values(array_column($r['nuevas'], 'actividad_id')) + array_fill_keys(array_keys($tallerDe), 0);
            foreach (array_unique(array_column($talleres, 'franja')) as $franja) {
                $deLaFranja = array_filter($talleres, static fn (array $t): bool => $t['franja'] === $franja);
                $cuantos = array_map(static fn (array $t): int => $auditores[$t['id']], $deLaFranja);
                $muestra = reset($deLaFranja);
                $pueden = count(array_filter($alumnos, static fn (array $h): bool => $cabe($h, $muestra)));
                // Se usan todos los lugares posibles: los pedidos o, si no alcanzan, todos los alumnos que pueden.
                $this->assertGreaterThanOrEqual(min($pedidos * count($deLaFranja), $pueden), array_sum($cuantos), $caso);
                // Mientras falten auditores, ninguno de la franja tiene dos más que otro.
                if (min($cuantos) < $pedidos) {
                    $this->assertLessThanOrEqual(1, max($cuantos) - min($cuantos), $caso);
                }
            }
            $this->assertEqualsCanonicalizing(
                array_keys(array_filter($auditores, static fn (int $n): bool => $n === 0)),
                $r['talleresSinCubrir'],
                $caso
            );
            $this->assertEqualsCanonicalizing(
                array_keys(array_filter($auditores, static fn (int $n): bool => $n > 0 && $n < $pedidos)),
                $r['talleresIncompletos'],
                $caso
            );
        }
    }

    public function testLaFranjaEsLaFechaYElGrupo(): void
    {
        $this->assertSame('2026-10-15|3', Sorteo::franja('2026-10-15', 3));
        $this->assertSame(Sorteo::franja('2026-10-15', 3), Sorteo::franja('2026-10-15 00:00:00', 3));
        $this->assertNotSame(Sorteo::franja('2026-10-15', 3), Sorteo::franja('2026-10-16', 3));
    }
}
