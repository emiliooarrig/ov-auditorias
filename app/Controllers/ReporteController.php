<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Pdf;
use App\Core\Request;
use App\Core\Response;
use App\Models\Actividad;
use DateTimeImmutable;

/**
 * Reportes descargables.
 */
final class ReporteController extends Controller
{
    private const TITULO = 'Reporte de talleres y auditores';

    /**
     * PDF con todos los talleres activos, toda su información y los alumnos que los auditan, en una tabla
     * horizontal (carta apaisada). No depende de los filtros del panel: siempre incluye todos.
     */
    public function talleres(Request $request): Response
    {
        $ahora = new DateTimeImmutable();
        $generado = fecha_corta($ahora->format('Y-m-d')) . ', ' . $ahora->format('H:i');
        $usuario = $this->app->auth()->user();
        $generadoPor = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));

        $html = $this->app->view()->render('reportes/talleres', [
            'talleres' => (new Actividad($this->db()))->reporte(),
            'titulo' => self::TITULO,
            'generado' => $generado,
            'generadoPor' => $generadoPor,
        ], null);
        $pdf = Pdf::desdeHtml(
            $html,
            self::TITULO,
            (string) $this->app->config('name', 'Auditores de Talleres'),
            self::TITULO . ' · Generado el ' . $generado,
            $this->app->basePath('storage/cache/dompdf')
        );

        $this->app->logger()->info('Reporte de talleres descargado', ['por' => $this->adminId()]);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte-talleres-' . $ahora->format('Y-m-d') . '.pdf"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }
}
