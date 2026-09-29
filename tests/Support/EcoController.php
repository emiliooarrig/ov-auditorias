<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Controlador de prueba que responde con los datos que recibió.
 */
final class EcoController extends Controller
{
    public function ver(Request $request): Response
    {
        return new Response('actividad ' . $request->intParam('id'));
    }

    public function nueva(Request $request): Response
    {
        return new Response('formulario nuevo');
    }

    public function guardar(Request $request): Response
    {
        return new Response('guardado');
    }
}
