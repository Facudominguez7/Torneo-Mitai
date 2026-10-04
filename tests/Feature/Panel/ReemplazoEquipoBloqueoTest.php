<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\TablaPosicion;
use App\Models\User;
use App\Services\PartidoService;
use App\Services\ReemplazadorEquipo;

if (! function_exists('escenarioBloqueoReemplazo')) {
    /**
     * Escenario: "Viejo" jugó (2-1 a "Rival") y fue reemplazado por "Nuevo".
     * "Viejo" tenía además un partido y una final pendientes, que pasan a "Nuevo".
     *
     * @return array<string, mixed>
     */
    function escenarioBloqueoReemplazo(): array
    {
        $edicion = Edicion::create(['nombre' => 'Edicion Bloqueo']);
        $edicion->id = 100;
        $edicion->save();
        $categoria = Categoria::create(['idEdicion' => 100, 'nombreCategoria' => 'Sub 12']);
        $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => 100, 'nombre' => 'Grupo A']);
        $fecha = Fecha::create(['idEdicion' => 100, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);
        $fase = Fase::create(['nombre' => 'Semifinal']);

        $crear = function (string $nombre, bool $enGrupo) use ($categoria, $grupo): Equipo {
            $equipo = Equipo::create(['nombre' => $nombre, 'foto' => 'e.png', 'idCategoria' => $categoria->id, 'idEdicion' => 100]);
            EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => 100, 'idCategoria' => $categoria->id, 'golesContra' => 0]);
            if ($enGrupo) {
                EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $grupo->id]);
            }

            return $equipo;
        };

        $viejo = $crear('Viejo', true);
        $rival = $crear('Rival', true);
        $nuevo = $crear('Nuevo', false);

        $base = [
            'idEdicion' => 100,
            'idCategoria' => $categoria->id,
        ];

        $jugado = Partido::create($base + [
            'idFechas' => $fecha->id, 'idEquipoLocal' => $viejo->id, 'idEquipoVisitante' => $rival->id,
            'idGrupo' => $grupo->id, 'horario_datetime' => '2026-04-05 10:00:00', 'cancha' => 1,
            'jugado' => 1, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 1,
        ]);
        $pendiente = Partido::create($base + [
            'idFechas' => $fecha->id, 'idEquipoLocal' => $rival->id, 'idEquipoVisitante' => $viejo->id,
            'idGrupo' => $grupo->id, 'horario_datetime' => '2026-04-12 10:00:00', 'cancha' => 1, 'jugado' => 0,
        ]);
        $finalJugada = InstanciaFinal::create($base + [
            'idFase' => $fase->id, 'idEquipoLocal' => $viejo->id, 'idEquipoVisitante' => $rival->id,
            'horario' => '2026-05-05 10:00:00', 'cancha' => 1, 'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0,
        ]);
        $finalPendiente = InstanciaFinal::create($base + [
            'idFase' => $fase->id, 'idEquipoLocal' => $rival->id, 'idEquipoVisitante' => $viejo->id,
            'horario' => '2026-05-12 10:00:00', 'cancha' => 1,
        ]);

        TablaPosicion::create([
            'idGrupo' => $grupo->id, 'idEquipo' => $viejo->id, 'idEdicion' => 100,
            'puntos' => 10, 'jugado' => 4, 'ganado' => 3, 'empatado' => 1, 'perdido' => 0,
            'golesFavor' => 8, 'golesContra' => 3, 'diferenciaGoles' => 5,
        ]);
        TablaPosicion::create([
            'idGrupo' => $grupo->id, 'idEquipo' => $rival->id, 'idEdicion' => 100,
            'puntos' => 3, 'jugado' => 4, 'ganado' => 1, 'empatado' => 0, 'perdido' => 3,
            'golesFavor' => 3, 'golesContra' => 8, 'diferenciaGoles' => -5,
        ]);

        app(ReemplazadorEquipo::class)->reemplazar(100, $categoria->id, $viejo->id, $nuevo->id, null);

        return compact('edicion', 'categoria', 'grupo', 'viejo', 'rival', 'nuevo', 'jugado', 'pendiente', 'finalJugada', 'finalPendiente');
    }

    function peticionAdminReemplazo(mixed $test): mixed
    {
        $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);

        return $test->actingAs($admin)->withSession(['_token' => 'x']);
    }
}

