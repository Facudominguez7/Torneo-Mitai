<?php

// Los cobros de la planilla son información interna: el sitio público no los
// enlaza ni los muestra, tampoco cuando navega una cuenta de administrador.
// El acceso a los cobros de cada partido queda en el panel.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;

it('no muestra los cobros de los partidos en el sitio publico a un administrador', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Cobros Portal']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Grupo A']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);
    $local = Equipo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Leones', 'foto' => 'leones.png']);
    $visitante = Equipo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Tigres', 'foto' => 'tigres.png']);
    $partido = Partido::create([
        'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'idGrupo' => $grupo->id,
        'idFechas' => $fecha->id, 'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
        'jugado' => 1, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 1,
        'horario_datetime' => '2025-01-01 12:00:00', 'cancha' => 1,
    ]);
    $final = InstanciaFinal::create([
        'idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'idFase' => Fase::create(['nombre' => 'Final'])->id,
        'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0, 'horario' => now()->subDay(), 'cancha' => '1',
    ]);
    $admin = User::factory()->create(['rol' => 'admin']);

    $fixture = $this->actingAs($admin)->get(route('fixture', ['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'idFecha' => $fecha->id]));
    $fixture->assertOk()->assertSee('id="partido-'.$partido->id.'"', false);

    $finales = $this->actingAs($admin)->get(route('instancias-finales', ['idEdicion' => $edicion->id]));
    $finales->assertOk()->assertSee('id="partido-'.$final->id.'"', false);

    foreach ([$fixture, $finales] as $respuesta) {
        $respuesta->assertDontSee('Cobros de este partido')
            ->assertDontSee(route('cobros.index'), false);
    }
});
