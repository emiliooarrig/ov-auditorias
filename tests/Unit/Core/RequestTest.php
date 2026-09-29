<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function rutas(): array
    {
        return [
            'raíz' => ['/', '', '/'],
            'diagonal final' => ['/mis-talleres/', '', '/mis-talleres'],
            'diagonales dobles' => ['//actividades//3', '', '/actividades/3'],
            'subcarpeta' => ['/auditorias/actividades', '/auditorias', '/actividades'],
            'raíz de subcarpeta' => ['/auditorias', '/auditorias', '/'],
            'prefijo parecido no se quita' => ['/auditorias2/x', '/auditorias', '/auditorias2/x'],
        ];
    }

    #[DataProvider('rutas')]
    public function testNormalizaLaRuta(string $ruta, string $subcarpeta, string $esperada): void
    {
        $this->assertSame($esperada, Request::normalizePath($ruta, $subcarpeta));
    }

    public function testLosCamposDeTextoSeRecortanYNoAceptanArreglos(): void
    {
        $request = new Request('GET', '/', ['nombre' => '  Taller  ', 'carrera' => ['1']], ['correo' => ' a@anahuac.mx ']);

        $this->assertSame('Taller', $request->queryString('nombre'));
        $this->assertSame('', $request->queryString('carrera'));
        $this->assertSame('', $request->queryString('edificio'));
        $this->assertSame('a@anahuac.mx', $request->inputString('correo'));
    }

    public function testIpInvalidaSeReemplaza(): void
    {
        $this->assertSame('0.0.0.0', (new Request('GET', '/', [], [], ['REMOTE_ADDR' => 'basura']))->ip());
        $this->assertSame('10.0.0.5', (new Request('GET', '/', [], [], ['REMOTE_ADDR' => '10.0.0.5']))->ip());
    }
}