it('rechaza editar el resultado de un partido jugado del reemplazado', function () {
    $e = escenarioBloqueoReemplazo();

    $response = peticionAdminReemplazo($this)->post(route('cargar-resultado'), [
        '_token' => 'x',
        'idPartido' => $e['jugado']->id,
        'idEdicion' => 100,
        'golesEquipoLocal' => 5,
        'golesEquipoVisitante' => 0,
    ]);

    $response->assertSessionHas('error');
    expect(session('error'))->toContain('reemplazado');
    $jugado = $e['jugado']->fresh();
    expect([$jugado->golesEquipoLocal, $jugado->golesEquipoVisitante])->toBe([2, 1]);
    expect(TablaPosicion::where('idEquipo', $e['rival']->id)->value('puntos'))->toBe(3);
    expect(TablaPosicion::where('idEquipo', $e['nuevo']->id)->value('puntos'))->toBe(10);
});

it('rechaza eliminar un partido jugado del reemplazado', function () {
    $e = escenarioBloqueoReemplazo();

    $response = peticionAdminReemplazo($this)->delete(route('partido.destroy', ['partido' => $e['jugado']->id, 'idEdicion' => 100]), ['_token' => 'x']);

    $response->assertSessionHas('error');
    expect(session('error'))->toContain('reemplazado');
    expect(Partido::find($e['jugado']->id))->not->toBeNull();
    expect(TablaPosicion::where('idEquipo', $e['nuevo']->id)->value('puntos'))->toBe(10);
});

it('rechaza editar el resultado de una instancia final jugada del reemplazado', function () {
    $e = escenarioBloqueoReemplazo();

    $response = peticionAdminReemplazo($this)->post(route('cargar-resultado-instancia'), [
        '_token' => 'x',
        'idPartido' => $e['finalJugada']->id,
        'idEdicion' => 100,
        'golesEquipoLocal' => 4,
        'golesEquipoVisitante' => 4,
    ]);

    $response->assertSessionHas('error');
    $final = $e['finalJugada']->fresh();
    expect([$final->golesEquipoLocal, $final->golesEquipoVisitante])->toBe([1, 0]);
});

it('rechaza eliminar y cambiar equipos de una instancia final jugada del reemplazado', function () {
    $e = escenarioBloqueoReemplazo();

    $respuestaBorrado = peticionAdminReemplazo($this)->delete(route('instancia_final.destroy', ['instancia_final' => $e['finalJugada']->id, 'idEdicion' => 100]), ['_token' => 'x']);
    $respuestaBorrado->assertSessionHas('error');
    expect(InstanciaFinal::find($e['finalJugada']->id))->not->toBeNull();

    $respuestaCambio = peticionAdminReemplazo($this)->patch(route('instancia_final.update', $e['finalJugada']->id), [
        '_token' => 'x',
        'idCategoria' => $e['categoria']->id,
        'idEdicion' => 100,
        'idEquipoLocal' => $e['nuevo']->id,
        'idEquipoVisitante' => $e['rival']->id,
        'idFase' => $e['finalJugada']->idFase,
        'horario' => '2026-05-05T10:00',
        'cancha' => 1,
    ]);
    $respuestaCambio->assertSessionHas('error');
    expect($e['finalJugada']->fresh()->idEquipoLocal)->toBe($e['viejo']->id);
});

it('carga el resultado de un partido reasignado y actualiza la fila heredada de forma incremental', function () {
    $e = escenarioBloqueoReemplazo();

    $response = peticionAdminReemplazo($this)->post(route('cargar-resultado'), [
        '_token' => 'x',
        'idPartido' => $e['pendiente']->id,
        'idEdicion' => 100,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 2,
    ]);

    $response->assertSessionMissing('error');
    $fila = TablaPosicion::where('idEquipo', $e['nuevo']->id)->where('idGrupo', $e['grupo']->id)->first();
    expect($fila->puntos)->toBe(13);
    expect($fila->jugado)->toBe(5);
    expect($fila->golesFavor)->toBe(10);
    expect(TablaPosicion::where('idEquipo', $e['viejo']->id)->exists())->toBeFalse();
    expect(TablaPosicion::where('idEquipo', $e['rival']->id)->value('puntos'))->toBe(3);
    expect($e['pendiente']->fresh()->jugado)->toBe(1);
});

