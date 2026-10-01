<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\SchemaInstaller;
use Tests\Support\IntegrationTestCase;
use Tests\Support\TestDatabase;

/**
 * Catálogo de edificios con su área (2026-09-30) y la migración para bases ya instaladas.
 */
final class EdificioTest extends IntegrationTestCase
{
    private const CATALOGO = [
        5 => 'Derecho',
        6 => 'Anáhuac Labs',
        7 => 'Psicología',
        8 => 'Medicina',
        9 => 'Ingeniería',
        11 => 'Economía',
        17 => 'CAD',
        22 => 'Artes',
    ];

    private function idDelEdificio(int $numero): int
    {
        return (int) $this->app->db()->fetchValue('SELECT id FROM edificios WHERE numero = ?', [$numero]);
    }

    public function testElFormularioOfreceLosOchoEdificiosConSuArea(): void
    {
        $this->entrarComoAdministrador();
        $html = $this->get('/actividades/nueva')->body();

        foreach (self::CATALOGO as $numero => $area) {
            $this->assertStringContainsString(e("Edificio {$numero} ({$area})"), $html);
        }
        // Solo esas ocho opciones: los edificios retirados no se ofrecen.
        $this->assertSame(8, preg_match_all('#<option value="\d+"[^>]*>\s*Edificio \d+#', $html));
    }

    public function testElPanelYElDetalleMuestranElEdificioConSuArea(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin, ['nombre' => 'Robótica', 'edificio_id' => $this->idDelEdificio(9)]);

        $this->assertStringContainsString('Edificio 9 (Ingeniería)', $this->get('/')->body());
        $this->assertStringContainsString('9 (Ingeniería)', $this->get("/actividades/{$id}")->body());
    }

    public function testLaMigracionEsIdempotenteYRetiraLosEdificiosQueYaNoEstan(): void
    {
        $db = $this->app->db();
        // Simula una base anterior: un edificio fuera del catálogo y uno del catálogo sin nombre ni activo.
        $db->execute("INSERT INTO edificios (numero, nombre) VALUES (3, '')");
        $db->execute("UPDATE edificios SET nombre = '', activo = 0 WHERE numero = 22");
        $config = TestDatabase::config();
        $instalador = new SchemaInstaller($config, dirname(__DIR__, 2) . '/database');

        try {
            $instalador->migrate((string) $config['name']);
            $aplicadas = $instalador->migrate((string) $config['name']);

            $this->assertContains('2026-09-30_edificios_con_nombre.sql', $aplicadas);
            /** @var list<array{numero: int, nombre: string}> $activos */
            $activos = $db->fetchAll('SELECT numero, nombre FROM edificios WHERE activo = 1 ORDER BY numero');
            $this->assertSame(self::CATALOGO, array_column($activos, 'nombre', 'numero'));
            $this->assertSame(0, (int) $db->fetchValue('SELECT activo FROM edificios WHERE numero = 3'));
        } finally {
            $db->execute('DELETE FROM edificios WHERE numero = 3');
        }
    }

    public function testLaEtiquetaDelEdificio(): void
    {
        $this->assertSame('Edificio 9 (Ingeniería)', edificio_etiqueta(9, 'Ingeniería'));
        $this->assertSame('9 (Ingeniería)', edificio_etiqueta(9, 'Ingeniería', false));
        // Un edificio retirado sin nombre muestra solo su número.
        $this->assertSame('Edificio 3', edificio_etiqueta(3, ''));
    }
}
