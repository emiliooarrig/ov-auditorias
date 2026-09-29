<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Router;
use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Tests\Support\EcoController;
use Tests\Support\TestApp;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(TestApp::make());
        $this->router->middleware([CsrfMiddleware::class]);
        $this->router->get('/actividades/nueva', [EcoController::class, 'nueva']);
        $this->router->get('/actividades/{id:\d+}', [EcoController::class, 'ver']);
        $this->router->post('/actividades', [EcoController::class, 'guardar']);
    }

    public function testRutaFijaNoChocaConRutaConId(): void
    {
        $this->assertSame('formulario nuevo', $this->router->dispatch(new Request('GET', '/actividades/nueva'))->body());
        $this->assertSame('actividad 42', $this->router->dispatch(new Request('GET', '/actividades/42'))->body());
    }

    public function testIdNoNumericoDa404(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->router->dispatch(new Request('GET', '/actividades/abc'));
    }

    public function testMetodoNoPermitidoDa405ConCabeceraAllow(): void
    {
        try {
            $this->router->dispatch(new Request('POST', '/actividades/nueva'));
            $this->fail('Se esperaba 405');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->status);
            $this->assertSame('GET', $e->headers['Allow']);
        }
    }

    public function testPostSinTokenCsrfEsRechazado(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $this->router->dispatch(new Request('POST', '/actividades'));
    }

    public function testPostConTokenCsrfValidoPasa(): void
    {
        $token = \app()->csrf()->token();
        $response = $this->router->dispatch(new Request('POST', '/actividades', [], ['_csrf' => $token]));

        $this->assertSame('guardado', $response->body());
    }

    public function testMiddlewareDeGrupoSoloAplicaDentroDelGrupo(): void
    {
        $router = new Router(TestApp::make());
        $router->group([[BloqueaMiddleware::class, 'bloqueado']], function (Router $r): void {
            $r->get('/privado', [EcoController::class, 'nueva']);
        });
        $router->get('/publico', [EcoController::class, 'nueva']);

        $this->assertSame('bloqueado', $router->dispatch(new Request('GET', '/privado'))->body());
        $this->assertSame('formulario nuevo', $router->dispatch(new Request('GET', '/publico'))->body());
    }
}
