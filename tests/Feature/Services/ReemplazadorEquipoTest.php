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
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\ReemplazoEquipo;
use App\Models\TablaPosicion;
use App\Services\PartidoService;
use App\Services\ReemplazadorEquipo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

if (! function_exists('contextoReemplazo')) {
    /**
     * Edición 100 para esquivar la reescritura legacy de ediciones 1..3 en golesContra.
     *
     * @return array{edicion: Edicion, categoria: Categoria, grupo: Grupo, fecha: Fecha}
     */
    function contextoReemplazo(): array
    {
        $edicion = Edicion::create(['nombre' => 'Edicion Reemplazo']);
        $edicion->id = 100;
        $edicion->save();

        $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12']);
        $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
        $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);

        return compact('edicion', 'categoria', 'grupo', 'fecha');
    }

    function equipoReemplazo(array $contexto, string $nombre, bool $enGrupo = true): Equipo
    {
        $equipo = Equipo::create([
            'nombre' => $nombre,
            'foto' => 'escudo.png',
            'idCategoria' => $contexto['categoria']->id,
            'idEdicion' => $contexto['edicion']->id,
        ]);

        EquipoEdicion::create([
            'idEquipo' => $equipo->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'golesContra' => 0,
        ]);

        if ($enGrupo) {
            EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $contexto['grupo']->id]);
        }

        return $equipo;
    }

    function filaPosicionReemplazo(array $contexto, Equipo $equipo, array $valores = []): TablaPosicion
    {
        return TablaPosicion::create(array_merge([
            'idGrupo' => $contexto['grupo']->id,
            'idEquipo' => $equipo->id,
            'idEdicion' => $contexto['edicion']->id,
            'puntos' => 10,
            'jugado' => 5,
            'ganado' => 3,
            'empatado' => 1,
            'perdido' => 1,
            'golesFavor' => 9,
            'golesContra' => 4,
            'diferenciaGoles' => 5,
        ], $valores));
    }

    function partidoReemplazo(array $contexto, Equipo $local, Equipo $visitante, array $atributos = []): Partido
    {
        return Partido::create(array_merge([
            'idFechas' => $contexto['fecha']->id,
            'idEquipoLocal' => $local->id,
            'idEquipoVisitante' => $visitante->id,
            'idGrupo' => $contexto['grupo']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'horario_datetime' => '2026-04-05 10:00:00',
            'cancha' => 1,
            'jugado' => 0,
        ], $atributos));
    }

    function finalReemplazo(array $contexto, Equipo $local, Equipo $visitante, array $atributos = []): InstanciaFinal
    {
        $fase = Fase::firstOrCreate(['nombre' => 'Semifinal']);

        return InstanciaFinal::create(array_merge([
            'idCategoria' => $contexto['categoria']->id,
            'idEdicion' => $contexto['edicion']->id,
            'idFase' => $fase->id,
            'idEquipoLocal' => $local->id,
            'idEquipoVisitante' => $visitante->id,
            'horario' => '2026-05-05 10:00:00',
            'cancha' => 1,
        ], $atributos));
    }

    function planillaReemplazo(Partido|InstanciaFinal $partido, string $dni, int $idEquipo): PlanillaJugador
    {
        Jugador::firstOrCreate(['dni' => $dni], ['nombre' => 'Jugador', 'apellido' => $dni]);

        return PlanillaJugador::create([
            'dni_jugador' => $dni,
            'idEquipo' => $idEquipo,
            'idCategoria' => $partido->idCategoria,
            'partido_id' => $partido->id,
            'partido_type' => $partido::class,
            'idEdicion' => $partido->idEdicion,
            'numero_camiseta' => 10,
            'goles' => 0,
            'asistio' => true,
        ]);
    }
}

function reemplazador(): ReemplazadorEquipo
{
    return app(ReemplazadorEquipo::class);
}

