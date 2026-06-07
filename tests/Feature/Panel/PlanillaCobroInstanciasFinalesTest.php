<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;

function crearContextoPlanillaCobro(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Planilla Cobro',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 13',
    ]);

    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Cobro',
    ]);

    $fase = Fase::create([
        'nombre' => 'Final',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Cobro',
    ]);

    $equipos = collect([
        'Regular A' => crearEquipoPlanillaCobro($edicion, $categoria, 'Regular A'),
        'Regular B' => crearEquipoPlanillaCobro($edicion, $categoria, 'Regular B'),
        'Regular Tarde A' => crearEquipoPlanillaCobro($edicion, $categoria, 'Regular Tarde A'),
        'Regular Tarde B' => crearEquipoPlanillaCobro($edicion, $categoria, 'Regular Tarde B'),
        'Final A' => crearEquipoPlanillaCobro($edicion, $categoria, 'Final A'),
        'Final B' => crearEquipoPlanillaCobro($edicion, $categoria, 'Final B'),
    ]);

    return compact('admin', 'edicion', 'categoria', 'fecha', 'fase', 'grupo', 'equipos');
}

function crearEquipoPlanillaCobro(Edicion $edicion, Categoria $categoria, string $nombre): Equipo
{
    return Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);
}

function crearPartidoPlanillaCobro(array $contexto, Equipo $local, Equipo $visitante, string $horario): Partido
{
    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => $horario,
    ]);
}

function crearInstanciaFinalPlanillaCobro(array $contexto, Equipo $local, Equipo $visitante, string $horario): InstanciaFinal
{
    return InstanciaFinal::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFase' => $contexto['fase']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idEdicion' => $contexto['edicion']->id,
        'cancha' => '1',
        'horario' => $horario,
    ]);
}

it('usa la ultima fecha de partidos regulares cuando es la mas reciente', function () {
    $contexto = crearContextoPlanillaCobro();

    crearInstanciaFinalPlanillaCobro($contexto, $contexto['equipos']['Final A'], $contexto['equipos']['Final B'], '2026-04-10 09:00:00');
    crearPartidoPlanillaCobro($contexto, $contexto['equipos']['Regular Tarde A'], $contexto['equipos']['Regular Tarde B'], '2026-04-17 11:00:00');

    $this->actingAs($contexto['admin'])
        ->get(route('admin.planilla-cobro', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertViewHas('fechaJornada', '17/04/2026')
        ->assertSee('Regular Tarde A')
        ->assertSee('Regular Tarde B')
        ->assertDontSee('Final A')
        ->assertDontSee('Final B');
});

it('usa la ultima fecha de instancias finales cuando no hay partidos regulares en esa fecha', function () {
    $contexto = crearContextoPlanillaCobro();

    crearPartidoPlanillaCobro($contexto, $contexto['equipos']['Regular A'], $contexto['equipos']['Regular B'], '2026-04-10 09:00:00');
    crearInstanciaFinalPlanillaCobro($contexto, $contexto['equipos']['Final A'], $contexto['equipos']['Final B'], '2026-04-17 11:00:00');

    $this->actingAs($contexto['admin'])
        ->get(route('admin.planilla-cobro', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertViewHas('fechaJornada', '17/04/2026')
        ->assertSee('Final A')
        ->assertSee('Final B')
        ->assertDontSee('Regular A')
        ->assertDontSee('Regular B');
});

it('agrupa equipos de partidos regulares e instancias finales de la misma fecha', function () {
    $contexto = crearContextoPlanillaCobro();

    crearPartidoPlanillaCobro($contexto, $contexto['equipos']['Regular A'], $contexto['equipos']['Regular B'], '2026-04-17 09:00:00');
    crearInstanciaFinalPlanillaCobro($contexto, $contexto['equipos']['Final A'], $contexto['equipos']['Final B'], '2026-04-17 09:00:00');

    $this->actingAs($contexto['admin'])
        ->get(route('admin.planilla-cobro', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertViewHas('agrupado', function (array $agrupado): bool {
            return ($agrupado['09:00']['Sub 13'] ?? []) === [
                'Regular A',
                'Regular B',
                'Final A',
                'Final B',
            ];
        })
        ->assertSee('Regular A')
        ->assertSee('Regular B')
        ->assertSee('Final A')
        ->assertSee('Final B');
});
