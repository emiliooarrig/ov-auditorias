<?php

declare(strict_types=1);

namespace App\Core;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/**
 * Convierte una vista HTML en PDF con Dompdf. No carga recursos remotos ni ejecuta PHP incrustado:
 * la plantilla trae sus estilos y solo usa las fuentes base del PDF (Helvetica). Las métricas de las
 * fuentes se guardan en $cache (storage/cache/dompdf) para no escribir dentro de vendor/.
 */
final class Pdf
{
    private const FUENTE = 'Helvetica';
    private const TAMANO_PIE = 7.0;
    /** Gris de texto secundario (#5C524B) en RGB de 0 a 1. */
    private const COLOR_PIE = [0.36, 0.32, 0.29];

    /**
     * @param string $pie Texto a la izquierda del pie de cada página; a la derecha va "Página N de M".
     */
    public static function desdeHtml(string $html, string $titulo, string $autor, string $pie, string $cache): string
    {
        if (!is_dir($cache) && !mkdir($cache, 0775, true) && !is_dir($cache)) {
            throw new RuntimeException('No se pudo crear la carpeta ' . $cache);
        }
        $opciones = new Options();
        $opciones->setFontCache($cache);
        $opciones->setDefaultFont(self::FUENTE);
        $opciones->setIsRemoteEnabled(false);
        $opciones->setIsPhpEnabled(false);
        $opciones->setIsJavascriptEnabled(false);

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->addInfo('Title', $titulo);
        $dompdf->addInfo('Author', $autor);
        $dompdf->render();

        // Pie en todas las páginas, a la altura del margen inferior (12 mm de los lados).
        $canvas = $dompdf->getCanvas();
        $metricas = $dompdf->getFontMetrics();
        $fuente = $metricas->getFont(self::FUENTE);
        $margen = 12 * 72 / 25.4;
        $y = $canvas->get_height() - 10 * 72 / 25.4;
        $paginas = 'Página {PAGE_NUM} de {PAGE_COUNT}';
        $ancho = $metricas->getTextWidth('Página 000 de 000', $fuente, self::TAMANO_PIE);
        $derecha = $canvas->get_width() - $margen - $ancho;
        $canvas->page_text($margen, $y, $pie, $fuente, self::TAMANO_PIE, self::COLOR_PIE);
        $canvas->page_text($derecha, $y, $paginas, $fuente, self::TAMANO_PIE, self::COLOR_PIE);

        return (string) $dompdf->output();
    }
}
