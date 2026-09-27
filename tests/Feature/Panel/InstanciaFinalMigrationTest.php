<?php

// Feature tests for the Instancias finales module migration onto the shared
// x-ui.* components (T6, panel-admin-redesign), following the Partidos pilot
// (T5). Instancias finales is a bracket: grouped by categoría, then copa,
// then laid out in fase columns. Covers the header actions and print
// dropdown, the filters bar (ids kept for the existing client-side JS), the
// stat tiles, the copa/fase grouping order, the match cards (penales,
// global, missing-sheet warning, the four actions and confirm-delete) and
// the empty state, plus the create/edit forms.

use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearAdminInstanciaFinalMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoInstanciaFinalMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Instancia Final Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 15 Migration',
    ]);

    return compact('edicion', 'categoria');
}

function crearEquipoParaInstanciaFinalMigration(Edicion $edicion, Categoria $categoria, string $nombre): Equipo
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

function crearInstanciaFinalParaMigration(array $contexto, string $local, string $visitante, array $atributos = []): InstanciaFinal
{
    $equipoLocal = crearEquipoParaInstanciaFinalMigration($contexto['edicion'], $contexto['categoria'], $local);
    $equipoVisitante = crearEquipoParaInstanciaFinalMigration($contexto['edicion'], $contexto['categoria'], $visitante);

    $fase = $atributos['fase'] ?? Fase::create(['nombre' => 'Final']);

    return InstanciaFinal::create(array_merge([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'horario' => '2026-04-05 10:00:00',
        'cancha' => 1,
    ], array_diff_key($atributos, ['fase' => null])));
}

it('renderiza el encabezado de Instancias finales con x-ui.page-header y las acciones', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    crearInstanciaFinalParaMigration($contexto, 'Mitai Azul', 'Mitai Rojo');

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Instancias finales')
        ->assertSee('Agregar partido')
        ->assertSee('Imprimir')
        ->assertSee('Fixture')
        ->assertSee('Planilla Árbitros')
        ->assertSee('Planilla de Cobro')
        ->assertSee('text-2xl font-bold text-gray-800', false);
});

it('no muestra la accion Agregar en esta categoria sin filtro de categoria', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertDontSee('Agregar en esta categoría');
});

it('muestra la accion Agregar en esta categoria cuando hay filtro de categoria', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    crearInstanciaFinalParaMigration($contexto, 'Mitai Azul', 'Mitai Rojo');

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()->assertSee('Agregar en esta categoría');
});

it('renderiza los filtros y el selector de impresion con los ids que usa el script de la pagina', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    crearInstanciaFinalParaMigration($contexto, 'Mitai Azul', 'Mitai Rojo');

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('id="filtro-categoria"', false)
        ->assertSee('id="filtro-horario"', false)
        ->assertSee('id="selector-impresion-instancias"', false)
        ->assertSee('ejecutarImpresionInstanciasFinales(', false);
});

it('muestra chips de filtro activos removibles y los oculta sin filtros', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    $fase = Fase::create(['nombre' => 'Semifinal Migration']);
    crearInstanciaFinalParaMigration($contexto, 'Mitai Azul', 'Mitai Rojo', ['fase' => $fase]);

    $conFiltros = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFase' => $fase->id,
        'horario' => '10:00',
        'fecha' => '2026-04-05',
    ]));

    $conFiltros->assertOk()
        ->assertSee('Categoría: '.$contexto['categoria']->nombreCategoria)
        ->assertSee('Fase: '.$fase->nombre)
        ->assertSee('Horario: 10:00')
        ->assertSee('Fecha: 2026-04-05');

    $sinFiltros = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $sinFiltros->assertOk()
        ->assertDontSee('Categoría: '.$contexto['categoria']->nombreCategoria)
        ->assertDontSee('Fase: '.$fase->nombre)
        ->assertDontSee('Horario: 10:00')
        ->assertDontSee('Fecha: 2026-04-05');
});