it('mueve la fila de posiciones al reemplazante con los valores exactos', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);
    $fila = filaPosicionReemplazo($c, $viejo);

    $reemplazo = reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null);

    $heredada = TablaPosicion::where('idEquipo', $nuevo->id)->where('idGrupo', $c['grupo']->id)->get();
    expect($heredada)->toHaveCount(1);
    expect($heredada->first()->id)->toBe($fila->id);
    expect($heredada->first()->only(['puntos', 'jugado', 'ganado', 'empatado', 'perdido', 'golesFavor', 'golesContra', 'diferenciaGoles']))
        ->toBe(['puntos' => 10, 'jugado' => 5, 'ganado' => 3, 'empatado' => 1, 'perdido' => 1, 'golesFavor' => 9, 'golesContra' => 4, 'diferenciaGoles' => 5]);
    expect(TablaPosicion::where('idEquipo', $viejo->id)->where('idGrupo', $c['grupo']->id)->exists())->toBeFalse();
    expect($reemplazo)->toBeInstanceOf(ReemplazoEquipo::class);
});

it('intercambia la pertenencia al grupo sin duplicados y registra el reemplazo', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $otro = equipoReemplazo($c, 'Otro');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);

    $reemplazo = reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null);

    expect(EquipoGrupo::where('idGrupo', $c['grupo']->id)->pluck('idEquipo')->sort()->values()->all())
        ->toBe(collect([$otro->id, $nuevo->id])->sort()->values()->all());
    expect(EquipoGrupo::where('idEquipo', $nuevo->id)->count())->toBe(1);
    expect(EquipoGrupo::where('idEquipo', $viejo->id)->count())->toBe(0);

    expect($reemplazo->only(['idEdicion', 'idCategoria', 'idGrupo', 'idEquipoReemplazado', 'idEquipoReemplazante']))
        ->toBe([
            'idEdicion' => 100,
            'idCategoria' => $c['categoria']->id,
            'idGrupo' => $c['grupo']->id,
            'idEquipoReemplazado' => $viejo->id,
            'idEquipoReemplazante' => $nuevo->id,
        ]);
    expect($reemplazo->equipoReemplazado->is($viejo))->toBeTrue();
    expect($reemplazo->equipoReemplazante->is($nuevo))->toBeTrue();
    expect($reemplazo->grupo->is($c['grupo']))->toBeTrue();
});

it('reasigna solo partidos y finales pendientes y deja los aplicados con el reemplazado', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $rival = equipoReemplazo($c, 'Rival');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);

    $pendienteLocal = partidoReemplazo($c, $viejo, $rival);
    $pendienteVisita = partidoReemplazo($c, $rival, $viejo);
    $jugado = partidoReemplazo($c, $viejo, $rival, ['jugado' => 1, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0]);

    $finalPendiente = finalReemplazo($c, $viejo, $rival);
    $finalJugada = finalReemplazo($c, $viejo, $rival, ['golesEquipoLocal' => 3, 'golesEquipoVisitante' => 1]);

    reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null);

    expect($pendienteLocal->fresh()->idEquipoLocal)->toBe($nuevo->id);
    expect($pendienteVisita->fresh()->idEquipoVisitante)->toBe($nuevo->id);
    expect($jugado->fresh()->idEquipoLocal)->toBe($viejo->id);

    expect($finalPendiente->fresh()->idEquipoLocal)->toBe($nuevo->id);
    expect($finalJugada->fresh()->idEquipoLocal)->toBe($viejo->id);
});

it('recalcula golesContra de ambos equipos', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $rival = equipoReemplazo($c, 'Rival');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);
    filaPosicionReemplazo($c, $viejo, ['golesContra' => 4]);
    finalReemplazo($c, $rival, $viejo, ['golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0]);
    EquipoEdicion::where('idEquipo', $viejo->id)->update(['golesContra' => 99]);

    reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null);

    expect((int) EquipoEdicion::where('idEquipo', $nuevo->id)->value('golesContra'))->toBe(4);
    expect((int) EquipoEdicion::where('idEquipo', $viejo->id)->value('golesContra'))->toBe(2);
});

