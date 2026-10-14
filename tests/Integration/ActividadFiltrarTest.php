<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Actividad;
use Tests\Support\IntegrationTestCase;

/**
 * Actividad::filtrar con cada combinación de filtros, los alumnos auditores de cada taller y paginación (SDD 8.2).
 */
final class ActividadFiltrarTest extends IntegrationTestCase
{
    private Actividad $actividades;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actividades = new Actividad($this->app->db());
        $this->admin = $this->crearAdministrador();
    }

    /**
     * @param array{nombre?: string, carrera_id?: int|null, edificio_id?: int|null} $filtros
     * @return list<string>
     */
    private function nombres(array $filtros): array
    {
        return array_column($this->actividades->filtrar($filtros)['filas'], 'nombre');
    }

    private function crearCatalogo(): void
    {
        $this->crearActividad($this->admin, ['nombre' => 'Robótica básica', 'carrera_id' => 11, 'edificio_id' => 3, 'fecha' => '2026-10-01']);
        $this->crearActividad($this->admin, ['nombre' => 'Robótica avanzada', 'carrera_id' => 11, 'edificio_id' => 5, 'fecha' => '2026-10-02']);
        $this->crearActividad($this->admin, ['nombre' => 'Oratoria', 'carrera_id' => 5, 'edificio_id' => 3, 'fecha' => '2026-10-03']);
        $this->crearActividad($this->admin, ['nombre' => 'Finanzas personales', 'carrera_id' => 8, 'edificio_id' => 5, 'fecha' => '2026-09-30']);
    }

    public function testSinFiltrosDevuelveTodosLosActivosOrdenadosPorFecha(): void
    {
        $this->crearCatalogo();
        $this->crearActividad($this->admin, ['nombre' => 'Desactivado', 'activo' => 0]);

        $this->assertSame(
            ['Finanzas personales', 'Robótica básica', 'Robótica avanzada', 'Oratoria'],
            $this->nombres([])
        );
    }

    public function testCadaFiltroSoloYCombinado(): void
    {
        $this->crearCatalogo();

        $this->assertSame(['Robótica básica', 'Robótica avanzada'], $this->nombres(['nombre' => 'robót']));
        $this->assertSame(['Robótica básica', 'Robótica avanzada'], $this->nombres(['carrera_id' => 11]));
        $this->assertSame(['Robótica básica', 'Oratoria'], $this->nombres(['edificio_id' => 3]));
        $this->assertSame(['Robótica básica'], $this->nombres(['nombre' => 'Robót', 'edificio_id' => 3]));
        $this->assertSame(['Robótica avanzada'], $this->nombres(['carrera_id' => 11, 'edificio_id' => 5]));
        $this->assertSame(['Oratoria'], $this->nombres(['nombre' => 'ora', 'carrera_id' => 5, 'edificio_id' => 3]));
        $this->assertSame([], $this->nombres(['nombre' => 'Oratoria', 'carrera_id' => 11]));
    }

    public function testLosComodinesDelNombreSeBuscanComoTexto(): void
    {
        $this->crearActividad($this->admin, ['nombre' => 'Taller 100% práctico']);
        $this->crearActividad($this->admin, ['nombre' => 'Taller_especial']);
        $this->crearActividad($this->admin, ['nombre' => 'Taller normal']);

        $this->assertSame(['Taller 100% práctico'], $this->nombres(['nombre' => '%']));
        $this->assertSame(['Taller_especial'], $this->nombres(['nombre' => '_']));
        $this->assertSame([], $this->nombres(['nombre' => "' OR 1=1 --"]));
    }

    public function testCadaTallerListaASusAlumnosAuditores(): void
    {
        $ana = $this->crearAlumno('Ana', 'Álvarez');
        $luis = $this->crearAlumno('Luis', 'Beltrán');
        $compartido = $this->crearActividad($this->admin, ['nombre' => 'Compartido', 'fecha' => '2026-10-01']);
        $soloLuis = $this->crearActividad($this->admin, ['nombre' => 'Solo de Luis', 'fecha' => '2026-10-02']);
        $this->crearActividad($this->admin, ['nombre' => 'Sin asignar', 'fecha' => '2026-10-03']);
        $this->asignar($compartido, $luis, $this->admin);
        $this->asignar($compartido, $ana, $this->admin);
        $this->asignar($soloLuis, $luis, $this->admin);

        $filas = $this->actividades->filtrar([])['filas'];

        $this->assertSame(['Compartido', 'Solo de Luis', 'Sin asignar'], array_column($filas, 'nombre'));
        $this->assertSame(['Ana Álvarez, Luis Beltrán', 'Luis Beltrán', null], array_column($filas, 'auditores'));
        $this->assertSame(['Solo de Luis'], $this->nombres(['nombre' => 'Luis']));
    }

    public function testPaginaDeVeinteEnVeinte(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->crearActividad($this->admin, ['nombre' => sprintf('Taller %02d', $i), 'fecha' => sprintf('2026-11-%02d', $i)]);
        }

        $primera = $this->actividades->filtrar([], 1, 20);
        $segunda = $this->actividades->filtrar([], 2, 20);
        $fueraDeRango = $this->actividades->filtrar([], 99, 20);

        $this->assertSame(25, $primera['total']);
        $this->assertSame(2, $primera['paginas']);
        $this->assertCount(20, $primera['filas']);
        $this->assertSame('Taller 21', $segunda['filas'][0]['nombre']);
        $this->assertCount(5, $segunda['filas']);
        $this->assertSame(2, $fueraDeRango['pagina']);
    }
}
