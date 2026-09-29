<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;

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
