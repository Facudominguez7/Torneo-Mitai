<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

const TIPO_PARTIDO_PANEL = 'App\\Models\\Partido';

function crearAdminPanel(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoBaseAdmin(): array
{
    $edicion = Edicion::create([
        'nombre' => 'Edicion Test',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);

    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 7',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    return compact('edicion', 'categoria', 'fecha', 'grupo');
}

function crearEquipoEnEdicion(array $contexto, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    return $equipo;
}

function crearPartidoAdmin(
    array $contexto,
    Equipo $local,
    Equipo $visitante,
    string $horario,
    ?int $golesEquipoLocal = 0,
    ?int $golesEquipoVisitante = 0,
): Partido {
    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $golesEquipoLocal,
        'golesEquipoVisitante' => $golesEquipoVisitante,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => $horario,
    ]);
}

function crearJugadorConPlanilla(Partido $partido, Equipo $equipo, Categoria $categoria, Edicion $edicion, int $dni, bool $asistio): void
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => 'Jugador',
        'apellido' => (string) $dni,
    ]);

    PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => TIPO_PARTIDO_PANEL,
        'dni_jugador' => $dni,
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'goles' => 0,
        'asistio' => $asistio,
        'tarjeta_roja' => false,
    ]);
}

/**
 * Extrae con DOM el markup de la tarjeta del dashboard de un partido
 * especifico (elemento con data-dashboard-partido) para que las aserciones
 * de badges queden asociadas al partido correcto y no arrastren texto del
 * sidebar, otras secciones ni tarjetas vecinas.
 */
function contenidoTarjetaPartido(TestResponse $response, int $partidoId): string
{
    $documento = new DOMDocument;
    $estadoErrores = libxml_use_internal_errors(true);

    try {
        if ($documento->loadHTML($response->getContent()) === false) {
            return '';
        }

        $tarjetas = (new DOMXPath($documento))->query('//*[@data-dashboard-partido="'.$partidoId.'"]');

        if ($tarjetas === false || $tarjetas->length === 0) {
            return '';
        }

        $markup = $documento->saveHTML($tarjetas->item(0));

        return $markup === false ? '' : $markup;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($estadoErrores);
    }
}

it('extrae unicamente la tarjeta pedida sin arrastrar el contenido de la tarjeta vecina', function () {
    $html = '<div data-dashboard-partido="1"><span>Mitai Uno</span></div>'
        .'<div data-dashboard-partido="2"><span class="badge">Mitai Dos (Local): Sin planilla</span>'
        .'<a href="/planilla">Planillas</a></div>';
    $respuesta = new TestResponse(new Response($html));

    expect(contenidoTarjetaPartido($respuesta, 1))
        ->not->toBeEmpty()
        ->not->toContain('Sin planilla')
        ->and(contenidoTarjetaPartido($respuesta, 2))
        ->toContain('Mitai Dos (Local): Sin planilla')
        ->and(contenidoTarjetaPartido($respuesta, 999))->toBe('');
});

it('muestra en la tarjeta del partido los equipos sin planilla con su condicion', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Azul');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Rojo');
    $partido = crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 10:00:00');

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $tarjeta = contenidoTarjetaPartido($response, $partido->id);

    $response->assertOk();
    expect($tarjeta)->not->toBeEmpty()
        ->toContain('Mitai Azul (Local): Sin planilla')
        ->toContain('Mitai Rojo (Visitante): Sin planilla');
});

it('distingue el badge del equipo con planilla sin asistencias marcadas del que no cargo planilla', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Verde');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Blanco');
    $partido = crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 11:00:00');

    crearJugadorConPlanilla($partido, $local, $contexto['categoria'], $contexto['edicion'], 30100111, false);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $tarjeta = contenidoTarjetaPartido($response, $partido->id);

    $response->assertOk();
    expect($tarjeta)->not->toBeEmpty()
        ->toContain('Mitai Verde (Local): Sin asistencias marcadas')
        ->toContain('Mitai Blanco (Visitante): Sin planilla');
});

it('mantiene la tarjeta del partido visible sin badges de asistencia cuando ambos equipos registraron presentes', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Dorado');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Negro');
    $partido = crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 12:00:00');

    crearJugadorConPlanilla($partido, $local, $contexto['categoria'], $contexto['edicion'], 30100222, true);
    crearJugadorConPlanilla($partido, $visitante, $contexto['categoria'], $contexto['edicion'], 30100223, true);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $tarjeta = contenidoTarjetaPartido($response, $partido->id);

    $response->assertOk();
    expect($tarjeta)->not->toBeEmpty()
        ->toContain('Mitai Dorado')
        ->toContain('/planilla/'.$partido->id.'/'.$contexto['edicion']->id.'/partido')
        ->toContain('Planillas</a>')
        ->not->toContain('Sin planilla')
        ->not->toContain('Sin asistencias marcadas');
});

it('marca solo al equipo pendiente cuando el rival ya registro presentes', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Presente');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Ausente');
    $partido = crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 13:00:00');

    crearJugadorConPlanilla($partido, $local, $contexto['categoria'], $contexto['edicion'], 30100333, true);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $tarjeta = contenidoTarjetaPartido($response, $partido->id);

    $response->assertOk();
    expect($tarjeta)->not->toBeEmpty()
        ->toContain('Mitai Ausente (Visitante): Sin planilla')
        ->not->toContain('(Local): Sin planilla')
        ->not->toContain('(Local): Sin asistencias marcadas');
});