it('carga el resultado de una instancia final reasignada', function () {
    $e = escenarioBloqueoReemplazo();

    $response = peticionAdminReemplazo($this)->post(route('cargar-resultado-instancia'), [
        '_token' => 'x',
        'idPartido' => $e['finalPendiente']->id,
        'idEdicion' => 100,
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 3,
    ]);

    $response->assertSessionMissing('error');
    $final = $e['finalPendiente']->fresh();
    expect([$final->idEquipoVisitante, $final->golesEquipoVisitante])->toBe([$e['nuevo']->id, 3]);
});

it('mantiene editable un partido jugado cuando ningun equipo fue reemplazado', function () {
    $e = escenarioBloqueoReemplazo();
    $otro = Equipo::create(['nombre' => 'Otro', 'foto' => 'e.png', 'idCategoria' => $e['categoria']->id, 'idEdicion' => 100]);
    EquipoEdicion::create(['idEquipo' => $otro->id, 'idEdicion' => 100, 'idCategoria' => $e['categoria']->id, 'golesContra' => 0]);
    $partido = Partido::create([
        'idEdicion' => 100, 'idCategoria' => $e['categoria']->id, 'idFechas' => $e['pendiente']->idFechas,
        'idEquipoLocal' => $e['rival']->id, 'idEquipoVisitante' => $otro->id, 'idGrupo' => $e['grupo']->id,
        'horario_datetime' => '2026-04-19 10:00:00', 'cancha' => 1, 'jugado' => 1,
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 1,
    ]);

    $response = peticionAdminReemplazo($this)->post(route('cargar-resultado'), [
        '_token' => 'x',
        'idPartido' => $partido->id,
        'idEdicion' => 100,
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
    ]);

    $response->assertSessionMissing('error');
    expect($partido->fresh()->golesEquipoLocal)->toBe(2);
});

it('permite editar y borrar un partido a medio cargar de un equipo ya reemplazado', function () {
    $e = escenarioBloqueoReemplazo();
    $medioCargado = Partido::create([
        'idEdicion' => 100, 'idCategoria' => $e['categoria']->id, 'idFechas' => $e['pendiente']->idFechas,
        'idEquipoLocal' => $e['viejo']->id, 'idEquipoVisitante' => $e['rival']->id, 'idGrupo' => $e['grupo']->id,
        'horario_datetime' => '2026-04-26 10:00:00', 'cancha' => 1, 'jugado' => 0,
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0,
    ]);

    expect(app(PartidoService::class)->resultadoBloqueadoPorReemplazo($medioCargado))->toBeFalse();

    $response = peticionAdminReemplazo($this)->delete(route('partido.destroy', ['partido' => $medioCargado->id, 'idEdicion' => 100]), ['_token' => 'x']);

    $response->assertSessionMissing('error');
    expect(Partido::find($medioCargado->id))->toBeNull();
});

it('permite borrar una instancia final a medio cargar de un equipo ya reemplazado', function () {
    $e = escenarioBloqueoReemplazo();
    $final = InstanciaFinal::create([
        'idEdicion' => 100, 'idCategoria' => $e['categoria']->id, 'idFase' => $e['finalJugada']->idFase,
        'idEquipoLocal' => $e['viejo']->id, 'idEquipoVisitante' => $e['rival']->id,
        'horario' => '2026-06-05 10:00:00', 'cancha' => 1, 'golesEquipoLocal' => 1,
    ]);

    expect(app(PartidoService::class)->resultadoBloqueadoPorReemplazo($final))->toBeFalse();
});

it('sigue bloqueando un partido jugado con resultado aplicado del reemplazado', function () {
    $e = escenarioBloqueoReemplazo();

    expect(app(PartidoService::class)->resultadoBloqueadoPorReemplazo($e['jugado']))->toBeTrue();
    expect(app(PartidoService::class)->resultadoBloqueadoPorReemplazo($e['finalJugada']))->toBeTrue();
});
