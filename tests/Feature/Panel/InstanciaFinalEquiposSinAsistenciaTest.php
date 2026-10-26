<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearAdminInstanciaFinal(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearEquipoInstanciaFinal(Edicion $edicion, Categoria $categoria, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    return $equipo;
}

function crearPartidoInstanciaFinalConContexto(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Finales Filtros Test']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 12',
    ]);
    $fase = Fase::create(['nombre' => $atributos['nombreFase'] ?? 'Semifinal']);
    $local = crearEquipoInstanciaFinal($edicion, $categoria, $atributos['nombreLocal'] ?? 'Mitai Azul');
    $visitante = crearEquipoInstanciaFinal($edicion, $categoria, $atributos['nombreVisitante'] ?? 'Mitai Rojo');

    $partido = InstanciaFinal::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'horario' => $atributos['horario'] ?? '2026-03-20 10:00:00',
        'cancha' => $atributos['cancha'] ?? '1',
        'golesEquipoLocal' => $atributos['golesEquipoLocal'] ?? null,
        'golesEquipoVisitante' => $atributos['golesEquipoVisitante'] ?? null,
    ]);

    return compact('edicion', 'categoria', 'fase', 'local', 'visitante', 'partido');
}

function obtenerQueryRedirectInstanciaFinal($response): array
{
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    return $query;
}

it('muestra chips de asistencia pendiente para instancias finales', function () {
    $admin = crearAdminInstanciaFinal();
    $edicion = Edicion::create(['nombre' => 'Edicion Finales Test']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 10',
    ]);
    $fase = Fase::create(['nombre' => 'Final']);
    $local = crearEquipoInstanciaFinal($edicion, $categoria, 'Mitai Local');
    $visitante = crearEquipoInstanciaFinal($edicion, $categoria, 'Mitai Visitante');

    $partido = InstanciaFinal::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'horario' => '2026-03-20 10:00:00',
        'cancha' => '1',
    ]);

    Jugador::create([
        'dni' => 40100111,
        'nombre' => 'Jugador',
        'apellido' => 'Local',
    ]);

    PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => InstanciaFinal::class,
        'dni_jugador' => 40100111,
        'idEquipo' => $local->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('instancia_final.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertViewHas('equiposSinAsistencia')
        ->assertSee('Mitai Local (Local): Sin asistencias marcadas')
        ->assertSee('Mitai Visitante (Visitante): Sin planilla');
});

it('mantiene visible la accion para editar resultado cuando la instancia final ya tiene goles', function () {
    $admin = crearAdminInstanciaFinal();
    $contexto = crearPartidoInstanciaFinalConContexto([
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('instancia_final.index', [
            'idEdicion' => $contexto['edicion']->id,
            'estado' => 'jugado',
        ]));

    // "Editar resultado" en sentence case: T6 (panel-admin-redesign) migró el
    // botón a x-ui.button, que sigue la convención de copy del rediseño
    // (botones en sentence case, igual que en el card de Partidos, T5).
    $response->assertOk()
        ->assertSee('Editar resultado')
        ->assertSee('cargar-resultado-instancia')
        ->assertSee('idPartido='.$contexto['partido']->id, false)
        ->assertSee('estado=jugado', false);
});

it('preserva solo los filtros de regreso al cargar resultado de instancia final', function () {
    $admin = crearAdminInstanciaFinal();
    $contexto = crearPartidoInstanciaFinalConContexto();
    $filtros = [
        'idEdicion' => $contexto['edicion']->id,
        'idFase' => $contexto['fase']->id,
        'estado' => 'pendiente',
        'busqueda' => 'Mitai',
        'fecha' => '2026-03-20',
        'horario' => '10:00',
    ];

    // "Cargar resultado" en sentence case: mismo cambio de copy que arriba.
    $this->actingAs($admin)
        ->get(route('instancia_final.index', $filtros))
        ->assertOk()
        ->assertSee('Cargar resultado')
        ->assertSee('idPartido='.$contexto['partido']->id, false)
        ->assertSee('estado=pendiente', false)
        ->assertSee('busqueda=Mitai', false);

    $this->actingAs($admin)
        ->get(route('cargar-resultado-instancia', array_merge($filtros, [
            'idPartido' => $contexto['partido']->id,
            'tipo' => 'instancia_final',
        ])))
        ->assertOk()
        ->assertSee('name="retorno_idFase" value="'.$contexto['fase']->id.'"', false)
        ->assertSee('name="retorno_estado" value="pendiente"', false)
        ->assertSee('name="retorno_busqueda" value="Mitai"', false)
        ->assertSee('name="retorno_fecha" value="2026-03-20"', false)
        ->assertSee('name="retorno_horario" value="10:00"', false)
        ->assertSee('name="retorno_idCategoria" value=""', false);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'resultado-token'])
        ->post(route('cargar-resultado-instancia', array_merge($filtros, [
            'idPartido' => $contexto['partido']->id,
        ])), [
            '_token' => 'resultado-token',
            'golesEquipoLocal' => 3,
            'golesEquipoVisitante' => 1,
            'retorno_idFase' => $contexto['fase']->id,
            'retorno_estado' => 'pendiente',
            'retorno_busqueda' => 'Mitai',
            'retorno_fecha' => '2026-03-20',
            'retorno_horario' => '10:00',
        ]);

    $query = obtenerQueryRedirectInstanciaFinal($response);

    $response->assertRedirect();
    expect($query)->toMatchArray([
        'idEdicion' => (string) $contexto['edicion']->id,
        'idFase' => (string) $contexto['fase']->id,
        'estado' => 'pendiente',
        'busqueda' => 'Mitai',
        'fecha' => '2026-03-20',
        'horario' => '10:00',
    ])->not->toHaveKey('idCategoria');
});

