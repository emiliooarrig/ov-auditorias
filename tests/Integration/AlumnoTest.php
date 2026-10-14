<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Alumnos auditores: alta uno por uno y por lista, edición, baja y reactivación, lista con panel.
 * Los alumnos no tienen cuenta: solo nombre, apellidos y el horario en el que pueden auditar.
 */
final class AlumnoTest extends IntegrationTestCase
{
    /**
     * @return list<array{nombre: string, apellidos: string}>
     */
    private function alumnos(): array
    {
        /** @var list<array{nombre: string, apellidos: string}> */
        return $this->app->db()->fetchAll('SELECT nombre, apellidos FROM alumnos ORDER BY id');
    }

    public function testAltaUnoPorUnoConNombreApellidosYHorario(): void
    {
        $admin = $this->entrarComoAdministrador();
        $matutino = $this->horarioId('Matutino');

        $response = $this->post('/alumnos', [
            'nombre' => '  José  María ',
            'apellidos' => "O'Connor Núñez",
            'horario_id' => (string) $matutino,
            'estado' => 'activos',
        ]);

        $this->assertRedirige('/alumnos?estado=activos', $response);
        $this->assertSame([['nombre' => 'José María', 'apellidos' => "O'Connor Núñez"]], $this->alumnos());
        $this->assertSame($admin, $this->contar('SELECT creado_por FROM alumnos'));
        $this->assertSame($matutino, $this->contar('SELECT horario_id FROM alumnos'));
        $html = $this->get('/alumnos')->body();
        $this->assertStringContainsString('Se agregó a José María O&apos;Connor Núñez.', $html);
        $this->assertStringContainsString('O&apos;Connor Núñez, José María', $html);
        $this->assertStringContainsString('Matutino', $html);
        // Ni correo ni contraseña: no es una cuenta.
        $this->assertSame(1, $this->contar('SELECT COUNT(*) FROM usuarios'));
    }

    public function testAltaRechazaNombresVaciosOConCodigoYExigeHorario(): void
    {
        $this->entrarComoAdministrador();

        $this->assertRedirige('/alumnos', $this->post('/alumnos', ['nombre' => '<script>x</script>', 'apellidos' => '']));

        $html = $this->get('/alumnos')->body();
        $this->assertStringContainsString('Este campo es obligatorio.', $html);
        $this->assertStringContainsString('Solo se permiten letras', $html);
        $this->assertStringContainsString('Elige el horario en el que puede auditar.', $html);
        $this->post('/alumnos', ['nombre' => 'Ana', 'apellidos' => 'López', 'horario_id' => '99']);
        $this->assertStringContainsString('Elige el horario en el que puede auditar.', $this->get('/alumnos')->body());
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertSame([], $this->alumnos());
    }

    public function testNoSeRepiteUnNombreAunqueCambienMayusculasOAcentos(): void
    {
        $this->entrarComoAdministrador();
        $this->crearAlumno('Ana', 'López Ruiz');
        $baja = $this->crearAlumno('Luis', 'Pérez', false);

        $horario = (string) $this->horarioId('Matutino');
        $this->post('/alumnos', ['nombre' => 'ANA', 'apellidos' => 'lopez ruiz', 'horario_id' => $horario]);
        $this->assertStringContainsString('Ya hay un alumno con ese nombre.', $this->get('/alumnos')->body());

        $this->post('/alumnos', ['nombre' => 'Luis', 'apellidos' => 'Perez', 'horario_id' => $horario]);
        $this->assertStringContainsString('Ya hay un alumno dado de baja con ese nombre', $this->get('/alumnos')->body());

        $this->assertCount(2, $this->alumnos());
        $this->assertSame(0, $this->contar('SELECT activo FROM alumnos WHERE id = ?', [$baja]));
    }

    public function testAltaPorListaConComasYColumnasDeExcel(): void
    {
        $this->entrarComoAdministrador();
        $this->crearAlumno('Ana', 'López Ruiz');

        $lista = "Apellidos\tNombre\n"           // encabezado copiado de Excel: se ignora
            . "Pérez\tGil\tLuis\n"                // tres columnas: paterno, materno, nombre
            . "  Soto Mar ,  Eva  \n"
            . "\n"
            . "López Ruiz, Ana\n"                 // ya existía
            . "Pérez Gil, Luis\n";                // repetido dentro de la misma lista

        $vespertino = $this->horarioId('Vespertino');
        $this->post('/alumnos/lista', ['lista' => $lista, 'orden' => 'apellidos', 'horario_id' => (string) $vespertino]);

        $this->assertSame([
            ['nombre' => 'Ana', 'apellidos' => 'López Ruiz'],
            ['nombre' => 'Luis', 'apellidos' => 'Pérez Gil'],
            ['nombre' => 'Eva', 'apellidos' => 'Soto Mar'],
        ], $this->alumnos());
        // Los nuevos quedan con el horario elegido para la lista; el que ya existía conserva el suyo.
        $this->assertSame(2, $this->contar('SELECT COUNT(*) FROM alumnos WHERE horario_id = ?', [$vespertino]));
        $html = $this->get('/alumnos')->body();
        $this->assertStringContainsString('Se agregaron 2 alumnos.', $html);
        $this->assertStringContainsString('Ya estaban registrados: Ana López Ruiz, Luis Pérez Gil.', $html);
    }

