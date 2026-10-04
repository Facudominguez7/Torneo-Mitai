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
use App\Services\ReemplazadorEquipo;

if (! function_exists('pantallaReemplazoContexto')) {
    /**
     * @return array<string, mixed>
     */
    function pantallaReemplazoContexto(): array
    {
        $edicion = Edicion::create(['nombre' => 'Edicion Pantalla']);
        $edicion->id = 100;
        $edicion->save();
        $categoria = Categoria::create(['idEdicion' => 100, 'nombreCategoria' => 'Sub 12']);
        $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => 100, 'nombre' => 'Grupo A']);
        $fecha = Fecha::create(['idEdicion' => 100, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);

        $crear = function (string $nombre, bool $enGrupo) use ($categoria, $grupo): Equipo {
            $equipo = Equipo::create(['nombre' => $nombre, 'foto' => 'e.png', 'idCategoria' => $categoria->id, 'idEdicion' => 100]);
            EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => 100, 'idCategoria' => $categoria->id, 'golesContra' => 0]);
            if ($enGrupo) {
                EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $grupo->id]);
                TablaPosicion::create([
                    'idGrupo' => $grupo->id, 'idEquipo' => $equipo->id, 'idEdicion' => 100,
                    'puntos' => 10, 'jugado' => 5, 'ganado' => 3, 'empatado' => 1, 'perdido' => 1,
                    'golesFavor' => 9, 'golesContra' => 4, 'diferenciaGoles' => 5,
                ]);
            }

            return $equipo;
        };

        $viejo = $crear('Club Viejo', true);
        $rival = $crear('Club Rival', true);
        $libre = $crear('Club Libre', false);

        return compact('edicion', 'categoria', 'grupo', 'fecha', 'viejo', 'rival', 'libre');
    }

    function pantallaReemplazoPartido(array $c, Equipo $local, Equipo $visitante, array $atributos = []): Partido
    {
        return Partido::create(array_merge([
            'idFechas' => $c['fecha']->id, 'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
            'idGrupo' => $c['grupo']->id, 'idEdicion' => 100, 'idCategoria' => $c['categoria']->id,
            'horario_datetime' => '2026-04-05 10:00:00', 'cancha' => 1, 'jugado' => 0,
        ], $atributos));
    }

    function pantallaReemplazoAdmin(mixed $test): mixed
    {
        $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);

        return $test->actingAs($admin)->withSession(['_token' => 'x']);
    }

    /**
     * @return array<string, mixed>
     */
    function pantallaReemplazoQuery(array $c, Equipo $equipo): array
    {
        return [
            'idEdicion' => 100,
            'idCategoria' => $c['categoria']->id,
            'idGrupo' => $c['grupo']->id,
            'idEquipo' => $equipo->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    function pantallaReemplazoPayload(array $c, array $extra = []): array
    {
        return array_merge([
            '_token' => 'x',
            'idEdicion' => 100,
            'idCategoria' => $c['categoria']->id,
            'idGrupo' => $c['grupo']->id,
            'idEquipoReemplazado' => $c['viejo']->id,
            'confirmar' => '1',
        ], $extra);
    }
}

it('muestra la accion Reemplazar equipo en cada fila de la tabla de posiciones con el contexto', function () {
    $c = pantallaReemplazoContexto();

    $response = pantallaReemplazoAdmin($this)->get(route('tabla_posicion.index', [
        'idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'idGrupo' => $c['grupo']->id,
    ]));

    $response->assertOk()->assertSee('Reemplazar equipo');
    foreach ([$c['viejo'], $c['rival']] as $equipo) {
        $response->assertSee(e(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $equipo))), false);
    }
});

it('muestra la pantalla con el nombre, la fila heredada y los conteos', function () {
    $c = pantallaReemplazoContexto();
    pantallaReemplazoPartido($c, $c['viejo'], $c['rival']);
    pantallaReemplazoPartido($c, $c['rival'], $c['viejo']);
    pantallaReemplazoPartido($c, $c['viejo'], $c['rival'], ['jugado' => 1, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 1]);

    $response = pantallaReemplazoAdmin($this)->get(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])));

    $response->assertOk()
        ->assertSee('Club Viejo')
        ->assertSee('Grupo A')
        ->assertViewHas('vistaPrevia', fn (array $v) => $v['partidosPendientes'] === 2
            && $v['finalesPendientes'] === 0
            && $v['resultadosBloqueados'] === 1
            && $v['filaPosicion']->puntos === 10)
        ->assertSee('Confirmar reemplazo')
        ->assertSee('name="origen"', false);
});

it('lista los equipos elegibles en el select', function () {
    $c = pantallaReemplazoContexto();

    $response = pantallaReemplazoAdmin($this)->get(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])));

    $response->assertOk()
        ->assertSee('name="idEquipoExistente"', false)
        ->assertSee('Club Libre')
        ->assertDontSee('value="'.$c['rival']->id.'">Club Rival', false);
});

it('explica que hay que inscribir al equipo cuando no hay elegibles', function () {
    $c = pantallaReemplazoContexto();
    EquipoGrupo::create(['idEquipo' => $c['libre']->id, 'idGrupo' => $c['grupo']->id]);

    $response = pantallaReemplazoAdmin($this)->get(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])));

    $response->assertOk()
        ->assertSee('inscripto en la edición sin grupo')
        ->assertDontSee('name="idEquipoExistente"', false)
        ->assertSee('name="nombre"', false);
});

it('muestra el motivo y no el formulario cuando el reemplazo es imposible', function () {
    $c = pantallaReemplazoContexto();
    pantallaReemplazoPartido($c, $c['viejo'], $c['rival'], ['golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0, 'jugado' => 0]);

    $response = pantallaReemplazoAdmin($this)->get(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])));

    $response->assertOk()
        ->assertSee('Tiene partidos a medio cargar')
        ->assertDontSee('Confirmar reemplazo')
        ->assertDontSee('name="origen"', false);
});