it('preserva filtros al abrir editar instancia final desde el card y actualizar', function () {
    $admin = crearAdminInstanciaFinal();
    $contexto = crearPartidoInstanciaFinalConContexto();
    $filtros = [
        'idEdicion' => $contexto['edicion']->id,
        'idFase' => $contexto['fase']->id,
        'estado' => 'pendiente',
        'busqueda' => 'Mitai',
        'fecha' => '2026-03-20',
        'horario' => '10:00',
    ];

    $this->actingAs($admin)
        ->get(route('instancia_final.index', $filtros))
        ->assertOk()
        ->assertSee('Editar')
        ->assertSee('instancia_final/'.$contexto['partido']->id.'/edit', false)
        ->assertSee('estado=pendiente', false)
        ->assertSee('busqueda=Mitai', false);

    $this->actingAs($admin)
        ->get(route('instancia_final.edit', array_merge($filtros, [
            'instancia_final' => $contexto['partido']->id,
        ])))
        ->assertOk()
        ->assertSee('name="retorno_idFase" value="'.$contexto['fase']->id.'"', false)
        ->assertSee('name="retorno_estado" value="pendiente"', false)
        ->assertSee('name="retorno_busqueda" value="Mitai"', false)
        ->assertSee('name="retorno_fecha" value="2026-03-20"', false)
        ->assertSee('name="retorno_horario" value="10:00"', false)
        ->assertSee('name="retorno_idCategoria" value=""', false);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'edit-token'])
        ->put(route('instancia_final.update', $contexto['partido']), [
            '_token' => 'edit-token',
            'idCategoria' => $contexto['categoria']->id,
            'idCopa' => null,
            'idEdicion' => $contexto['edicion']->id,
            'idEquipoLocal' => $contexto['local']->id,
            'idEquipoVisitante' => $contexto['visitante']->id,
            'idFase' => $contexto['fase']->id,
            'horario' => '2026-03-21T11:30',
            'cancha' => 2,
            'retorno_idCategoria' => '',
            'retorno_idFase' => $contexto['fase']->id,
            'retorno_estado' => 'pendiente',
            'retorno_busqueda' => 'Mitai',
            'retorno_fecha' => '2026-03-20',
            'retorno_horario' => '10:00',
        ]);

    $query = obtenerQueryRedirectInstanciaFinal($response);

    $response->assertRedirect();
    expect($query)->toMatchArray([
        'idEdicion' => (string) $contexto['edicion']->id,
        'idFase' => (string) $contexto['fase']->id,
        'estado' => 'pendiente',
        'busqueda' => 'Mitai',
        'fecha' => '2026-03-20',
        'horario' => '10:00',
    ])->not->toHaveKey('idCategoria');
});

it('preserva los filtros de regreso al actualizar una instancia final sin usar datos del partido como filtros', function () {
    $admin = crearAdminInstanciaFinal();
    $contexto = crearPartidoInstanciaFinalConContexto();

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'update-token'])
        ->put(route('instancia_final.update', $contexto['partido']), [
            '_token' => 'update-token',
            'idCategoria' => $contexto['categoria']->id,
            'idCopa' => null,
            'idEdicion' => $contexto['edicion']->id,
            'idEquipoLocal' => $contexto['local']->id,
            'idEquipoVisitante' => $contexto['visitante']->id,
            'idFase' => $contexto['fase']->id,
            'horario' => '2026-03-21T11:30',
            'cancha' => 2,
            'retorno_idFase' => $contexto['fase']->id,
            'retorno_estado' => 'jugado',
            'retorno_fecha' => '2026-03-20',
            'retorno_horario' => '10:00',
        ]);

    $query = obtenerQueryRedirectInstanciaFinal($response);

    $response->assertRedirect();
    expect($query)->toMatchArray([
        'idEdicion' => (string) $contexto['edicion']->id,
        'idFase' => (string) $contexto['fase']->id,
        'estado' => 'jugado',
        'fecha' => '2026-03-20',
        'horario' => '10:00',
    ])->not->toHaveKey('idCategoria');
});

it('preserva los filtros de regreso al eliminar una instancia final sin agregar categoria por defecto', function () {
    $admin = crearAdminInstanciaFinal();
    $contexto = crearPartidoInstanciaFinalConContexto();

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'destroy-token'])
        ->delete(route('instancia_final.destroy', [
            'instancia_final' => $contexto['partido']->id,
            'idEdicion' => $contexto['edicion']->id,
            'estado' => 'pendiente',
            'fecha' => '2026-03-20',
            'horario' => '10:00',
        ]), ['_token' => 'destroy-token']);

    $query = obtenerQueryRedirectInstanciaFinal($response);

    $response->assertRedirect();
    expect($query)->toMatchArray([
        'idEdicion' => (string) $contexto['edicion']->id,
        'estado' => 'pendiente',
        'fecha' => '2026-03-20',
        'horario' => '10:00',
    ])->not->toHaveKey('idCategoria');
});
