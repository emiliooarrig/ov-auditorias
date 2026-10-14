<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Alumno;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lectura de una lista pegada: un alumno por renglón, en columnas de Excel o separado por comas.
 */
final class AlumnoListaTest extends TestCase
{
    /**
     * @return array<string, array{string, string, array{nombre: string, apellidos: string}}>
     */
    public static function renglones(): array
    {
        return [
            'coma, apellidos primero' => ['López Ruiz, Ana', 'apellidos', ['nombre' => 'Ana', 'apellidos' => 'López Ruiz']],
            'coma, nombre primero' => ['Ana María, López', 'nombre', ['nombre' => 'Ana María', 'apellidos' => 'López']],
            'dos columnas' => ["López Ruiz\tAna", 'apellidos', ['nombre' => 'Ana', 'apellidos' => 'López Ruiz']],
            'tres columnas, apellidos primero' => ["López\tRuiz\tAna", 'apellidos', ['nombre' => 'Ana', 'apellidos' => 'López Ruiz']],
            'tres columnas, nombre primero' => ["Ana\tLópez\tRuiz", 'nombre', ['nombre' => 'Ana', 'apellidos' => 'López Ruiz']],
            'espacios de más' => ["  López   Ruiz ,\t Ana  ", 'apellidos', ['nombre' => 'Ana', 'apellidos' => 'López Ruiz']],
            'columna vacía en medio' => ["López\t\tAna", 'apellidos', ['nombre' => 'Ana', 'apellidos' => 'López']],
        ];
    }

    /**
     * @param array{nombre: string, apellidos: string} $esperado
     */
    #[DataProvider('renglones')]
    public function testInterpretaCadaFormato(string $renglon, string $orden, array $esperado): void
    {
        $this->assertSame(['alumnos' => [$esperado], 'errores' => []], Alumno::interpretarLista($renglon, $orden));
    }

    public function testIgnoraRenglonesVaciosYElEncabezado(): void
    {
        $lista = "Nombre\tApellidos\r\n\r\nAna\tLópez\n   \nLuis\tPérez\n";

        $resultado = Alumno::interpretarLista($lista, 'nombre');

        $this->assertSame([
            ['nombre' => 'Ana', 'apellidos' => 'López'],
            ['nombre' => 'Luis', 'apellidos' => 'Pérez'],
        ], $resultado['alumnos']);
        $this->assertSame([], $resultado['errores']);
    }

    public function testSoloElPrimerRenglonPuedeSerEncabezado(): void
    {
        $resultado = Alumno::interpretarLista("Ana, López\nNombre, Apellidos", 'nombre');

        $this->assertCount(2, $resultado['alumnos']);
    }

    public function testReportaLosRenglonesQueNoSePuedenLeerConSuNumero(): void
    {
        $resultado = Alumno::interpretarLista("López, Ana\nAna López\n, Ana\nPérez, Luis3", 'apellidos');

        $this->assertSame([['nombre' => 'Ana', 'apellidos' => 'López']], $resultado['alumnos']);
        $this->assertSame([2, 3, 4], array_column($resultado['errores'], 'renglon'));
        $this->assertSame(['Ana López', ', Ana', 'Pérez, Luis3'], array_column($resultado['errores'], 'texto'));
        $this->assertStringContainsString('Separa el nombre de los apellidos', $resultado['errores'][0]['error']);
        $this->assertSame('Falta el nombre o los apellidos.', $resultado['errores'][1]['error']);
        $this->assertStringStartsWith('Nombre: Solo se permiten letras', $resultado['errores'][2]['error']);
    }
}
