<?php

// Buscador de jugador por DNI en el header del panel, al lado de "Mitaí Admin".
// Usa la misma búsqueda que Consulta de jugador y muestra el resultado en un
// modal: el fragmento de /consulta-jugador/rapida reutiliza el mismo parcial.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearAdminBuscadorJugador(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearJugadorConPlanillaBuscador(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Buscador Header']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 15 Buscador']);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 1']);
    $local = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Buscador Local', 'foto' => 'a.png']);
    $visitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Buscador Visitante', 'foto' => 'b.png']);
    $partido = Partido::create([
        'idCategoria' => $categoria->id, 'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
        'idEdicion' => $edicion->id, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0,
        'cancha' => 1, 'jugado' => 1, 'horario_datetime' => '2026-05-10 10:00:00',
    ]);

    Jugador::create(['dni' => 40500001, 'nombre' => 'Lucas', 'apellido' => 'Benitez']);
    PlanillaJugador::create([
        'partido_id' => $partido->id, 'partido_type' => Partido::class, 'dni_jugador' => 40500001,
        'idEquipo' => $local->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id,
        'numero_camiseta' => 10, 'goles' => 2, 'asistio' => true, 'tarjeta_roja' => false,
    ]);

    return compact('edicion', 'categoria', 'local', 'partido');
}

it('muestra el buscador de jugador por DNI al lado de Mitai Admin en todo el panel', function () {
    $contexto = crearJugadorConPlanillaBuscador();

    $html = $this->actingAs(crearAdminBuscadorJugador())
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-buscador-jugador')
        ->toContain('action="'.route('jugador.buscar').'"')
        ->toContain('data-url-rapida="'.route('jugador.consulta-rapida').'"')
        ->toContain('id="buscador-jugador-dni"')
        ->toContain('name="idEdicion" value="'.$contexto['edicion']->id.'"')
        ->toContain('id="buscador-jugador-modal"');

    expect(strpos($html, 'Mitaí Admin'))->toBeLessThan(strpos($html, 'data-buscador-jugador'));
});

it('devuelve en el modal la misma informacion que la consulta de jugador', function () {
    $contexto = crearJugadorConPlanillaBuscador();

    $respuesta = $this->actingAs(crearAdminBuscadorJugador())
        ->get(route('jugador.consulta-rapida', ['dni' => 40500001, 'idEdicion' => $contexto['edicion']->id]))
        ->assertOk();

    $respuesta->assertSee('Benitez, Lucas')
        ->assertSee('DNI: 40500001')
        ->assertSee('Buscador Local')
        ->assertSee('Sub 15 Buscador')
        ->assertSee('Partidos donde Hizo Goles')
        ->assertSeeInOrder(['Historial Completo de Partidos', 'P-'.$contexto['partido']->id, '2 - 0'])
        ->assertSee('data-buscador-jugador-encontrado', false)
        ->assertSee('action="'.route('jugador.buscar').'"', false)
        ->assertSee('name="dni" value="40500001"', false)
        ->assertSee('Ver en Consulta de jugador')
        ->assertDontSee('data-panel-edicion-header', false);
});

it('informa en el modal cuando no encuentra al jugador', function () {
    $contexto = crearJugadorConPlanillaBuscador();

    $this->actingAs(crearAdminBuscadorJugador())
        ->get(route('jugador.consulta-rapida', ['dni' => 99999999, 'idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('No se encontró ningún jugador con el DNI: 99999999')
        ->assertSee('data-buscador-jugador-sin-resultado', false)
        ->assertDontSee('data-buscador-jugador-encontrado', false);
});

it('rechaza un DNI invalido sin buscar', function (string $dni) {
    $contexto = crearJugadorConPlanillaBuscador();

    $this->actingAs(crearAdminBuscadorJugador())
        ->get(route('jugador.consulta-rapida', ['dni' => $dni, 'idEdicion' => $contexto['edicion']->id]))
        ->assertUnprocessable()
        ->assertSee('Ingresá un DNI válido, solo con números.')
        ->assertDontSee('data-buscador-jugador-encontrado', false);
})->with([
    'vacio' => [''],
    'con letras' => ['40a50'],
]);

it('busca dentro de la edicion activa del panel', function () {
    $contexto = crearJugadorConPlanillaBuscador();
    $otraEdicion = Edicion::create(['nombre' => 'Edicion Sin Partidos']);

    $this->actingAs(crearAdminBuscadorJugador())
        ->get(route('jugador.consulta-rapida', ['dni' => 40500001, 'idEdicion' => $otraEdicion->id]))
        ->assertOk()
        ->assertSee('Benitez, Lucas')
        ->assertSee('Edicion Sin Partidos')
        ->assertDontSee('P-'.$contexto['partido']->id);
});

it('no permite usar el buscador a quien no es administrador', function (string $rol) {
    $usuario = User::factory()->create(['rol' => $rol, 'email_verified_at' => now()]);

    $this->actingAs($usuario)
        ->get(route('jugador.consulta-rapida', ['dni' => 40500001]))
        ->assertRedirect('/');
})->with(['regular', 'editor']);

it('pide iniciar sesion para usar el buscador', function () {
    $this->get(route('jugador.consulta-rapida', ['dni' => 40500001]))
        ->assertRedirect(route('login'));
});

it('la consulta de jugador completa sigue mostrando los resultados', function () {
    $contexto = crearJugadorConPlanillaBuscador();
    $token = 'token-buscador-header';

    $this->actingAs(crearAdminBuscadorJugador())
        ->withSession(['_token' => $token])
        ->post(route('jugador.buscar'), ['_token' => $token, 'dni' => 40500001, 'idEdicion' => $contexto['edicion']->id])
        ->assertOk()
        ->assertSee('Consulta de Jugador')
        ->assertSee('Benitez, Lucas')
        ->assertSeeInOrder(['Historial Completo de Partidos', 'P-'.$contexto['partido']->id, '2 - 0']);
});
