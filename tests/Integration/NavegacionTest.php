<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Encabezado: botón de menú para celulares y separación de "Cerrar sesión".
 */
final class NavegacionTest extends IntegrationTestCase
{
    public function testConSesionHayBotonDeMenuQueControlaElPanel(): void
    {
        $this->entrarComoAuditor();
        $html = $this->get('/mis-talleres')->body();

        $this->assertStringContainsString('aria-controls="menu-principal" aria-expanded="false"', $html);
        $this->assertStringContainsString('id="menu-principal"', $html);
        $this->assertStringContainsString('class="session__logout"', $html);
    }

    public function testLaMarcaEsElLogoOvConNombreAccesible(): void
    {
        $html = $this->get('/login')->body();

        $this->assertStringContainsString('<svg class="logo"', $html);
        $this->assertMatchesRegularExpression('#<text class="logo__letras"[^>]*>OV</text>#', $html);
        $this->assertStringContainsString('<span class="sr-only">Pruebas, Universidad Anáhuac. Ir al inicio</span>', $html);
        $this->assertStringNotContainsString('brand__org', $html);
    }

    public function testSinSesionNoHayMenu(): void
    {
        $html = $this->get('/login')->body();

        $this->assertStringNotContainsString('menu-toggle', $html);
        $this->assertStringNotContainsString('menu-principal', $html);
    }
}