    public function testListaConElNombrePrimero(): void
    {
        $this->entrarComoAdministrador();

        $this->post('/alumnos/lista', [
            'lista' => "Ana\tLópez\tRuiz\nLuis, Pérez Gil",
            'orden' => 'nombre',
            'horario_id' => (string) $this->horarioId('Todo el día'),
        ]);

        $this->assertSame([
            ['nombre' => 'Ana', 'apellidos' => 'López Ruiz'],
            ['nombre' => 'Luis', 'apellidos' => 'Pérez Gil'],
        ], $this->alumnos());
    }

    public function testLosRenglonesQueNoSeEntiendenRegresanParaCorregirlos(): void
    {
        $this->entrarComoAdministrador();

        $matutino = (string) $this->horarioId('Matutino');
        $this->post('/alumnos/lista', [
            'lista' => "López Ruiz, Ana\nLuis Pérez Gil\nSoto, Eva2",
            'orden' => 'apellidos',
            'horario_id' => $matutino,
        ]);

        $this->assertSame([['nombre' => 'Ana', 'apellidos' => 'López Ruiz']], $this->alumnos());
        $html = $this->get('/alumnos')->body();
        $this->assertStringContainsString('Se agregó 1 alumno. 2 renglones no se pudieron leer', $html);
        $this->assertStringContainsString('«Luis Pérez Gil»: Separa el nombre de los apellidos', $html);
        $this->assertStringContainsString('«Soto, Eva2»: Nombre: Solo se permiten letras', $html);
        // En el cuadro quedan solo los renglones por corregir, con el mismo horario elegido.
        $this->assertMatchesRegularExpression('#<textarea[^>]*name="lista"[^>]*>\s*Luis Pérez Gil\nSoto, Eva2</textarea>#', $html);
        $this->assertMatchesRegularExpression(
            '#<select id="alta-lista-horario".*<option value="' . $matutino . '" selected>#s',
            $html
        );
    }

    public function testListaVaciaOMuyLargaSeRechaza(): void
    {
        $this->entrarComoAdministrador();

        $this->post('/alumnos/lista', ['lista' => "  \n\n"]);
        $vacia = $this->get('/alumnos')->body();
        $this->assertStringContainsString('Pega al menos un alumno', $vacia);
        $this->assertStringContainsString('Elige el horario en el que puede auditar.', $vacia);

        $larga = implode("\n", array_map(static fn (int $i): string => 'Apellido, Nombre', range(1, 501)));
        $this->post('/alumnos/lista', ['lista' => $larga]);
        $this->assertStringContainsString('pega como máximo 500 a la vez', $this->get('/alumnos')->body());
        $this->assertSame([], $this->alumnos());
    }

    public function testElPanelMuestraLosTalleresQueAuditaYCuandoSeAsignaron(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López Ruiz');
        $robotica = $this->crearActividad($admin, ['nombre' => 'Robótica', 'grupo' => 1]);
        $oratoria = $this->crearActividad($admin, ['nombre' => 'Oratoria', 'grupo' => 2, 'estado' => 'realizado']);
        $this->crearActividad($admin, ['nombre' => 'Pintura', 'grupo' => 3]);
        $this->asignar($robotica, $ana, $admin, 'aleatoria');
        $this->asignar($oratoria, $ana, $admin);

        $html = $this->get("/alumnos/{$ana}")->body();

        $this->assertStringContainsString('<h2 class="panel__title"', $html);
        $this->assertStringContainsString('Ana López Ruiz', $html);
        $this->assertStringContainsString('2 talleres', $html);
        $this->assertStringContainsString('/actividades/' . $robotica, $html);
        $this->assertStringContainsString('Al azar', $html);
        $this->assertStringContainsString('A mano', $html);
        // Solo el taller programado se puede quitar; el realizado queda como registro.
        $this->assertSame(1, substr_count($html, '/quitar'));
        // Para asignar a mano se ofrecen los talleres programados en horarios libres.
        $this->assertMatchesRegularExpression('#<select id="asignar-taller"[^>]*>.*Pintura.*</select>#s', $html);
        $this->assertDoesNotMatchRegularExpression('#<select id="asignar-taller"[^>]*>.*Robótica.*</select>#s', $html);
        $this->assertStringContainsString('aria-current="true"', $html);
        $this->assertSame(404, $this->get('/alumnos/99999')->status());
    }

