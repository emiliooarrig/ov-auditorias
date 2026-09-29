<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Csrf;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApp;

final class CsrfTest extends TestCase
{
    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->csrf = TestApp::make()->csrf();
    }

    public function testElTokenEsEstableDuranteLaSesion(): void
    {
        $token = $this->csrf->token();

        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $this->csrf->token());
    }

    public function testValidaSoloElTokenCorrecto(): void
    {
        $token = $this->csrf->token();

        $this->assertTrue($this->csrf->validate($token));
        $this->assertFalse($this->csrf->validate('otro'));
        $this->assertFalse($this->csrf->validate(''));
        $this->assertFalse($this->csrf->validate(null));
        $this->assertFalse($this->csrf->validate([$token]));
    }

    public function testSinTokenEnSesionNadaEsValido(): void
    {
        $this->assertFalse($this->csrf->validate(''));
    }

    public function testRotarInvalidaElTokenAnterior(): void
    {
        $anterior = $this->csrf->token();
        $this->csrf->rotate();

        $this->assertFalse($this->csrf->validate($anterior));
        $this->assertNotSame($anterior, $this->csrf->token());
    }
}