it('crea un equipo nuevo con su inscripcion en la edicion y categoria', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    filaPosicionReemplazo($c, $viejo);

    $reemplazo = reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, null, [
        'nombre' => 'club recien llegado',
        'foto' => 'nuevo-escudo.png',
    ]);

    $nuevo = Equipo::findOrFail($reemplazo->idEquipoReemplazante);
    expect($nuevo->nombre)->toBe('Club Recien Llegado');
    expect($nuevo->foto)->toBe('nuevo-escudo.png');
    expect($nuevo->idInstitucion)->not->toBeNull();
    expect($nuevo->idCategoria)->toBe($c['categoria']->id);
    expect($nuevo->idEdicion)->toBe(100);

    $inscripcion = EquipoEdicion::where('idEquipo', $nuevo->id)->first();
    expect($inscripcion)->not->toBeNull();
    expect($inscripcion->idEdicion)->toBe(100);
    expect($inscripcion->idCategoria)->toBe($c['categoria']->id);
    expect(TablaPosicion::where('idEquipo', $nuevo->id)->value('puntos'))->toBe(10);
    expect(EquipoGrupo::where('idEquipo', $nuevo->id)->where('idGrupo', $c['grupo']->id)->count())->toBe(1);
});

it('acepta un equipo nuevo sin escudo', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');

    $reemplazo = reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, null, ['nombre' => 'Sin Escudo', 'foto' => null]);

    expect(Equipo::findOrFail($reemplazo->idEquipoReemplazante)->foto)->toBeNull();
});

it('rechaza reemplazos invalidos sin modificar datos', function (string $caso, string $mensaje) {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $enGrupo = equipoReemplazo($c, 'Ya en grupo');
    $otraCategoria = Categoria::create(['idEdicion' => 100, 'nombreCategoria' => 'Sub 14']);
    $ajeno = Equipo::create(['nombre' => 'Ajeno', 'foto' => 'a.png', 'idCategoria' => $otraCategoria->id, 'idEdicion' => 100]);
    EquipoEdicion::create(['idEquipo' => $ajeno->id, 'idEdicion' => 100, 'idCategoria' => $otraCategoria->id, 'golesContra' => 0]);
    $libre = equipoReemplazo($c, 'Libre', enGrupo: false);
    filaPosicionReemplazo($c, $viejo);

    $idsExistentes = match ($caso) {
        'ya en grupo' => $enGrupo->id,
        'mismo equipo' => $viejo->id,
        'no inscripto' => $ajeno->id,
        'sin origen' => null,
        'ya reemplazado' => $libre->id,
        'ambos origenes' => $libre->id,
    };
    $nuevo = $caso === 'ambos origenes' ? ['nombre' => 'Equipo nuevo', 'foto' => null] : null;

    if ($caso === 'ya reemplazado') {
        reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $libre->id, null);
        $otroLibre = equipoReemplazo($c, 'Otro libre', enGrupo: false);
        $idsExistentes = $otroLibre->id;
    }

    $antes = [
        TablaPosicion::count(),
        EquipoGrupo::count(),
        ReemplazoEquipo::count(),
        Equipo::count(),
    ];

    expect(fn () => reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $idsExistentes, $nuevo))
        ->toThrow(DomainException::class, $mensaje);

    expect([TablaPosicion::count(), EquipoGrupo::count(), ReemplazoEquipo::count(), Equipo::count()])->toBe($antes);
})->with([
    'reemplazante ya en un grupo' => ['ya en grupo', 'ya pertenece a un grupo'],
    'mismo equipo' => ['mismo equipo', 'mismo equipo'],
    'no inscripto en edicion y categoria' => ['no inscripto', 'no está inscripto'],
    'sin reemplazante' => ['sin origen', 'Indicá un equipo existente o los datos de un equipo nuevo.'],
    'ambos origenes' => ['ambos origenes', 'pero no ambos'],
    'reemplazado ya reemplazado' => ['ya reemplazado', 'ya fue reemplazado'],
]);