    public function testSinJavaScriptLaListaSeFiltraEnElServidor(): void
    {
        $admin = $this->entrarComoAdministrador();
        $this->crearAlumno('Ana', 'González');
        $luis = $this->crearAlumno('Luis', 'Pérez');
        $this->crearAlumno('Eva', 'Soto', false);
        $this->asignar($this->crearActividad($admin), $luis, $admin);

        $html = $this->get('/alumnos', ['texto' => 'gonzalez'])->body();
        $this->assertStringContainsString('1 de 3 alumnos', $html);
        $this->assertMatchesRegularExpression('#<li data-buscar="Ana González"[^>]*data-talleres="sin"\s+data-horario="\d+">#', $html);
        $this->assertMatchesRegularExpression('#<li data-buscar="Luis Pérez"[^>]*hidden>#', $html);

        $sinTaller = $this->get('/alumnos', ['talleres' => 'sin', 'estado' => 'activos'])->body();
        $this->assertStringContainsString('1 de 3 alumnos', $sinTaller);
        $this->assertStringContainsString('<option value="sin" selected>', $sinTaller);
        // Los enlaces de la lista conservan los filtros.
        $this->assertStringContainsString(e("/alumnos/{$luis}?estado=activos&talleres=sin"), $sinTaller);
    }

    public function testLaListaSeFiltraPorHorario(): void
    {
        $this->entrarComoAdministrador();
        $this->crearAlumno('Ana', 'González', true, 'Matutino');
        $this->crearAlumno('Luis', 'Pérez', true, 'Vespertino');
        $this->crearAlumno('Eva', 'Soto', true, null);
        $matutino = (string) $this->horarioId('Matutino');

        $html = $this->get('/alumnos', ['horario' => $matutino])->body();
        $this->assertStringContainsString('1 de 3 alumnos', $html);
        $this->assertStringContainsString('<option value="' . $matutino . '" selected>Matutino</option>', $html);
        $this->assertMatchesRegularExpression('#<li data-buscar="Ana González"[^>]*data-horario="' . $matutino . '">#', $html);
        $this->assertMatchesRegularExpression('#<li data-buscar="Luis Pérez"[^>]*hidden>#', $html);

        $sinHorario = $this->get('/alumnos', ['horario' => 'sin'])->body();
        $this->assertStringContainsString('1 de 3 alumnos', $sinHorario);
        $this->assertMatchesRegularExpression('#<li data-buscar="Eva Soto"[^>]*data-horario="sin">#', $sinHorario);

        // Un valor desconocido se descarta.
        $this->assertStringNotContainsString(' de 3 alumnos', $this->get('/alumnos', ['horario' => 'x'])->body());
    }

    public function testLaListaSePaginaDeDiezEnDiez(): void
    {
        $this->entrarComoAdministrador();
        $ids = [];
        for ($i = 1; $i <= 23; $i++) {
            $ids[$i] = $this->crearAlumno('Alumno', sprintf('Apellido%02d', $i));
        }
        $visible = static fn (int $i): string => '#<li data-buscar="Alumno ' . sprintf('Apellido%02d', $i) . '"[^>]*"\d+">#';
        $oculto = static fn (int $i): string => '#<li data-buscar="Alumno ' . sprintf('Apellido%02d', $i) . '"[^>]*hidden>#';

        $html = $this->get('/alumnos')->body();
        $this->assertMatchesRegularExpression($visible(1), $html);
        $this->assertMatchesRegularExpression($visible(10), $html);
        $this->assertMatchesRegularExpression($oculto(11), $html);
        $this->assertStringContainsString('data-pagina="1"', $html);
        $this->assertStringContainsString('href="' . e(url('/alumnos', ['pagina' => 3])) . '"', $html);

        $tercera = $this->get('/alumnos', ['pagina' => '3'])->body();
        $this->assertMatchesRegularExpression($oculto(20), $tercera);
        $this->assertMatchesRegularExpression($visible(21), $tercera);
        $this->assertMatchesRegularExpression($visible(23), $tercera);
        $this->assertStringContainsString('<span aria-current="page">3</span>', $tercera);
        // Los enlaces de la lista conservan la página.
        $this->assertStringContainsString(e("/alumnos/{$ids[21]}?pagina=3"), $tercera);

        // Una página que ya no existe muestra la última; al filtrar, la paginación se calcula sobre lo que coincide.
        $this->assertMatchesRegularExpression($visible(23), $this->get('/alumnos', ['pagina' => '99'])->body());
        $filtrada = $this->get('/alumnos', ['texto' => 'apellido2', 'pagina' => '2'])->body();
        $this->assertMatchesRegularExpression($visible(20), $filtrada);
        $this->assertStringNotContainsString('class="pagination"', $filtrada);

        // Al guardar desde el panel se vuelve a la misma página.
        $horario = (string) $this->horarioId('Todo el día');
        $this->assertRedirige("/alumnos/{$ids[12]}?pagina=2", $this->post("/alumnos/{$ids[12]}", [
            'nombre' => 'Alumno', 'apellidos' => 'Apellido12', 'horario_id' => $horario, 'pagina' => '2',
        ]));
    }

