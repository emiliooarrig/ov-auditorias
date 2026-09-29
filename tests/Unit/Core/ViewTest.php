<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpException;
use App\Core\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestApp;

final class ViewTest extends TestCase
{
    public function testEEscapaHtmlYComillas(): void
    {
        $this->assertSame('&lt;script&gt;alert(&apos;x&apos;)&lt;/script&gt;', \e("<script>alert('x')</script>"));
        $this->assertSame('&quot;Taller&quot; &amp; más', \e('"Taller" & más'));
        $this->assertSame('', \e(null));
        $this->assertSame('', \e(['a']));
        $this->assertSame('3', \e(3));
    }

    public function testLaPaginaDeErrorEscapaElDetalle(): void
    {
        $html = TestApp::make()->errorResponse(403, '<b>x</b>')->body();

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    public function testNombreDeVistaInvalidoSeRechaza(): void
    {
        $this->expectException(RuntimeException::class);
        TestApp::make()->view()->render('../composer');
    }

    public function testRutaInexistenteRespondeConPagina404YCabecerasDeSeguridad(): void
    {
        $app = TestApp::make();
        $response = $app->handle(new Request('GET', '/no-existe'));

        $this->assertSame(404, $response->status());
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertStringContainsString("default-src 'self'", (string) $response->header('Content-Security-Policy'));
    }

    public function testHttpExceptionConservaSuCodigo(): void
    {
        $this->assertSame(404, (new HttpException(404))->status);
    }
}
