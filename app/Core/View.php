<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Renderiza plantillas PHP nativas de app/Views dentro de un layout.
 * Las plantillas deben escapar toda salida con e().
 */
final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $contenido = $this->renderFile($template, $data);
        if ($layout === null) {
            return $contenido;
        }

        return $this->renderFile($layout, ['contenido' => $contenido] + $data);
    }

    /**
     * Renderiza una plantilla sin layout (fragmentos reutilizables).
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->directory . '/' . $template . '.php';
        if (preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#i', $template) !== 1 || !is_file($file)) {
            throw new RuntimeException('Vista no encontrada: ' . $template);
        }

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