it('rechaza un equipo nuevo sin nombre', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');

    expect(fn () => reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, null, ['nombre' => '  ', 'foto' => null]))
        ->toThrow(DomainException::class);
    expect(Equipo::count())->toBe(1);
});

it('permite encadenar reemplazos A por B y luego B por C', function () {
    $c = contextoReemplazo();
    $a = equipoReemplazo($c, 'A');
    $b = equipoReemplazo($c, 'B', enGrupo: false);
    $cc = equipoReemplazo($c, 'C', enGrupo: false);
    filaPosicionReemplazo($c, $a, ['puntos' => 7]);

    reemplazador()->reemplazar(100, $c['categoria']->id, $a->id, $b->id, null);
    reemplazador()->reemplazar(100, $c['categoria']->id, $b->id, $cc->id, null);

    expect(TablaPosicion::where('idGrupo', $c['grupo']->id)->pluck('idEquipo')->all())->toBe([$cc->id]);
    expect(TablaPosicion::where('idEquipo', $cc->id)->value('puntos'))->toBe(7);
    expect(EquipoGrupo::where('idGrupo', $c['grupo']->id)->pluck('idEquipo')->all())->toBe([$cc->id]);
    expect(ReemplazoEquipo::count())->toBe(2);
});

it('revierte todo y registra el error si falla dentro de la transaccion', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $rival = equipoReemplazo($c, 'Rival');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);
    filaPosicionReemplazo($c, $viejo);
    $pendiente = partidoReemplazo($c, $viejo, $rival);

    $falla = Mockery::mock(PartidoService::class)->makePartial();
    $falla->shouldReceive('actualizarGolesContraEquipoEdicion')->andThrow(new RuntimeException('falla simulada'));
    Log::spy();

    $servicio = new ReemplazadorEquipo($falla);

    expect(fn () => $servicio->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null))
        ->toThrow(RuntimeException::class, 'falla simulada');

    expect(TablaPosicion::where('idEquipo', $viejo->id)->exists())->toBeTrue();
    expect(TablaPosicion::where('idEquipo', $nuevo->id)->exists())->toBeFalse();
    expect(EquipoGrupo::where('idEquipo', $viejo->id)->exists())->toBeTrue();
    expect(EquipoGrupo::where('idEquipo', $nuevo->id)->exists())->toBeFalse();
    expect($pendiente->fresh()->idEquipoLocal)->toBe($viejo->id);
    expect(ReemplazoEquipo::count())->toBe(0);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $mensaje, array $contexto = []): bool => ($contexto['exception'] ?? null) instanceof RuntimeException
            && ($contexto['idEquipoReemplazado'] ?? null) === $viejo->id)
        ->once();
});

