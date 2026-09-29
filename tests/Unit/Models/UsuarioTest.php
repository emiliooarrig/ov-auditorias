<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Core\Password;
use App\Models\Usuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsuarioTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function correos(): array
    {
        return [
            'institucional' => ['ana.lopez@anahuac.mx', true],
            'otro dominio' => ['ana@gmail.com', false],
            'subdominio' => ['ana@alumnos.anahuac.mx', false],
            'dominio como sufijo' => ['ana@falsoanahuac.mx', false],
            'sin arroba' => ['anahuac.mx', false],
            'vacío' => ['', false],
            'dos arrobas' => ['a@b@anahuac.mx', false],
        ];
    }

    #[DataProvider('correos')]
    public function testSoloAceptaCorreosDelDominioPermitido(string $correo, bool $esperado): void
    {
        $this->assertSame($esperado, Usuario::correoPermitido($correo, ['anahuac.mx']));
    }

    public function testNormalizaCorreoYNombre(): void
    {
        $this->assertSame('ana.lopez@anahuac.mx', Usuario::normalizarCorreo('  Ana.Lopez@ANAHUAC.MX '));
        $this->assertSame('María José', Usuario::normalizarNombre("  María \t José "));
    }

    public function testValidaNombres(): void
    {
        $this->assertNull(Usuario::validarNombre("D'Artagnan de la Peña-Núñez", 120));
        $this->assertNotNull(Usuario::validarNombre('', 80));
        $this->assertNotNull(Usuario::validarNombre('<b>Ana</b>', 80));
        $this->assertNotNull(Usuario::validarNombre('Ana1', 80));
        $this->assertNotNull(Usuario::validarNombre(str_repeat('a', 81), 80));
    }

    public function testPoliticaDeContrasena(): void
    {
        $this->assertNotNull(Password::validate('corta', 'corta'));
        $this->assertNotNull(Password::validate('suficientemente-larga', 'otra-distinta-xx'));
        $this->assertNull(Password::validate('suficientemente-larga', 'suficientemente-larga'));
    }

    public function testHashYVerificacion(): void
    {
        $hash = Password::hash('Clave-segura-2026');

        $this->assertNotSame('Clave-segura-2026', $hash);
        $this->assertTrue(Password::verify('Clave-segura-2026', $hash));
        $this->assertFalse(Password::verify('otra', $hash));
        $this->assertFalse(Password::verify('Clave-segura-2026', null));
        $this->assertFalse(Password::needsRehash($hash));
    }
}
