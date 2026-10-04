<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\PlanillaJugador;
use App\Services\ReemplazadorEquipo;
use App\Services\ValidadorReglasJugador;

if (! function_exists('escenarioJugadorReemplazo')) {
    /**
     * @return array<string, mixed>
     */
    function escenarioJugadorReemplazo(): array
    {
        $edicion = Edicion::create(['nombre' => 'Edicion Jugadores', 'jugadores_por_planilla' => 30]);
        $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria 2014']);
        $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

        $crear = function (string $nombre, bool $enGrupo) use ($edicion, $categoria, $grupo): Equipo {
            $equipo = Equipo::create(['nombre' => $nombre, 'foto' => 'e.png', 'idCategoria' => $categoria->id, 'idEdicion' => $edicion->id]);
            EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'golesContra' => 0]);
            if ($enGrupo) {
                EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $grupo->id]);
            }

            return $equipo;
        };

        return [
            'edicion' => $edicion,
            'categoria' => $categoria,
            'a' => $crear('Equipo A', true),
            'b' => $crear('Equipo B', false),
            'c' => $crear('Equipo C', false),
            'ajeno' => $crear('Equipo Ajeno', true),
        ];
    }

    function planillaDeJugadorReemplazo(array $e, string $dni, Equipo $equipo): void
    {
        Jugador::firstOrCreate(['dni' => $dni], ['nombre' => 'Jugador', 'apellido' => $dni]);

        PlanillaJugador::create([
            'dni_jugador' => $dni,
            'idEquipo' => $equipo->id,
            'idCategoria' => $e['categoria']->id,
            'partido_id' => 1,
            'partido_type' => 'App\\Models\\Partido',
            'idEdicion' => $e['edicion']->id,
            'numero_camiseta' => 9,
            'goles' => 0,
            'asistio' => true,
        ]);
    }

    function validarJugadorReemplazo(array $e, string $dni, Equipo $destino): ?array
    {
        return app(ValidadorReglasJugador::class)->validarJugador($dni, $destino->id, $e['edicion']->id, $e['categoria']->id, '2015-03-10');
    }
}

it('bloquea a un jugador con planillas en un equipo sin relacion de reemplazo', function () {
    $e = escenarioJugadorReemplazo();
    planillaDeJugadorReemplazo($e, '30111222', $e['ajeno']);

    $resultado = validarJugadorReemplazo($e, '30111222', $e['b']);

    expect($resultado)->not->toBeNull();
    expect($resultado['message'])->toContain('ya jugó para el equipo');
});

it('permite que un jugador del reemplazado se anote en el reemplazante y viceversa', function () {
    $e = escenarioJugadorReemplazo();
    app(ReemplazadorEquipo::class)->reemplazar($e['edicion']->id, $e['categoria']->id, $e['a']->id, $e['b']->id, null);

    planillaDeJugadorReemplazo($e, '30111222', $e['a']);
    expect(validarJugadorReemplazo($e, '30111222', $e['b']))->toBeNull();

    planillaDeJugadorReemplazo($e, '30333444', $e['b']);
    expect(validarJugadorReemplazo($e, '30333444', $e['a']))->toBeNull();
});

it('sigue la cadena de reemplazos A por B y B por C', function () {
    $e = escenarioJugadorReemplazo();
    $servicio = app(ReemplazadorEquipo::class);
    $servicio->reemplazar($e['edicion']->id, $e['categoria']->id, $e['a']->id, $e['b']->id, null);
    $servicio->reemplazar($e['edicion']->id, $e['categoria']->id, $e['b']->id, $e['c']->id, null);

    planillaDeJugadorReemplazo($e, '30111222', $e['a']);

    expect(validarJugadorReemplazo($e, '30111222', $e['c']))->toBeNull();
});

it('sigue bloqueando a un jugador de un equipo ajeno aunque exista un reemplazo en la categoria', function () {
    $e = escenarioJugadorReemplazo();
    app(ReemplazadorEquipo::class)->reemplazar($e['edicion']->id, $e['categoria']->id, $e['a']->id, $e['b']->id, null);
    planillaDeJugadorReemplazo($e, '30555666', $e['ajeno']);

    $resultado = validarJugadorReemplazo($e, '30555666', $e['b']);

    expect($resultado)->not->toBeNull();
    expect($resultado['message'])->toContain('ya jugó para el equipo');
});

it('mantiene el bloqueo por lista de buena fe activa en el reemplazado', function () {
    $e = escenarioJugadorReemplazo();
    app(ReemplazadorEquipo::class)->reemplazar($e['edicion']->id, $e['categoria']->id, $e['a']->id, $e['b']->id, null);
    Jugador::create(['dni' => '30777888', 'nombre' => 'Jugador', 'apellido' => 'Lista']);
    ListaBuenaFe::create([
        'dni_jugador' => '30777888',
        'fecha_nacimiento' => '2015-03-10',
        'idEquipo' => $e['a']->id,
        'idEdicion' => $e['edicion']->id,
        'idCategoria' => $e['categoria']->id,
        'activo' => true,
    ]);

    $resultado = validarJugadorReemplazo($e, '30777888', $e['b']);

    expect($resultado['message'])->toContain('Lista de Buena Fe');
});
