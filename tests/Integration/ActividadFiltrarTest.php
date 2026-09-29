<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Actividad;
use App\Models\Usuario;
use Tests\Support\IntegrationTestCase;

/**
 * Actividad::filtrar con cada combinación de filtros, restricción del auditor y paginación (SDD 8.2).
 */
final class ActividadFiltrarTest extends IntegrationTestCase
{
    private Actividad $actividades;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actividades = new Actividad($this->app->db());
        $this->admin = $this->crearUsuario(Usuario::ADMINISTRADOR, 'admin@anahuac.mx', 'Clave-segura-2026');
    }

    /**
     * @param array{nombre?: string, carrera_id?: int|null, edificio_id?: int|null} $filtros
     * @return list<string>
     */
    private function nombres(array $filtros, ?int $usuario = null): array
    {
        return array_column($this->actividades->filtrar($filtros, $usuario)['filas'], 'nombre');
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

    public function testElAuditorSoloVeSusTalleresPeroConTodosSusAuditores(): void
    {
        $ana = $this->crearUsuario(Usuario::AUDITOR, 'ana@anahuac.mx');
        $luis = $this->crearUsuario(Usuario::AUDITOR, 'luis@anahuac.mx');
        $this->app->db()->execute("UPDATE usuarios SET nombre = 'Ana', apellidos = 'Álvarez' WHERE id = ?", [$ana]);
        $this->app->db()->execute("UPDATE usuarios SET nombre = 'Luis', apellidos = 'Beltrán' WHERE id = ?", [$luis]);

        $compartido = $this->crearActividad($this->admin, ['nombre' => 'Compartido']);
        $soloLuis = $this->crearActividad($this->admin, ['nombre' => 'Solo de Luis']);
        $this->crearActividad($this->admin, ['nombre' => 'Sin asignar']);
        $this->asignar($compartido, $ana, $this->admin);
        $this->asignar($compartido, $luis, $this->admin);
        $this->asignar($soloLuis, $luis, $this->admin);

        $deAna = $this->actividades->filtrar([], $ana)['filas'];
        $this->assertSame(['Compartido'], array_column($deAna, 'nombre'));
        $this->assertSame('Ana Álvarez, Luis Beltrán', $deAna[0]['auditores']);

        $this->assertSame(['Compartido', 'Solo de Luis'], $this->nombres([], $luis));
        $this->assertSame(['Solo de Luis'], $this->nombres(['nombre' => 'Luis'], $luis));

        $todos = $this->actividades->filtrar([], null)['filas'];
        $this->assertNull($todos[2]['auditores']);
    }

    public function testPaginaDeVeinteEnVeinte(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->crearActividad($this->admin, ['nombre' => sprintf('Taller %02d', $i), 'fecha' => sprintf('2026-11-%02d', $i)]);
        }

        $primera = $this->actividades->filtrar([], null, 1, 20);
        $segunda = $this->actividades->filtrar([], null, 2, 20);
        $fueraDeRango = $this->actividades->filtrar([], null, 99, 20);

        $this->assertSame(25, $primera['total']);
        $this->assertSame(2, $primera['paginas']);
        $this->assertCount(20, $primera['filas']);
        $this->assertSame('Taller 21', $segunda['filas'][0]['nombre']);
        $this->assertCount(5, $segunda['filas']);
        $this->assertSame(2, $fueraDeRango['pagina']);
    }
}