it('muestra las tarjetas estadisticas con los conteos correctos', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    crearInstanciaFinalParaMigration($contexto, 'Jugado Local', 'Jugado Visita', [
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
    ]);
    crearInstanciaFinalParaMigration($contexto, 'Pendiente Local', 'Pendiente Visita');

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toMatch('/text-gray-900">\s*2\s*<\/span>\s*<p class="text-xs font-medium text-gray-500">Partidos/s')
        ->and($html)->toMatch('/text-emerald-600">\s*1\s*<\/span>\s*<p class="text-xs font-medium text-gray-500">Jugados/s')
        ->and($html)->toMatch('/text-amber-600">\s*1\s*<\/span>\s*<p class="text-xs font-medium text-gray-500">Pendientes/s');
});

it('agrupa las tarjetas por categoria, copa y fase en ese orden', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    $copa = Copa::create(['nombre' => 'Copa Oro Migration']);
    $faseSemi = Fase::create(['nombre' => 'Semifinal Bracket Migration']);
    $faseFinal = Fase::create(['nombre' => 'Final Bracket Migration']);

    crearInstanciaFinalParaMigration($contexto, 'Semi Local', 'Semi Visita', [
        'fase' => $faseSemi,
        'idCopa' => $copa->id,
        'horario' => '2026-04-05 09:00:00',
    ]);
    crearInstanciaFinalParaMigration($contexto, 'Final Local', 'Final Visita', [
        'fase' => $faseFinal,
        'idCopa' => $copa->id,
        'horario' => '2026-04-05 11:00:00',
    ]);

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSeeInOrder([
            $contexto['categoria']->nombreCategoria,
            'Copa Oro Migration',
            'Semifinal Bracket Migration',
            'Semi Local',
            'Final Bracket Migration',
            'Final Local',
        ]);
});

it('muestra penales y resultado global cuando estan cargados', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    // resultadoGlobal no es fillable en el modelo (el controlador lo asigna
    // por propiedad directa en cargarResultadoInstancia, nunca por alta
    // masiva), así que se carga igual que en producción: forceFill + save.
    crearInstanciaFinalParaMigration($contexto, 'Penal Local', 'Penal Visita', [
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 1,
        'penalesEquipoLocal' => 4,
        'penalesEquipoVisitante' => 2,
    ])->forceFill(['resultadoGlobal' => '3-2'])->save();

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('4 - 2')
        ->assertSee('Global: 3-2');
});

it('muestra el aviso de planilla o asistencia faltante en la tarjeta', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    $partido = crearInstanciaFinalParaMigration($contexto, 'Mitai Local', 'Mitai Visitante');

    Jugador::create([
        'dni' => 40100222,
        'nombre' => 'Jugador',
        'apellido' => 'Local',
    ]);

    PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => InstanciaFinal::class,
        'dni_jugador' => 40100222,
        'idEquipo' => $partido->idEquipoLocal,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 10,
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Mitai Local (Local): Sin asistencias marcadas')
        ->assertSee('Mitai Visitante (Visitante): Sin planilla');
});

it('renderiza cada tarjeta con las cuatro acciones y el borrado con confirmacion', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    $partido = crearInstanciaFinalParaMigration($contexto, 'Mitai Azul', 'Mitai Rojo');

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Cargar resultado')
        ->assertSee('Planillas')
        ->assertSee('Editar')
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee(e(route('instancia_final.destroy', [
            'instancia_final' => $partido->id,
            'idEdicion' => $contexto['edicion']->id,
        ])), false);
});

it('muestra Editar resultado cuando la instancia final ya tiene goles cargados', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    crearInstanciaFinalParaMigration($contexto, 'Mitai Azul', 'Mitai Rojo', [
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
    ]);

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Editar resultado')
        ->assertDontSee('Cargar resultado');
});

it('renderiza el estado vacio con direccion y la accion Agregar partido cuando no hay partidos', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();

    $response = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('No se encontraron partidos de instancias finales')
        ->assertSee('border-dashed', false)
        ->assertSee('Agregar partido');
});

