<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\User;

function crearContextoImpresionBuenaFePorFecha(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Impresion Fecha',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 15',
    ]);

    $fechaPrimera = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 1',
    ]);

    $fechaSegunda = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 2',
    ]);

    $equipos = collect([
        'Equipo A' => Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo A', 'foto' => 'a.png']),
        'Equipo B' => Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo B', 'foto' => 'b.png']),
        'Equipo C' => Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo C', 'foto' => 'c.png']),
        'Equipo D' => Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo D', 'foto' => 'd.png']),
        'Equipo E' => Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo E', 'foto' => 'e.png']),
        'Equipo F' => Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo F', 'foto' => 'f.png']),
    ]);

    foreach ($equipos as $equipo) {
        EquipoEdicion::create([
            'idEdicion' => $edicion->id,
            'idEquipo' => $equipo->id,
            'idCategoria' => $categoria->id,
        ]);

        $jugador = Jugador::create([
            'dni' => (string) (40000000 + $equipo->id),
            'nombre' => 'Jugador '.$equipo->nombre,
            'apellido' => 'Test',
            'goles_totales' => 0,
            'partidos_totales' => 0,
            'tarjetas_rojas_totales' => 0,
        ]);

        ListaBuenaFe::create([
            'dni_jugador' => $jugador->dni,
            'idEquipo' => $equipo->id,
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoria->id,
            'fecha_nacimiento' => '2012-01-01',
            'fecha_alta' => now(),
            'activo' => true,
        ]);
    }

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaPrimera->id,
        'idEquipoLocal' => $equipos['Equipo A']->id,
        'idEquipoVisitante' => $equipos['Equipo B']->id,
        'idEdicion' => $edicion->id,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-04-10 09:00:00',
    ]);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaPrimera->id,
        'idEquipoLocal' => $equipos['Equipo C']->id,
        'idEquipoVisitante' => $equipos['Equipo D']->id,
        'idEdicion' => $edicion->id,
        'cancha' => 2,
        'jugado' => 0,
        'horario_datetime' => '2026-04-10 10:00:00',
    ]);

    Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fechaSegunda->id,
        'idEquipoLocal' => $equipos['Equipo E']->id,
        'idEquipoVisitante' => $equipos['Equipo F']->id,
        'idEdicion' => $edicion->id,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-04-17 09:00:00',
    ]);

    return compact('admin', 'edicion', 'categoria', 'fechaPrimera', 'fechaSegunda', 'equipos');
}

it('permite abrir la impresion masiva por fecha', function () {
    $contexto = crearContextoImpresionBuenaFePorFecha();

    $this->actingAs($contexto['admin'])
        ->get(route('lista-buena-fe.imprimir-planillas-fecha', [
            'idEdicion' => $contexto['edicion']->id,
            'idFecha' => $contexto['fechaPrimera']->id,
        ]))
        ->assertOk()
        ->assertSee('Planilla de Partido')
        ->assertSee('Fecha 1');
});

it('solo incluye partidos y equipos de la fecha seleccionada', function () {
    $contexto = crearContextoImpresionBuenaFePorFecha();

    $this->actingAs($contexto['admin'])
        ->get(route('lista-buena-fe.imprimir-planillas-fecha', [
            'idEdicion' => $contexto['edicion']->id,
            'idFecha' => $contexto['fechaPrimera']->id,
        ]))
        ->assertOk()
        ->assertSee('Equipo A')
        ->assertSee('Equipo B')
        ->assertSee('Equipo C')
        ->assertSee('Equipo D')
        ->assertDontSee('Equipo E')
        ->assertDontSee('Equipo F')
        ->assertDontSee('Fecha 2');
});