it('muestra el motivo cuando el equipo ya fue reemplazado', function () {
    $c = pantallaReemplazoContexto();
    app(ReemplazadorEquipo::class)->reemplazar(100, $c['categoria']->id, $c['viejo']->id, $c['libre']->id, null);

    $response = pantallaReemplazoAdmin($this)->get(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])));

    $response->assertOk()->assertSee('ya fue reemplazado')->assertDontSee('Confirmar reemplazo');
});

it('devuelve 404 cuando el contexto no existe', function () {
    $c = pantallaReemplazoContexto();

    pantallaReemplazoAdmin($this)->get(route('reemplazo-equipo.create', ['idEquipo' => 99999] + pantallaReemplazoQuery($c, $c['viejo'])))
        ->assertNotFound();
});

it('reemplaza con un equipo ya inscripto, hereda la fila y redirige con el mensaje', function () {
    $c = pantallaReemplazoContexto();
    pantallaReemplazoPartido($c, $c['viejo'], $c['rival']);

    $response = pantallaReemplazoAdmin($this)->post(route('reemplazo-equipo.store'), pantallaReemplazoPayload($c, [
        'origen' => 'existente', 'idEquipoExistente' => $c['libre']->id,
    ]));

    $response->assertRedirect(route('tabla_posicion.index', [
        'idEdicion' => 100, 'idCategoria' => $c['categoria']->id, 'idGrupo' => $c['grupo']->id,
    ]));
    $response->assertSessionHas('success', 'Club Libre reemplazó a Club Viejo y heredó su posición.');
    expect(TablaPosicion::where('idEquipo', $c['libre']->id)->where('idGrupo', $c['grupo']->id)->value('puntos'))->toBe(10);
    expect(ReemplazoEquipo::count())->toBe(1);
});

it('reemplaza con un equipo nuevo sin escudo', function () {
    $c = pantallaReemplazoContexto();

    $response = pantallaReemplazoAdmin($this)->post(route('reemplazo-equipo.store'), pantallaReemplazoPayload($c, [
        'origen' => 'nuevo', 'nombre' => 'Club Recien Llegado',
    ]));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Club Recien Llegado reemplazó a Club Viejo y heredó su posición.');
    $nuevo = Equipo::where('nombre', 'Club Recien Llegado')->firstOrFail();
    expect($nuevo->foto)->toBeNull();
    expect(TablaPosicion::where('idEquipo', $nuevo->id)->value('puntos'))->toBe(10);
    expect(EquipoEdicion::where('idEquipo', $nuevo->id)->where('idEdicion', 100)->exists())->toBeTrue();
});

it('rechaza el envio con errores de validacion', function (array $extra, string $campo, string $mensaje) {
    $c = pantallaReemplazoContexto();
    $extra = array_map(fn ($v) => $v === '__libre__' ? $c['libre']->id : $v, $extra);

    $response = pantallaReemplazoAdmin($this)
        ->from(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])))
        ->post(route('reemplazo-equipo.store'), pantallaReemplazoPayload($c, $extra));

    $response->assertRedirect(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])));
    $response->assertSessionHasErrors([$campo => $mensaje]);
    expect(ReemplazoEquipo::count())->toBe(0);
})->with([
    'sin ninguna opcion' => [[], 'origen', 'Elegí si el reemplazo es un equipo inscripto, un equipo nuevo o un equipo de otra edición.'],
    'existente sin equipo' => [['origen' => 'existente'], 'idEquipoExistente', 'Elegí el equipo que reemplaza.'],
    'nuevo sin nombre' => [['origen' => 'nuevo'], 'nombre', 'Escribí el nombre del equipo nuevo.'],
    'ambas opciones' => [['origen' => 'existente', 'idEquipoExistente' => '__libre__', 'nombre' => 'Otro'], 'nombre', 'Completá solo los datos de la opción elegida.'],
    'sin confirmar' => [['origen' => 'nuevo', 'nombre' => 'Club Nuevo', 'confirmar' => ''], 'confirmar', 'Confirmá que entendés lo que implica el reemplazo.'],
]);

it('vuelve con el error cuando el servicio rechaza el reemplazo', function () {
    $c = pantallaReemplazoContexto();
    pantallaReemplazoPartido($c, $c['viejo'], $c['rival'], ['golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0, 'jugado' => 0]);

    $response = pantallaReemplazoAdmin($this)
        ->from(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])))
        ->post(route('reemplazo-equipo.store'), pantallaReemplazoPayload($c, ['origen' => 'existente', 'idEquipoExistente' => $c['libre']->id]));

    $response->assertRedirect(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])));
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('a medio cargar');
    $response->assertSessionHasInput('idEquipoExistente', $c['libre']->id);
    expect(ReemplazoEquipo::count())->toBe(0);
});

it('redirige a invitados al login y bloquea a usuarios no admin', function () {
    $c = pantallaReemplazoContexto();

    $this->get(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])))->assertRedirect(route('login'));
    $this->withSession(['_token' => 'x'])->post(route('reemplazo-equipo.store'), pantallaReemplazoPayload($c))->assertRedirect(route('login'));

    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);
    $this->actingAs($usuario)->get(route('reemplazo-equipo.create', pantallaReemplazoQuery($c, $c['viejo'])))->assertRedirect('/');
    $this->actingAs($usuario)->withSession(['_token' => 'x'])->post(route('reemplazo-equipo.store'), pantallaReemplazoPayload($c))->assertRedirect('/');
    expect(ReemplazoEquipo::count())->toBe(0);
});
