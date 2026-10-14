<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Pdf;
use App\Core\Request;
use App\Core\Response;
use App\Models\Actividad;
use App\Models\Alumno;

/**
 * Reportes descargables. Se reparten a los alumnos, así que no dicen quién los generó ni cuándo.
 */
final class ReporteController extends Controller
{
    private const TITULO_TALLERES = 'Reporte de talleres y auditores';
    private const TITULO_ALUMNOS = 'Directorio de alumnos auditores';

    /**
     * PDF con todos los talleres activos, toda su información y los alumnos que los auditan, en una tabla
     * horizontal (carta apaisada). No depende de los filtros del panel: siempre incluye todos.
     */
    public function talleres(Request $request): Response
    {
        $html = $this->app->view()->render('reportes/talleres', [
            'talleres' => (new Actividad($this->db()))->reporte(),
            'titulo' => self::TITULO_TALLERES,
        ], null);
        $this->app->logger()->info('Reporte de talleres descargado', ['por' => $this->adminId()]);

        return $this->pdf($html, self::TITULO_TALLERES, 'reporte-talleres.pdf', 'landscape');
    }

    /**
     * PDF para imprimir: los alumnos por apellido, cada uno en un renglón con sus talleres (hora, taller,
     * carrera y edificio), para que cada alumno se encuentre sin buscar en la tabla de talleres. Carta vertical.
     */
    public function alumnos(Request $request): Response
    {
        $html = $this->app->view()->render('reportes/alumnos', [
            'alumnos' => (new Alumno($this->db()))->directorio(),
            'titulo' => self::TITULO_ALUMNOS,
        ], null);
        $this->app->logger()->info('Directorio de alumnos descargado', ['por' => $this->adminId()]);

        return $this->pdf($html, self::TITULO_ALUMNOS, 'directorio-alumnos.pdf', 'portrait');
    }

    /**
     * @param 'landscape'|'portrait' $orientacion
     */
    private function pdf(string $html, string $titulo, string $archivo, string $orientacion): Response
    {
        $pdf = Pdf::desdeHtml(
            $html,
            $titulo,
            (string) $this->app->config('name', 'Auditores de Talleres'),
            $titulo,
            $this->app->basePath('storage/cache/dompdf'),
            $orientacion
        );

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $archivo . '"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }
}