it('lista equipos elegibles y arma la vista previa del reemplazo', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $rival = equipoReemplazo($c, 'Rival');
    $libre = equipoReemplazo($c, 'Libre', enGrupo: false);
    filaPosicionReemplazo($c, $viejo);
    partidoReemplazo($c, $viejo, $rival);
    partidoReemplazo($c, $rival, $viejo, ['jugado' => 1, 'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0]);
    finalReemplazo($c, $viejo, $rival);
    finalReemplazo($c, $viejo, $rival, ['golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0]);

    $servicio = reemplazador();

    expect($servicio->equiposElegibles(100, $c['categoria']->id, $viejo->id)->pluck('id')->all())->toBe([$libre->id]);

    $vista = $servicio->vistaPrevia(100, $c['categoria']->id, $viejo->id);
    expect($vista['grupo']->id)->toBe($c['grupo']->id);
    expect($vista['filaPosicion']->puntos)->toBe(10);
    expect($vista['partidosPendientes'])->toBe(1);
    expect($vista['finalesPendientes'])->toBe(1);
    expect($vista['resultadosBloqueados'])->toBe(2);
    expect($vista['yaReemplazado'])->toBeFalse();
});

it('rechaza reemplazar mientras el reemplazado tiene partidos o finales a medio cargar', function (string $caso) {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $rival = equipoReemplazo($c, 'Rival');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);
    filaPosicionReemplazo($c, $viejo);

    match ($caso) {
        'planillas sin resultado' => planillaReemplazo(partidoReemplazo($c, $viejo, $rival), '111', $viejo->id),
        'goles sin jugado' => partidoReemplazo($c, $viejo, $rival, ['golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0, 'jugado' => 0]),
        'jugado sin goles' => partidoReemplazo($c, $viejo, $rival, ['jugado' => 1]),
        'final con planillas' => planillaReemplazo(finalReemplazo($c, $viejo, $rival), '222', $viejo->id),
    };

    expect(fn () => reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null))
        ->toThrow(DomainException::class, 'a medio cargar');

    expect(ReemplazoEquipo::count())->toBe(0);
    expect(TablaPosicion::where('idEquipo', $viejo->id)->exists())->toBeTrue();
})->with(['planillas sin resultado', 'goles sin jugado', 'jugado sin goles', 'final con planillas']);

it('permite reemplazar cuando los partidos del reemplazado estan pendientes o ya aplicados', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $rival = equipoReemplazo($c, 'Rival');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);
    filaPosicionReemplazo($c, $viejo);
    partidoReemplazo($c, $viejo, $rival, ['jugado' => 1, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0]);
    partidoReemplazo($c, $rival, $viejo);
    finalReemplazo($c, $viejo, $rival, ['golesEquipoLocal' => 1, 'golesEquipoVisitante' => 1]);

    $reemplazo = reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null);

    expect($reemplazo->idEquipoReemplazante)->toBe($nuevo->id);
});

it('impone un indice unico sobre el equipo reemplazado por edicion y categoria', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $a = equipoReemplazo($c, 'A', enGrupo: false);
    $b = equipoReemplazo($c, 'B', enGrupo: false);
    $datos = ['idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'idGrupo' => $c['grupo']->id, 'idEquipoReemplazado' => $viejo->id];

    ReemplazoEquipo::create($datos + ['idEquipoReemplazante' => $a->id]);

    expect(fn () => ReemplazoEquipo::create($datos + ['idEquipoReemplazante' => $b->id]))
        ->toThrow(QueryException::class);
});

it('traduce una violacion de unicidad en reemplazos_equipos al mensaje de equipo ya reemplazado', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);
    $otro = equipoReemplazo($c, 'Otro', enGrupo: false);
    filaPosicionReemplazo($c, $viejo);

    ReemplazoEquipo::creating(function () use ($c, $viejo, $otro): void {
        ReemplazoEquipo::withoutEvents(fn () => ReemplazoEquipo::create([
            'idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'idGrupo' => $c['grupo']->id,
            'idEquipoReemplazado' => $viejo->id, 'idEquipoReemplazante' => $otro->id,
        ]));
    });

    expect(fn () => reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null))
        ->toThrow(DomainException::class, 'Este equipo ya fue reemplazado en la edición y no puede reemplazarse de nuevo.');
});

it('no traduce otras violaciones de unicidad como equipo ya reemplazado', function () {
    $c = contextoReemplazo();
    $viejo = equipoReemplazo($c, 'Viejo');
    $nuevo = equipoReemplazo($c, 'Nuevo', enGrupo: false);
    filaPosicionReemplazo($c, $viejo);

    ReemplazoEquipo::creating(function (): void {
        throw new QueryException('mysql', 'insert into otra_tabla', [], new PDOException('Duplicate entry for key otra_tabla.idx', 1062));
    });

    expect(fn () => reemplazador()->reemplazar(100, $c['categoria']->id, $viejo->id, $nuevo->id, null))
        ->toThrow(QueryException::class);
});
