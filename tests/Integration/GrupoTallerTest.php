<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDOException;
use Tests\Support\IntegrationTestCase;

/**
 * Grupos de talleres (Taller 1 … 6): cada taller pertenece a uno y el grupo fija su horario.
 */
final class GrupoTallerTest extends IntegrationTestCase
{
    private const HORARIOS = [
        1 => ['10:00:00', '11:00:00'],
        2 => ['11:00:00', '12:00:00'],
        3 => ['12:00:00', '13:00:00'],
        4 => ['13:00:00', '14:00:00'],
        5 => ['15:00:00', '16:00:00'],
        6 => ['16:00:00', '17:00:00'],
    ];

    private function idDelGrupo(int $numero): int
    {
        return (int) $this->app->db()->fetchValue('SELECT id FROM grupos_taller WHERE numero = ?', [$numero]);
    }

    /**
     * @return array<string, mixed>
     */
    private function taller(int $id): array
    {
        $fila = $this->app->db()->fetchOne('SELECT grupo_id, hora_inicio, hora_fin FROM actividades WHERE id = ?', [$id]);
        $this->assertNotNull($fila);

        return $fila;
    }

    public function testElCatalogoTieneLosSeisGruposConSuHorario(): void
    {
        /** @var list<array{numero: int, nombre: string, hora_inicio: string, hora_fin: string}> $grupos */
        $grupos = $this->app->db()->fetchAll('SELECT numero, nombre, hora_inicio, hora_fin FROM grupos_taller ORDER BY numero');

        $this->assertCount(6, $grupos);
        foreach ($grupos as $g) {
            $this->assertSame('Taller ' . $g['numero'], $g['nombre']);
            $this->assertSame(self::HORARIOS[$g['numero']], [$g['hora_inicio'], $g['hora_fin']]);
        }
    }

    public function testElFormularioOfreceLosGruposConSuHorarioEnLugarDeHoras(): void
    {
        $this->entrarComoAdministrador();
        $html = $this->get('/actividades/nueva')->body();

        $this->assertStringContainsString('Taller 1 (10:00–11:00)', $html);
        $this->assertStringContainsString('Taller 5 (15:00–16:00)', $html);
        $this->assertStringContainsString('Taller 6 (16:00–17:00)', $html);
        $this->assertStringNotContainsString('name="hora_inicio"', $html);
        $this->assertStringNotContainsString('name="hora_fin"', $html);
    }

    public function testElHorarioSaleDelGrupoAunqueElFormularioMandeOtrasHoras(): void
    {
        $this->entrarComoAdministrador();

        $this->post('/actividades', [
            'nombre' => 'Oratoria',
            'carrera_id' => '5',
            'edificio_id' => '1',
            'fecha' => '2026-10-20',
            'grupo_id' => (string) $this->idDelGrupo(5),
            'hora_inicio' => '09:00',
            'hora_fin' => '18:30',
        ]);

        $id = (int) $this->app->db()->fetchValue('SELECT id FROM actividades');
        $this->assertSame(
            ['grupo_id' => $this->idDelGrupo(5), 'hora_inicio' => '15:00:00', 'hora_fin' => '16:00:00'],
            $this->taller($id)
        );
    }

    public function testCambiarElGrupoCambiaElHorario(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);

        $this->post("/actividades/{$id}", [
            'nombre' => 'Robótica',
            'carrera_id' => '1',
            'edificio_id' => '1',
            'fecha' => '2026-10-15',
            'grupo_id' => (string) $this->idDelGrupo(4),
        ]);

        $this->assertSame(['13:00:00', '14:00:00'], array_values(array_slice($this->taller($id), 1)));
    }

    public function testElPanelYElDetalleMuestranElGrupo(): void
    {
        $admin = $this->entrarComoAdministrador();
        $id = $this->crearActividad($admin, ['nombre' => 'Finanzas', 'grupo' => 2]);

        $this->assertStringContainsString('Taller 2, 11:00–12:00', $this->get('/')->body());
        $this->assertMatchesRegularExpression('#Grupo</dt>\s*<dd>Taller 2</dd>#', $this->get("/actividades/{$id}")->body());
    }

    public function testElPanelFiltraPorGrupo(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->crearActividad($admin, ['nombre' => 'Robótica temprano', 'grupo' => 1]);
        $this->crearActividad($admin, ['nombre' => 'Oratoria mediodía', 'grupo' => 3]);
        $grupo3 = (string) $this->idDelGrupo(3);

        $html = $this->get('/', ['grupo' => $grupo3])->body();

        $this->assertStringContainsString('Oratoria mediodía', $html);
        $this->assertStringNotContainsString('Robótica temprano', $html);
        $this->assertStringContainsString('1 taller</strong>', $html);
        $this->assertMatchesRegularExpression('#<option value="' . $grupo3 . '" selected>\s*Taller 3 \(12:00–13:00\)#', $html);
        $this->assertMatchesRegularExpression('#id="f-grupo" name="grupo" class="is-active"#', $html);

        // Combinado con otro filtro y con un valor inválido (se ignora).
        $combinado = $this->get('/', ['grupo' => $grupo3, 'nombre' => 'Robótica'])->body();
        $this->assertStringContainsString('Ningún taller coincide con los filtros.', $combinado);
        $invalido = $this->get('/', ['grupo' => '1 OR 1=1'])->body();
        $this->assertStringContainsString('Robótica temprano', $invalido);
        $this->assertStringContainsString('Oratoria mediodía', $invalido);
    }

    public function testElAuditorFiltraPorGrupoSoloEntreSusTalleres(): void
    {
        $admin = $this->entrarComoAdministrador();
        $suyo = $this->crearActividad($admin, ['nombre' => 'Suyo en Taller 2', 'grupo' => 2]);
        $this->crearActividad($admin, ['nombre' => 'Ajeno en Taller 2', 'grupo' => 2]);
        $auditor = $this->crearUsuario('auditor', 'ana.lopez@anahuac.mx');
        $this->asignar($suyo, $auditor, $admin);

        $this->entrarComoAuditor('ana.lopez@anahuac.mx');
        $html = $this->get('/mis-talleres', ['grupo' => (string) $this->idDelGrupo(2)])->body();

        $this->assertStringContainsString('Suyo en Taller 2', $html);
        $this->assertStringNotContainsString('Ajeno en Taller 2', $html);
    }

    public function testAsignacionesVuelveConElFiltroDeGrupo(): void
    {
        $admin = $this->entrarComoAdministrador();
        $taller = $this->crearActividad($admin, ['grupo' => 4]);
        $auditor = $this->crearUsuario('auditor', 'ana.lopez@anahuac.mx');
        $grupo4 = (string) $this->idDelGrupo(4);

        $response = $this->post('/asignaciones', [
            'usuario_id' => (string) $auditor,
            'actividades' => [(string) $taller],
            'grupo' => $grupo4,
        ]);

        $this->assertRedirige("/asignaciones?grupo={$grupo4}&usuario={$auditor}", $response);
    }

    public function testLaBaseNoAceptaUnTallerSinGrupo(): void
    {
        $admin = $this->entrarComoAdministrador();

        $this->expectException(PDOException::class);
        $this->app->db()->execute(
            "INSERT INTO actividades (nombre, carrera_id, edificio_id, fecha, hora_inicio, hora_fin, creado_por)
             VALUES ('Sin grupo', 1, 1, '2026-10-15', '10:00:00', '11:00:00', :admin)",
            ['admin' => $admin]
        );
    }
}