it('renderiza el formulario de alta con x-ui.form-card y todos los campos', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    Fase::create(['nombre' => 'Final Form Migration']);
    Copa::create(['nombre' => 'Copa Form Migration']);
    crearEquipoParaInstanciaFinalMigration($contexto['edicion'], $contexto['categoria'], 'Equipo Form A');
    crearEquipoParaInstanciaFinalMigration($contexto['edicion'], $contexto['categoria'], 'Equipo Form B');

    $response = $this->actingAs($admin)->get(route('instancia_final.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $response->assertOk()
        ->assertSee('text-gray-700', false)
        ->assertSee('Crear partido')
        ->assertSee('id="idEquipoLocal"', false)
        ->assertSee('id="idEquipoVisitante"', false)
        ->assertSee('id="idFase"', false)
        ->assertSee('id="idCopa"', false)
        ->assertSee('Ninguna copa')
        ->assertSee('id="horario"', false)
        ->assertSee('id="cancha"', false)
        ->assertSee($contexto['categoria']->nombreCategoria);
});

it('renderiza el formulario de edicion con x-ui.form-card y Guardar cambios', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    crearEquipoParaInstanciaFinalMigration($contexto['edicion'], $contexto['categoria'], 'Equipo Form C');
    crearEquipoParaInstanciaFinalMigration($contexto['edicion'], $contexto['categoria'], 'Equipo Form D');
    $partido = crearInstanciaFinalParaMigration($contexto, 'Equipo Form C', 'Equipo Form D');

    $response = $this->actingAs($admin)->get(route('instancia_final.edit', [
        'instancia_final' => $partido->id,
    ]));

    $response->assertOk()
        ->assertSee('Guardar cambios')
        ->assertSee('action="'.route('instancia_final.update', $partido->id).'"', false);
});

it('incluye el resumen de errores en los formularios', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    $urlCreate = route('instancia_final.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $this->actingAs($admin)
        ->from($urlCreate)
        ->withSession(['_token' => 'token-csrf-instancia-final-errores'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-instancia-final-errores'])
        ->post(route('instancia_final.store'), [])
        ->assertRedirect($urlCreate)
        ->assertSessionHasErrors(['idCategoria', 'idFase']);

    $this->actingAs($admin)->get($urlCreate)
        ->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Revisá los campos marcados');
});

it('muestra las fases de una copa como columnas del mismo ancho en desktop', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    $copa = Copa::create(['nombre' => 'Copa Columnas Migration']);
    $fase = Fase::create(['nombre' => 'Final Columnas Migration']);
    crearInstanciaFinalParaMigration($contexto, 'Columna Local', 'Columna Visita', [
        'fase' => $fase,
        'idCopa' => $copa->id,
        'horario' => '2026-04-05 09:00:00',
    ]);

    $html = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]))->assertOk()->getContent();

    expect($html)->toMatch('/data-cuadro-fases[^>]*class="[^"]*lg:grid-flow-col[^"]*lg:auto-cols-\[17rem\]/');
});

it('rotula los partidos sin copa y ordena copas y fases por su primer partido', function () {
    $admin = crearAdminInstanciaFinalMigration();
    $contexto = crearContextoInstanciaFinalMigration();
    $copa = Copa::create(['nombre' => 'Copa Tardia Migration']);
    $faseIda = Fase::create(['nombre' => 'Tumbada Ida Migration']);
    $faseVuelta = Fase::create(['nombre' => 'Tumbada Vuelta Migration']);
    $faseFinal = Fase::create(['nombre' => 'Final Tardia Migration']);

    // La final con copa va primero en la consulta (es la única pendiente).
    crearInstanciaFinalParaMigration($contexto, 'Final Local', 'Final Visita', ['fase' => $faseFinal, 'idCopa' => $copa->id, 'horario' => '2026-07-12 10:00:00']);
    crearInstanciaFinalParaMigration($contexto, 'Vuelta Local', 'Vuelta Visita', ['fase' => $faseVuelta, 'idCopa' => null, 'horario' => '2026-06-14 10:00:00']);
    crearInstanciaFinalParaMigration($contexto, 'Ida Local', 'Ida Visita', ['fase' => $faseIda, 'idCopa' => null, 'horario' => '2026-06-07 10:00:00']);

    $html = $this->actingAs($admin)->get(route('instancia_final.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]))->assertOk()->getContent();
    $cuadro = substr($html, (int) strpos($html, 'data-cuadro-fases') - 600);

    expect($cuadro)->toMatch('/Sin copa.*Tumbada Ida Migration.*Tumbada Vuelta Migration.*Copa Tardia Migration.*Final Tardia Migration/s');
});
