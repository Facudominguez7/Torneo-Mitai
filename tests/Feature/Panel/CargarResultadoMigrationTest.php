<?php

// Feature tests for the "cargar resultado" screen migration (T5,
// panel-admin-redesign). Panel.cargar-resultado is shared between the
// regular partido flow (cargar-resultado) and the instancia final flow
// (cargar-resultado-instancia, out of scope for T6 but sharing this exact
// view file) — both are covered here to make sure the restyle keeps every
// input name, the penales fields only in instancia mode, and the
// Grupo/"Modo instancia final" badge.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;

function crearAdminCargarResultadoMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el formulario de carga de resultado regular con el badge de Grupo', function () {
    $admin = crearAdminCargarResultadoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Cargar Resultado Migration']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 17 Migration']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo Cargar Resultado']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha Cargar Resultado']);
    $local = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Local Migration', 'foto' => 'local.png']);
    $visitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Visitante Migration', 'foto' => 'visitante.png']);
    $partido = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'horario_datetime' => '2026-05-10 15:00:00',
        'cancha' => 3,
        'jugado' => 0,
    ]);

    $response = $this->actingAs($admin)->get(route('cargar-resultado', [
        'idPartido' => $partido->id,
        'idEdicion' => $edicion->id,
    ]));

    $response->assertOk()
        ->assertSee('Grupo: '.$grupo->nombre)
        ->assertDontSee('Modo instancia final')
        ->assertSee('name="golesEquipoLocal"', false)
        ->assertSee('name="golesEquipoVisitante"', false)
        ->assertSee('Local Migration')
        ->assertSee('Visitante Migration')
        ->assertSee('name="idGrupo" value="'.$grupo->id.'"', false)
        ->assertSee('name="idEdicion"', false)
        ->assertSee('name="origen"', false)
        ->assertSee('name="retorno_idGrupo"', false)
        ->assertSee('name="retorno_fecha"', false)
        ->assertSee('action="'.e(route('cargar-resultado', ['idPartido' => $partido->id, 'idEdicion' => $edicion->id])).'"', false)
        ->assertSee('Cancha 3')
        ->assertDontSee('name="resultadoGlobal"', false)
        ->assertDontSee('name="penalesEquipoLocal"', false);
});

it('renderiza el formulario de carga de resultado de instancia final con penales y sin el select de grupo', function () {
    $admin = crearAdminCargarResultadoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Cargar Resultado Instancia Migration']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 18 Migration']);
    $fase = Fase::create(['nombre' => 'Final Migration']);
    $local = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Local Instancia Migration', 'foto' => 'local.png']);
    $visitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Visitante Instancia Migration', 'foto' => 'visitante.png']);
    $partido = InstanciaFinal::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'horario' => '2026-05-11 16:00:00',
        'cancha' => '5',
    ]);

    $response = $this->actingAs($admin)->get(route('cargar-resultado-instancia', [
        'idPartido' => $partido->id,
        'idEdicion' => $edicion->id,
        'tipo' => 'instancia_final',
    ]));

    $response->assertOk()
        ->assertSee('Modo instancia final')
        ->assertSee('name="resultadoGlobal"', false)
        ->assertSee('name="penalesEquipoLocal"', false)
        ->assertSee('name="penalesEquipoVisitante"', false)
        ->assertSee('id="containerPenales"', false)
        ->assertDontSee('<select name="idGrupo"', false);
});

it('mantiene el formulario dentro de una tarjeta blanca sin labels blancos', function () {
    $admin = crearAdminCargarResultadoMigration();
    $edicion = Edicion::create(['nombre' => 'Edicion Cargar Resultado Card Migration']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 19 Migration']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo Card Migration']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha Card Migration']);
    $local = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Local Card Migration', 'foto' => 'local.png']);
    $visitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Visitante Card Migration', 'foto' => 'visitante.png']);
    $partido = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $edicion->id,
        'horario_datetime' => '2026-05-12 15:00:00',
        'cancha' => 2,
        'jugado' => 0,
    ]);

    $response = $this->actingAs($admin)->get(route('cargar-resultado', [
        'idPartido' => $partido->id,
        'idEdicion' => $edicion->id,
    ]));

    $response->assertOk()
        ->assertSee('rounded-xl border border-gray-200 bg-white', false)
        ->assertDontSee('block text-base font-medium text-white', false);
});
