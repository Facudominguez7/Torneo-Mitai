<?php

// Regresión: el detalle del equipo daba 500 porque show() usaba
// whereHas('ediciones') sobre EquipoEdicion, que no tiene esa relación.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\User;
use App\Services\Panel\DetalleEquipo;

it('muestra el detalle del equipo con la edicion y la categoria elegidas', function () {
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $edicion = Edicion::create(['nombre' => 'Edicion Detalle Equipo']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria Detalle']);
    $equipo = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo Con Detalle', 'foto' => 'e.png']);
    EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $this->actingAs($admin)
        ->get(route('equipo.show', ['equipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]))
        ->assertOk()
        ->assertSee('Equipo Con Detalle');
});

it('muestra el detalle del equipo aunque no llegue la categoria', function () {
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $edicion = Edicion::create(['nombre' => 'Edicion Detalle Sin Categoria']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria Sin Filtro']);
    $equipo = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Equipo Sin Filtro', 'foto' => 'e.png']);
    EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]);

    $this->actingAs($admin)
        ->get(route('equipo.show', ['equipo' => $equipo->id, 'idEdicion' => $edicion->id]))
        ->assertOk()
        ->assertSee('Equipo Sin Filtro');
});

// Detalle del equipo: partidos jugados, estadísticas y goleador de la edición.

use App\Models\Copa;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\TablaGoleador;
use App\Models\TablaPosicion;

function escenarioDetalleEquipo(): array
{
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $edicion = Edicion::forceCreate(['id' => 100 + random_int(1, 900000), 'nombre' => 'Edicion Detalle Partidos']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria DP']);
    $crear = fn (string $nombre) => Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => $nombre, 'foto' => 'x.png']);
    $equipo = $crear('Equipo Protagonista');
    $grupo = Grupo::create(['nombre' => 'Grupo Zeta', 'idCategoria' => $categoria->id, 'idEdicion' => $edicion->id]);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 7']);
    $fase = Fase::create(['nombre' => 'Semifinal']);
    $copa = Copa::create(['nombre' => 'Copa Oro']);

    return compact('admin', 'edicion', 'categoria', 'crear', 'equipo', 'grupo', 'fecha', 'fase', 'copa');
}

function partidoDetalleEquipo(array $e, Equipo $local, Equipo $visitante, ?int $gl, ?int $gv, ?int $jugado, string $horario): Partido
{
    return Partido::create([
        'idCategoria' => $e['categoria']->id, 'idFechas' => $e['fecha']->id, 'idGrupo' => $e['grupo']->id,
        'idEdicion' => $e['edicion']->id, 'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
        'golesEquipoLocal' => $gl, 'golesEquipoVisitante' => $gv, 'jugado' => $jugado,
        'horario_datetime' => $horario, 'cancha' => 1,
    ]);
}

function finalDetalleEquipo(array $e, Equipo $local, Equipo $visitante, ?int $gl, ?int $gv, string $horario, array $extra = []): InstanciaFinal
{
    // resultadoGlobal no está en $fillable del modelo (el controlador lo asigna directo).
    $final = (new InstanciaFinal)->forceFill(array_merge([
        'idCategoria' => $e['categoria']->id, 'idCopa' => $e['copa']->id, 'idFase' => $e['fase']->id,
        'idEdicion' => $e['edicion']->id, 'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
        'golesEquipoLocal' => $gl, 'golesEquipoVisitante' => $gv, 'horario' => $horario, 'cancha' => '1',
    ], $extra));
    $final->save();

    return $final;
}

function verDetalleEquipo(array $e)
{
    return test()->actingAs($e['admin'])->get(route('equipo.show', [
        'equipo' => $e['equipo']->id, 'idEdicion' => $e['edicion']->id, 'idCategoria' => $e['categoria']->id,
    ]));
}

it('lista solo partidos jugados del equipo, de partidos y finales, en orden de fecha', function () {
    $e = escenarioDetalleEquipo();
    $a = $e['crear']('Rival Alfa');
    $b = $e['crear']('Rival Beta');
    $c = $e['crear']('Rival Gamma');
    $otro = $e['crear']('Rival Ajeno');

    partidoDetalleEquipo($e, $e['equipo'], $a, 2, 0, 1, '2026-03-10 10:00:00');
    partidoDetalleEquipo($e, $b, $e['equipo'], 1, 1, 1, '2026-03-01 10:00:00');
    partidoDetalleEquipo($e, $e['equipo'], $c, null, null, null, '2026-03-20 10:00:00');
    partidoDetalleEquipo($e, $e['equipo'], $c, 3, null, 1, '2026-03-21 10:00:00');
    partidoDetalleEquipo($e, $a, $b, 4, 4, 1, '2026-03-05 10:00:00');
    finalDetalleEquipo($e, $e['equipo'], $c, 1, 0, '2026-04-01 10:00:00');
    finalDetalleEquipo($e, $e['equipo'], $otro, null, null, '2026-04-02 10:00:00');
    finalDetalleEquipo($e, $a, $b, 5, 5, '2026-04-03 10:00:00');

    $resp = verDetalleEquipo($e)->assertOk();

    $resp->assertSeeInOrder(['Rival Beta', 'Rival Alfa', 'Rival Gamma'], false);
    $html = $resp->getContent();
    $seccion = substr($html, strpos($html, 'data-detalle-partidos'));
    $seccion = substr($seccion, 0, strpos($seccion, 'data-detalle-goleador') ?: null);
    expect($seccion)->toContain('Rival Beta')->toContain('Rival Alfa')->toContain('Rival Gamma')
        ->not->toContain('Rival Ajeno');
    expect(substr_count($seccion, 'data-partido-fila'))->toBe(3);
    expect(strpos($seccion, 'Rival Beta'))->toBeLessThan(strpos($seccion, 'Rival Alfa'));
    expect(strpos($seccion, 'Rival Alfa'))->toBeLessThan(strpos($seccion, 'Rival Gamma'));
    $resp->assertSee('Fecha 7')->assertSee('Grupo Zeta')->assertSee('Semifinal')->assertSee('Copa Oro');
});

it('muestra la insignia G E P y define las finales empatadas por penales o resultado global', function () {
    $e = escenarioDetalleEquipo();
    $r1 = $e['crear']('Rival Gana');
    $r2 = $e['crear']('Rival Empata');
    $r3 = $e['crear']('Rival Pierde');
    $r4 = $e['crear']('Rival Penales');
    $r5 = $e['crear']('Rival Global');

    partidoDetalleEquipo($e, $e['equipo'], $r1, 3, 1, 1, '2026-03-01 10:00:00');
    partidoDetalleEquipo($e, $e['equipo'], $r2, 2, 2, 1, '2026-03-02 10:00:00');
    partidoDetalleEquipo($e, $r3, $e['equipo'], 2, 0, 1, '2026-03-03 10:00:00');
    finalDetalleEquipo($e, $r4, $e['equipo'], 1, 1, '2026-04-01 10:00:00', ['penalesEquipoLocal' => 3, 'penalesEquipoVisitante' => 5]);
    finalDetalleEquipo($e, $e['equipo'], $r5, 0, 0, '2026-04-02 10:00:00', ['resultadoGlobal' => '1-2', 'penalesEquipoLocal' => 4, 'penalesEquipoVisitante' => 1]);

    $detalle = app(DetalleEquipo::class)->obtener($e['equipo'], $e['edicion']->id);
    $res = collect($detalle['partidos'])->pluck('resultado', 'rival.nombre')->all();

    expect($res)->toBe([
        'Rival Gana' => 'G', 'Rival Empata' => 'E', 'Rival Pierde' => 'P', 'Rival Penales' => 'G', 'Rival Global' => 'P',
    ]);

    $html = verDetalleEquipo($e)->assertOk()->getContent();
    expect($html)->toContain('data-resultado="G"')->toContain('data-resultado="E"')->toContain('data-resultado="P"');
    expect($html)->toContain('data-penales');
});

it('calcula las estadisticas por seccion y el total, y muestra la fila de tabla de posiciones', function () {
    $e = escenarioDetalleEquipo();
    $a = $e['crear']('Rival Uno');
    $b = $e['crear']('Rival Dos');

    partidoDetalleEquipo($e, $e['equipo'], $a, 2, 0, 1, '2026-03-01 10:00:00');
    partidoDetalleEquipo($e, $b, $e['equipo'], 3, 1, 1, '2026-03-02 10:00:00');
    finalDetalleEquipo($e, $e['equipo'], $a, 1, 1, '2026-04-01 10:00:00', ['penalesEquipoLocal' => 5, 'penalesEquipoVisitante' => 4]);
    TablaPosicion::create([
        'idGrupo' => $e['grupo']->id, 'idEquipo' => $e['equipo']->id, 'idEdicion' => $e['edicion']->id,
        'puntos' => 37, 'golesFavor' => 3, 'golesContra' => 3, 'diferenciaGoles' => 0,
        'jugado' => 2, 'ganado' => 1, 'perdido' => 1, 'empatado' => 0,
    ]);

    $d = app(DetalleEquipo::class)->obtener($e['equipo'], $e['edicion']->id);

    expect($d['estadisticas']['instanciasFinales'])->toMatchArray(['pj' => 1, 'g' => 1, 'e' => 0, 'p' => 0, 'gf' => 1, 'gc' => 1, 'dg' => 0]);
    expect($d['estadisticas']['total'])->toMatchArray(['pj' => 3, 'g' => 2, 'e' => 0, 'p' => 1, 'gf' => 4, 'gc' => 4, 'dg' => 0]);
    expect($d['estadisticas']['faseGrupos'])->toHaveCount(1);
    expect($d['estadisticas']['faseGrupos'][0])->toMatchArray(['grupo' => 'Grupo Zeta', 'puntos' => 37, 'pj' => 2]);

    verDetalleEquipo($e)->assertOk()->assertSee('data-detalle-estadisticas', false)
        ->assertSee('Fase de grupos')->assertSee('Instancias finales')->assertSee('Total edición')
        ->assertSee('Grupo Zeta')->assertSee('37');
});

it('muestra el goleador del equipo con empates y el ranking de los cinco maximos en orden', function () {
    $e = escenarioDetalleEquipo();
    $crearGol = function (string $dni, string $nombre, int $goles, ?Equipo $equipo = null) use ($e) {
        Jugador::create(['dni' => $dni, 'nombre' => $nombre, 'apellido' => 'Apellido']);
        TablaGoleador::create([
            'idEquipo' => ($equipo ?? $e['equipo'])->id, 'idEdicion' => $e['edicion']->id, 'idCategoria' => $e['categoria']->id,
            'dni_jugador' => $dni, 'nombre' => $nombre, 'cantidadGoles' => $goles,
        ]);
    };
    $ajeno = $e['crear']('Equipo Ajeno');
    $crearGol('30000001', 'Cuarto', 2);
    $crearGol('30000002', 'Lider', 7);
    $crearGol('30000003', 'Segundo', 5);
    $crearGol('30000004', 'Coliderr', 7);
    $crearGol('30000005', 'Tercero', 3);
    $crearGol('30000006', 'Quinto', 1);
    $crearGol('30000007', 'Sexto', 1);
    $crearGol('30000008', 'Delotro', 99, $ajeno);

    $d = app(DetalleEquipo::class)->obtener($e['equipo'], $e['edicion']->id);

    expect($d['goleadores']['maximos'])->toHaveCount(2);
    expect(collect($d['goleadores']['maximos'])->pluck('goles')->unique()->all())->toBe([7]);
    expect($d['goleadores']['ranking'])->toHaveCount(5);
    expect(collect($d['goleadores']['ranking'])->pluck('goles')->all())->toBe([7, 7, 5, 3, 2]);

    $resp = verDetalleEquipo($e)->assertOk();
    $html = $resp->getContent();
    $seccion = substr($html, strpos($html, 'data-detalle-goleador'));
    expect($seccion)->toContain('Lider')->toContain('Coliderr')->not->toContain('Delotro')->not->toContain('Sexto');
    expect(strpos($seccion, 'Segundo'))->toBeLessThan(strpos($seccion, 'Tercero'));
});

it('muestra los estados vacios sin partidos ni goles', function () {
    $e = escenarioDetalleEquipo();

    verDetalleEquipo($e)->assertOk()
        ->assertSee('Este equipo todavía no jugó partidos en esta edición')
        ->assertSee('Todavía no hay goles cargados');
});

it('enlaza la planilla de cada partido jugado', function () {
    $e = escenarioDetalleEquipo();
    $a = $e['crear']('Rival Link');
    $p = partidoDetalleEquipo($e, $e['equipo'], $a, 1, 0, 1, '2026-03-01 10:00:00');

    $url = route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $e['edicion']->id, 'tipoPartido' => 'partido', 'horario' => 0, 'origen' => 'equipo-detalle']);

    verDetalleEquipo($e)->assertOk()->assertSee(e($url), false);
});