it('filtra las tarjetas al horario seleccionado y excluye los partidos de otros horarios', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();

    $equipoManana = crearEquipoEnEdicion($contexto, 'Mitai Manana');
    $rivalManana = crearEquipoEnEdicion($contexto, 'Mitai Sol');
    $partidoManana = crearPartidoAdmin($contexto, $equipoManana, $rivalManana, '2026-03-15 09:00:00');

    $equipoTarde = crearEquipoEnEdicion($contexto, 'Mitai Tarde');
    $rivalTarde = crearEquipoEnEdicion($contexto, 'Mitai Luna');
    $partidoTarde = crearPartidoAdmin($contexto, $equipoTarde, $rivalTarde, '2026-03-15 15:00:00');

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id, 'horario' => '15:00']));

    $response->assertOk()
        ->assertSee('Mostrando partidos de las 15:00 hs');

    expect($response->getContent())
        ->toContain('value="15:00" selected')
        ->not->toContain('value="09:00" selected')
        ->and(contenidoTarjetaPartido($response, $partidoTarde->id))
        ->toContain('Mitai Tarde (Local): Sin planilla')
        ->toContain('Mitai Luna (Visitante): Sin planilla')
        ->and(contenidoTarjetaPartido($response, $partidoManana->id))->toBe('');
});

it('muestra los partidos de todos los horarios cuando no hay filtro seleccionado', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();

    $equipoManana = crearEquipoEnEdicion($contexto, 'Mitai Manana');
    $rivalManana = crearEquipoEnEdicion($contexto, 'Mitai Sol');
    $partidoManana = crearPartidoAdmin($contexto, $equipoManana, $rivalManana, '2026-03-15 09:00:00');

    $equipoTarde = crearEquipoEnEdicion($contexto, 'Mitai Tarde');
    $rivalTarde = crearEquipoEnEdicion($contexto, 'Mitai Luna');
    $partidoTarde = crearPartidoAdmin($contexto, $equipoTarde, $rivalTarde, '2026-03-15 15:00:00');

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Todos los horarios')
        ->assertDontSee('Mostrando partidos de las');

    expect(contenidoTarjetaPartido($response, $partidoManana->id))
        ->toContain('Mitai Manana (Local): Sin planilla')
        ->and(contenidoTarjetaPartido($response, $partidoTarde->id))
        ->toContain('Mitai Tarde (Local): Sin planilla');
});

it('no arrastra los pendientes de otra edicion a las tarjetas del panel', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Local A');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Visitante A');
    $partido = crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 14:00:00');

    $otroContexto = crearContextoBaseAdmin();
    $equipoForaneo = crearEquipoEnEdicion($otroContexto, 'Mitai Foraneo');
    $rivalForaneo = crearEquipoEnEdicion($otroContexto, 'Mitai Foraneo Rival');
    crearPartidoAdmin($otroContexto, $equipoForaneo, $rivalForaneo, '2026-03-15 14:00:00');

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $tarjeta = contenidoTarjetaPartido($response, $partido->id);

    $response->assertOk()
        ->assertDontSee('Mitai Foraneo');

    expect($tarjeta)->not->toBeEmpty()
        ->toContain('Mitai Local A (Local): Sin planilla')
        ->not->toContain('Foraneo');
});

it('imprime solo los partidos de la ultima fecha disponible para arbitros', function () {
    $admin = crearAdminPanel();

    $edicion = Edicion::create([
        'nombre' => 'Edicion Test',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);

    $fechaAnterior = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 1',
    ]);

    $fechaUltima = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 2',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    $localAnterior = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Antiguo Local',
        'foto' => 'escudo.png',
    ]);

    $visitanteAnterior = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Antiguo Visitante',
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $localAnterior->id,
        'idCategoria' => $categoria->id,
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $visitanteAnterior->id,
        'idCategoria' => $categoria->id,
    ]);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaAnterior->id,
        'idEquipoLocal' => $localAnterior->id,
        'idEquipoVisitante' => $visitanteAnterior->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-03-15 09:00:00',
    ]);

    $localUltimo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Ultimo Local',
        'foto' => 'escudo.png',
    ]);

    $visitanteUltimo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Ultimo Visitante',
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $localUltimo->id,
        'idCategoria' => $categoria->id,
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $visitanteUltimo->id,
        'idCategoria' => $categoria->id,
    ]);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaUltima->id,
        'idEquipoLocal' => $localUltimo->id,
        'idEquipoVisitante' => $visitanteUltimo->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 2,
        'jugado' => 0,
        'horario_datetime' => '2026-03-16 10:30:00',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.imprimir-fixture-arbitros-partidos', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('Fecha 2')
        ->assertSee('Mitai Ultimo Local')
        ->assertSee('Mitai Ultimo Visitante')
        ->assertDontSee('Fecha 1')
        ->assertDontSee('Mitai Antiguo Local')
        ->assertDontSee('Mitai Antiguo Visitante');
});
