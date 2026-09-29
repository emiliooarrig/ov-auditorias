<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Actividad;
use App\Models\Carrera;
use App\Models\Edificio;

/**
 * Base de los controladores: acceso a servicios y atajos para responder.
 */
abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->app->view()->render($template, $data), $status);
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function redirect(string $path, array $query = []): Response
    {
        return Response::redirect($this->app->url($path, $query));
    }

    /**
     * Listado de talleres con los filtros GET nombre, carrera, edificio y pagina (CU-03).
     * Con $usuarioSesion se limita a los talleres asignados a ese usuario.
     *
     * @return array{
     *     filtros: array{nombre: string, carrera: ?int, edificio: ?int},
     *     hayFiltros: bool,
     *     resultado: array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int},
     *     carreras: list<array{id: int, nombre: string}>,
     *     edificios: list<array{id: int, numero: int}>
     * }
     */
    protected function listadoTalleres(Request $request, ?int $usuarioSesion): array
    {
        $filtros = [
            'nombre' => mb_substr($request->queryString('nombre'), 0, 150),
            'carrera' => $this->idOpcional($request->queryString('carrera')),
            'edificio' => $this->idOpcional($request->queryString('edificio')),
        ];

        $resultado = (new Actividad($this->db()))->filtrar(
            [
                'nombre' => $filtros['nombre'],
                'carrera_id' => $filtros['carrera'],
                'edificio_id' => $filtros['edificio'],
            ],
            $usuarioSesion,
            $this->idOpcional($request->queryString('pagina')) ?? 1,
            (int) $this->app->config('pagination.per_page', 20)
        );

        return [
            'filtros' => $filtros,
            'hayFiltros' => $filtros['nombre'] !== '' || $filtros['carrera'] !== null || $filtros['edificio'] !== null,
            'resultado' => $resultado,
            'carreras' => (new Carrera($this->db()))->activas(),
            'edificios' => (new Edificio($this->db()))->activos(),
        ];
    }

    /**
     * Entero positivo a partir de texto; null si no lo es.
     */
    protected function idOpcional(string $valor): ?int
    {
        return ctype_digit($valor) && (int) $valor > 0 ? (int) $valor : null;
    }

    protected function notFound(): never
    {
        throw new HttpException(404);
    }

    protected function db(): Database
    {
        return $this->app->db();
    }

    protected function session(): Session
    {
        return $this->app->session();
    }
}