    public function testCorregirElNombre(): void
    {
        $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'Lopes');
        $this->crearAlumno('Luis', 'Pérez');

        $horario = (string) $this->horarioId('Todo el día');
        $this->assertRedirige("/alumnos/{$ana}?texto=lo", $this->post("/alumnos/{$ana}", ['nombre' => 'Ana', 'apellidos' => 'López', 'horario_id' => $horario, 'texto' => 'lo']));
        $this->post("/alumnos/{$ana}", ['nombre' => 'Luis', 'apellidos' => 'Pérez', 'horario_id' => $horario]);

        $this->assertSame('López', (string) $this->app->db()->fetchValue('SELECT apellidos FROM alumnos WHERE id = ?', [$ana]));
        $html = $this->get("/alumnos/{$ana}")->body();
        $this->assertStringContainsString('Ya hay un alumno con ese nombre.', $html);
        $this->assertStringContainsString('<details class="disclosure" open>', $html);
    }

    public function testDarDeBajaLeQuitaLosTalleresProgramadosYConservaLosRealizados(): void
    {
        $admin = $this->entrarComoAdministrador();
        $ana = $this->crearAlumno('Ana', 'López');
        $this->asignar($this->crearActividad($admin, ['grupo' => 1]), $ana, $admin);
        $this->asignar($this->crearActividad($admin, ['grupo' => 2]), $ana, $admin);
        $realizado = $this->crearActividad($admin, ['grupo' => 3, 'estado' => 'realizado']);
        $this->asignar($realizado, $ana, $admin);

        $html = $this->get("/alumnos/{$ana}")->body();
        $this->assertStringContainsString('data-confirmar="¿Dar de baja a Ana López?"', $html);
        $this->assertStringContainsString('Se le quitarán los 2 talleres programados', $html);

        $this->post("/alumnos/{$ana}/estado", ['activo' => '0']);

        $this->assertSame(0, $this->contar('SELECT activo FROM alumnos WHERE id = ?', [$ana]));
        $this->assertSame([$realizado], array_map('intval', array_column(
            $this->app->db()->fetchAll('SELECT actividad_id FROM asignaciones WHERE alumno_id = ?', [$ana]),
            'actividad_id'
        )));
        $html = $this->get("/alumnos/{$ana}")->body();
        $this->assertStringContainsString('Se dio de baja a Ana López. Se le quitaron 2 talleres programados.', $html);
        $this->assertStringContainsString('Reactívalo para asignarle talleres', $html);

        $this->post("/alumnos/{$ana}/estado", ['activo' => '1']);
        $this->assertSame(1, $this->contar('SELECT activo FROM alumnos WHERE id = ?', [$ana]));
        $this->post("/alumnos/{$ana}/estado", ['activo' => '1']);
        $this->assertStringContainsString('El alumno ya está activo.', $this->get("/alumnos/{$ana}")->body());
    }

    public function testLosNombresSeEscapanEnLaListaYElPanel(): void
    {
        $this->entrarComoAdministrador();
        // Un nombre válido con apóstrofo y uno inválido insertado directo en la base.
        $id = $this->crearAlumno('"><img src=x>', "D'Artagnan");

        foreach (['/alumnos', "/alumnos/{$id}"] as $ruta) {
            $html = $this->get($ruta)->body();
            $this->assertStringNotContainsString('<img src=x>', $html, $ruta);
            $this->assertStringContainsString('&quot;&gt;&lt;img src=x&gt;', $html, $ruta);
        }
    }
}
