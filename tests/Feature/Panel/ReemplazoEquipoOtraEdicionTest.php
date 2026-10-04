<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\ReemplazoEquipo;
use App\Models\TablaPosicion;
use App\Models\User;
use App\Services\PartidoService;
use App\Services\ReemplazadorEquipo;

if (! function_exists('otraEdicionContexto')) {
    /**
     * Edición actual 100 (Categoría 2011) y edición anterior 101 con categorías de nombre equivalente y distinto.
     *
     * @return array<string, mixed>
     */
    function otraEdicionContexto(): array
    {
        foreach ([[100, 'Edicion Actual'], [101, 'Edicion Anterior'], [102, 'Edicion Antigua']] as [$id, $nombre]) {
            $edicion = Edicion::create(['nombre' => $nombre]);
            $edicion->id = $id;
            $edicion->save();
        }

        $categoria = Categoria::create(['idEdicion' => 100, 'nombreCategoria' => 'Categoría 2011']);
        $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => 100, 'nombre' => 'Grupo A']);
        $fecha = Fecha::create(['idEdicion' => 100, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);
        $categoriaAnterior = Categoria::create(['idEdicion' => 101, 'nombreCategoria' => ' categoria  2011 ']);
        $categoriaAntigua = Categoria::create(['idEdicion' => 102, 'nombreCategoria' => 'CATEGORÍA 2011']);
        $categoriaOtra = Categoria::create(['idEdicion' => 101, 'nombreCategoria' => 'Categoría 2012']);

        $inscribir = function (string $nombre, int $idEdicion, Categoria $cat, bool $enGrupo = false) use ($grupo): Equipo {
            $equipo = Equipo::create(['nombre' => $nombre, 'foto' => 'e.png', 'idCategoria' => $cat->id, 'idEdicion' => $idEdicion]);
            EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $idEdicion, 'idCategoria' => $cat->id, 'golesContra' => 0]);
            if ($enGrupo) {
                EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $grupo->id]);
                TablaPosicion::create([
                    'idGrupo' => $grupo->id, 'idEquipo' => $equipo->id, 'idEdicion' => 100,
                    'puntos' => 7, 'jugado' => 3, 'ganado' => 2, 'empatado' => 1, 'perdido' => 0,
                    'golesFavor' => 6, 'golesContra' => 2, 'diferenciaGoles' => 4,
                ]);
            }

            return $equipo;
        };

        $viejo = $inscribir('Club Viejo', 100, $categoria, true);
        $rival = $inscribir('Club Rival', 100, $categoria, true);
        $anterior = $inscribir('Atlético Visitante', 101, $categoriaAnterior);
        $antiguo = $inscribir('Deportivo Antiguo', 102, $categoriaAntigua);
        $otraCat = $inscribir('Club Otra Categoría', 101, $categoriaOtra);

        return compact('categoria', 'grupo', 'fecha', 'categoriaAnterior', 'categoriaOtra', 'viejo', 'rival', 'anterior', 'antiguo', 'otraCat', 'inscribir');
    }

    function otraEdicionAdmin(mixed $test): mixed
    {
        $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);

        return $test->actingAs($admin)->withSession(['_token' => 'x']);
    }

    function otraEdicionPartido(array $c, Equipo $local, Equipo $visitante): Partido
    {
        return Partido::create([
            'idFechas' => $c['fecha']->id, 'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
            'idGrupo' => $c['grupo']->id, 'idEdicion' => 100, 'idCategoria' => $c['categoria']->id,
            'horario_datetime' => '2026-04-05 10:00:00', 'cancha' => 1, 'jugado' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    function otraEdicionPayload(array $c, ?Equipo $elegido, array $extra = []): array
    {
        return array_merge([
            '_token' => 'x',
            'idEdicion' => 100,
            'idCategoria' => $c['categoria']->id,
            'idGrupo' => $c['grupo']->id,
            'idEquipoReemplazado' => $c['viejo']->id,
            'origen' => 'otra_edicion',
            'idEquipoOtraEdicion' => $elegido?->id,
            'confirmar' => '1',
        ], $extra);
    }
}

it('lista candidatos solo de otras ediciones con la categoria de igual nombre normalizado', function () {
    $c = otraEdicionContexto();

    $items = app(ReemplazadorEquipo::class)->equiposDeOtrasEdiciones(100, $c['categoria']->id, null);

    expect($items->pluck('nombre')->all())->toBe(['Deportivo Antiguo', 'Atlético Visitante']);
    expect($items->first())->toMatchArray([
        'id' => $c['antiguo']->id,
        'edicion' => 'Edicion Antigua',
        'categoria' => 'CATEGORÍA 2011',
        'foto' => 'e.png',
    ]);
});

it('excluye los equipos ya inscriptos en esta edicion y filtra por nombre', function () {
    $c = otraEdicionContexto();
    EquipoEdicion::create(['idEquipo' => $c['antiguo']->id, 'idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'golesContra' => 0]);
    $servicio = app(ReemplazadorEquipo::class);

    expect($servicio->equiposDeOtrasEdiciones(100, $c['categoria']->id, null)->pluck('id')->all())->toBe([$c['anterior']->id]);
    expect($servicio->equiposDeOtrasEdiciones(100, $c['categoria']->id, 'atlét')->pluck('id')->all())->toBe([$c['anterior']->id]);
    expect($servicio->equiposDeOtrasEdiciones(100, $c['categoria']->id, 'zzz'))->toBeEmpty();
    expect($servicio->equiposDeOtrasEdiciones(100, $c['categoria']->id, null, 0)->count())->toBe(0);
});

it('devuelve cada equipo una sola vez aunque haya jugado varias ediciones', function () {
    $c = otraEdicionContexto();
    EquipoEdicion::create(['idEquipo' => $c['anterior']->id, 'idEdicion' => 102, 'idCategoria' => $c['categoriaOtra']->id, 'golesContra' => 0]);
    $categoriaAntigua = Categoria::where('idEdicion', 102)->first();
    EquipoEdicion::create(['idEquipo' => $c['anterior']->id, 'idEdicion' => 102, 'idCategoria' => $categoriaAntigua->id, 'golesContra' => 0]);

    $items = app(ReemplazadorEquipo::class)->equiposDeOtrasEdiciones(100, $c['categoria']->id, null);

    expect($items->pluck('id')->all())->toEqualCanonicalizing([$c['anterior']->id, $c['antiguo']->id]);
    expect($items->count())->toBe(2);
});

it('responde el buscador con items y vacio si q tiene menos de 2 caracteres', function () {
    $c = otraEdicionContexto();
    $url = route('reemplazo-equipo.buscar-otra-edicion');

    otraEdicionAdmin($this)->getJson($url.'?idEdicion=100&idCategoria='.$c['categoria']->id.'&q=Atl')
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $c['anterior']->id)
        ->assertJsonPath('items.0.nombre', 'Atlético Visitante')
        ->assertJsonPath('items.0.edicion', 'Edicion Anterior')
        ->assertJsonStructure(['items' => [['id', 'nombre', 'foto', 'edicion', 'categoria']]]);

    otraEdicionAdmin($this)->getJson($url.'?idEdicion=100&idCategoria='.$c['categoria']->id.'&q=A')
        ->assertOk()->assertExactJson(['items' => []]);
    otraEdicionAdmin($this)->getJson($url.'?idEdicion=100&idCategoria='.$c['categoria']->id)
        ->assertOk()->assertExactJson(['items' => []]);
});

it('protege el buscador de invitados y no admin', function () {
    $c = otraEdicionContexto();
    $url = route('reemplazo-equipo.buscar-otra-edicion').'?idEdicion=100&idCategoria='.$c['categoria']->id.'&q=Atl';

    $this->get($url)->assertRedirect(route('login'));
    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);
    $this->actingAs($usuario)->get($url)->assertRedirect('/');
});

it('inscribe al equipo de otra edicion y hace el reemplazo en una transaccion', function () {
    $c = otraEdicionContexto();
    $pendiente = otraEdicionPartido($c, $c['viejo'], $c['rival']);
    $equiposAntes = Equipo::count();

    $response = otraEdicionAdmin($this)->post(route('reemplazo-equipo.store'), otraEdicionPayload($c, $c['anterior']));

    $response->assertRedirect(route('tabla_posicion.index', [
        'idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'idGrupo' => $c['grupo']->id,
    ]));
    $response->assertSessionHas('success', 'Atlético Visitante reemplazó a Club Viejo y heredó su posición.');

    expect(Equipo::count())->toBe($equiposAntes);
    $inscripciones = EquipoEdicion::where('idEquipo', $c['anterior']->id)->where('idEdicion', 100)->get();
    expect($inscripciones)->toHaveCount(1);
    expect($inscripciones->first()->idCategoria)->toBe($c['categoria']->id);
    expect(EquipoGrupo::where('idGrupo', $c['grupo']->id)->pluck('idEquipo')->sort()->values()->all())
        ->toBe(collect([$c['anterior']->id, $c['rival']->id])->sort()->values()->all());
    expect(TablaPosicion::where('idEquipo', $c['anterior']->id)->value('puntos'))->toBe(7);
    expect(TablaPosicion::where('idEquipo', $c['viejo']->id)->exists())->toBeFalse();
    expect($pendiente->fresh()->idEquipoLocal)->toBe($c['anterior']->id);
    expect(ReemplazoEquipo::where('idEquipoReemplazado', $c['viejo']->id)->where('idEquipoReemplazante', $c['anterior']->id)->count())->toBe(1);
});

it('no crea la inscripcion si falla el reemplazo', function () {
    $c = otraEdicionContexto();
    otraEdicionPartido($c, $c['viejo'], $c['rival']);

    $falla = Mockery::mock(PartidoService::class)->makePartial();
    $falla->shouldReceive('actualizarGolesContraEquipoEdicion')->andThrow(new RuntimeException('falla simulada'));
    $servicio = new ReemplazadorEquipo($falla);

    expect(fn () => $servicio->reemplazar(100, $c['categoria']->id, $c['viejo']->id, null, null, $c['anterior']->id))
        ->toThrow(RuntimeException::class, 'falla simulada');

    expect(EquipoEdicion::where('idEquipo', $c['anterior']->id)->where('idEdicion', 100)->exists())->toBeFalse();
    expect(ReemplazoEquipo::count())->toBe(0);
    expect(TablaPosicion::where('idEquipo', $c['viejo']->id)->exists())->toBeTrue();
});

it('rechaza reemplazantes de otra edicion invalidos sin crear nada', function (string $caso, string $mensaje) {
    $c = otraEdicionContexto();
    $yaInscripto = $c['inscribir']('Ya inscripto', 100, $c['categoria']);
    EquipoEdicion::create(['idEquipo' => $yaInscripto->id, 'idEdicion' => 101, 'idCategoria' => $c['categoriaAnterior']->id, 'golesContra' => 0]);

    $elegido = match ($caso) {
        'ya inscripto' => $yaInscripto,
        'categoria distinta' => $c['otraCat'],
        'el reemplazado' => $c['viejo'],
        'misma edicion' => $c['rival'],
    };

    $antes = [EquipoEdicion::count(), Equipo::count(), ReemplazoEquipo::count(), TablaPosicion::count()];

    expect(fn () => app(ReemplazadorEquipo::class)->reemplazar(100, $c['categoria']->id, $c['viejo']->id, null, null, $elegido->id))
        ->toThrow(DomainException::class, $mensaje);

    expect([EquipoEdicion::count(), Equipo::count(), ReemplazoEquipo::count(), TablaPosicion::count()])->toBe($antes);
})->with([
    'ya inscripto' => ['ya inscripto', 'Equipo ya inscripto'],
    'categoria distinta' => ['categoria distinta', 'categoría'],
    'el reemplazado' => ['el reemplazado', 'mismo equipo'],
    'misma edicion' => ['misma edicion', 'Equipo ya inscripto'],
]);

it('rechaza un equipo que no pertenece a otra edicion', function () {
    $c = otraEdicionContexto();
    $solo = Equipo::create(['nombre' => 'Sin historia', 'foto' => 'e.png', 'idCategoria' => $c['categoria']->id, 'idEdicion' => 100]);

    expect(fn () => app(ReemplazadorEquipo::class)->reemplazar(100, $c['categoria']->id, $c['viejo']->id, null, null, $solo->id))
        ->toThrow(DomainException::class, 'otra edición');
    expect(EquipoEdicion::where('idEquipo', $solo->id)->exists())->toBeFalse();
});

it('rechaza combinar el origen de otra edicion con otro origen', function () {
    $c = otraEdicionContexto();

    expect(fn () => app(ReemplazadorEquipo::class)->reemplazar(100, $c['categoria']->id, $c['viejo']->id, $c['rival']->id, null, $c['anterior']->id))
        ->toThrow(DomainException::class, 'un solo origen');
});

it('valida el formulario del origen otra edicion', function () {
    $c = otraEdicionContexto();
    $volver = route('reemplazo-equipo.create', ['idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'idGrupo' => $c['grupo']->id, 'idEquipo' => $c['viejo']->id]);

    otraEdicionAdmin($this)->from($volver)
        ->post(route('reemplazo-equipo.store'), otraEdicionPayload($c, null))
        ->assertRedirect($volver)
        ->assertSessionHasErrors(['idEquipoOtraEdicion' => 'Elegí el equipo de otra edición que reemplaza.']);

    otraEdicionAdmin($this)->from($volver)
        ->post(route('reemplazo-equipo.store'), otraEdicionPayload($c, $c['anterior'], ['idEquipoExistente' => $c['rival']->id]))
        ->assertRedirect($volver)
        ->assertSessionHasErrors('idEquipoExistente');

    expect(ReemplazoEquipo::count())->toBe(0);
});

it('muestra la opcion de otra edicion con candidatos recientes en la pantalla', function () {
    $c = otraEdicionContexto();

    $response = otraEdicionAdmin($this)->get(route('reemplazo-equipo.create', [
        'idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'idGrupo' => $c['grupo']->id, 'idEquipo' => $c['viejo']->id,
    ]));

    $response->assertOk()
        ->assertSee('Equipo de otra edición')
        ->assertSee('value="otra_edicion"', false)
        ->assertSee('name="idEquipoOtraEdicion"', false)
        ->assertSee('Atlético Visitante')
        ->assertSee('Edicion Anterior')
        ->assertSee('Deportivo Antiguo')
        ->assertDontSee('Club Otra Categoría')
        ->assertSee('buscar-otra-edicion', false);
});
