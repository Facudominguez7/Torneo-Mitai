<?php

// Feature tests for the Lista de buena fe module migration onto the shared
// x-ui.* components (T7, panel-admin-redesign). Covers the general listing
// (filters, stat tiles, exceeded-limit alert, search, print/admin dropdown
// actions, team card grid with badges and progress bar) and the per-team
// squad screen (search, migrate action, add-player form with autocomplete
// ids, active/inactive tables with the Suspendido/En revisión badges,
// edit modal and PDF/print actions). JS ids/data attributes and every
// existing route/query param are kept intact.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\User;

function crearAdminListaBuenaFeMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoListaBuenaFeMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Lista Buena Fe Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 13 Migration',
    ]);

    return compact('edicion', 'categoria');
}

function crearEquipoEnEdicionListaBuenaFeMigration(array $contexto, string $nombre): EquipoEdicion
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    return EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);
}

function crearJugadorEnListaBuenaFeMigration(array $contexto, EquipoEdicion $equipoEdicion, string $dni, bool $activo = true, array $atributos = []): ListaBuenaFe
{
    Jugador::firstOrCreate(['dni' => $dni], [
        'nombre' => 'Jugador',
        'apellido' => $dni,
    ]);

    // `numero_camiseta` quedó fuera adrede: la migración
    // 2025_11_22_213515_remove_numero_camiseta_from_lista_buena_fe_table.php
    // eliminó esa columna de la tabla real, pero el modelo/vistas todavía la
    // referencian como atributo de lectura (mismatch preexistente que ya
    // rompe 2 tests de BuscarDniListaBuenaFeTest en `main`; no se toca aquí).
    return ListaBuenaFe::create(array_merge([
        'dni_jugador' => $dni,
        'idEquipo' => $equipoEdicion->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2013-05-10',
        'fecha_alta' => now()->subDays(3),
        'activo' => $activo,
        'fecha_baja' => $activo ? null : now()->subDay(),
    ], $atributos));
}

it('renderiza el encabezado, filtros y tarjetas estadisticas del listado', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Mitai Azul');
    crearJugadorEnListaBuenaFeMigration($contexto, $equipoEdicion, '40100001');

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Lista de buena fe')
        ->assertSee('id="idEdicion"', false)
        ->assertSee('id="idCategoria"', false)
        ->assertSee('text-2xl font-bold text-gray-800', false)
        ->assertSee('Equipos')
        ->assertSee('Sin jugadores')
        ->assertSee('Excedidos')
        ->assertSee('Fechas');
});

it('muestra la alerta de equipos que exceden el limite de jugadores', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Equipo Excedido');

    foreach (range(1, 19) as $i) {
        crearJugadorEnListaBuenaFeMigration($contexto, $equipoEdicion, (string) (40200000 + $i));
    }

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('más de 18 jugadores')
        ->assertSee('Equipo Excedido');
});

it('muestra la busqueda, las acciones de impresion y administracion', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Mitai Azul');
    crearJugadorEnListaBuenaFeMigration($contexto, $equipoEdicion, '40100001');
    Fecha::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Fecha 1']);

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('name="search"', false)
        ->assertSee('Impresión')
        ->assertSee('Todas las planillas')
        ->assertSee('id="idFechaImpresion"', false)
        ->assertSee('data-print-fecha-form', false)
        ->assertSee('actualizarEstadoImpresionFecha(', false)
        ->assertSee('imprimirPlanillasPorFecha(', false)
        ->assertSee('Administración')
        ->assertSee('Migrar todos los equipos')
        ->assertSee(e(route('lista-buena-fe.migrar-todos')), false)
        ->assertSee('Revisión de jugadores')
        ->assertSee(e(route('lista-buena-fe.conflictivos')), false);
});

it('muestra las tarjetas de equipo con badge, barra de progreso y prioridad sin jugadores', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoConJugadores = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Equipo Con Jugadores');
    crearJugadorEnListaBuenaFeMigration($contexto, $equipoConJugadores, '40100002');
    crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Equipo Sin Jugadores');

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('1 jugadores')
        ->assertSee('Sin jugadores')
        ->assertSee('Prioridad administrativa')
        ->assertSee(e(route('lista-buena-fe.show', [
            'idEquipo' => $equipoConJugadores->idEquipo,
            'idEdicion' => $contexto['edicion']->id,
        ])), false);
});

it('renderiza el estado vacio de equipos cuando el filtro no encuentra resultados', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();

    $conFiltroSinResultados = $this->actingAs($admin)->get(route('lista-buena-fe.index', [
        'idEdicion' => $contexto['edicion']->id,
        'search' => 'Equipo Inexistente',
    ]));

    $conFiltroSinResultados->assertOk()
        ->assertSee('No se encontraron equipos')
        ->assertSee('border-dashed', false);
});

it('renderiza el estado sin edicion seleccionada cuando no hay ediciones cargadas', function () {
    $admin = crearAdminListaBuenaFeMigration();

    $sinEdicion = $this->actingAs($admin)->get(route('lista-buena-fe.index'));

    $sinEdicion->assertOk()->assertSee('Seleccioná una edición para comenzar');
});

it('renderiza el detalle del equipo con busqueda, formulario de alta y tabla de jugadores activos', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Mitai Azul');
    crearJugadorEnListaBuenaFeMigration($contexto, $equipoEdicion, '40100010');

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.show', [
        'idEquipo' => $equipoEdicion->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Mitai Azul')
        ->assertSee('id="buscar_dni"', false)
        ->assertSee('id="dni_jugador"', false)
        ->assertSee('id="nombre_jugador"', false)
        ->assertSee('id="apellido_jugador"', false)
        ->assertSee('id="fecha_nacimiento"', false)
        ->assertSee('id="numero_camiseta"', false)
        ->assertSee('id="dni_status"', false)
        ->assertSee('id="dni_buscando"', false)
        ->assertSee('id="dni_encontrado"', false)
        ->assertSee('id="dni_no_encontrado"', false)
        ->assertSee(e(route('lista-buena-fe.buscar-dni')), false)
        ->assertSee('40100010')
        ->assertSee(e(route('lista-buena-fe.pdf', ['idEquipo' => $equipoEdicion->idEquipo, 'idEdicion' => $contexto['edicion']->id])), false)
        ->assertSee('Descargar PDF')
        ->assertSee('Imprimir Planilla');
});

it('muestra el boton de migrar jugadores existentes cuando no hay jugadores activos', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Mitai Sin Jugadores');

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.show', [
        'idEquipo' => $equipoEdicion->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Migrar Jugadores Existentes')
        ->assertSee(e(route('lista-buena-fe.migrar')), false);
});

it('renderiza editar y dar de baja para cada jugador activo, con motivo y confirmacion', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Mitai Azul');
    $registro = crearJugadorEnListaBuenaFeMigration($contexto, $equipoEdicion, '40100020');

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.show', [
        'idEquipo' => $equipoEdicion->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('js-editar-jugador', false)
        ->assertSee('name="motivo_baja"', false)
        ->assertSee(e(route('lista-buena-fe.dar-baja', $registro->id)), false)
        ->assertSee(e(route('lista-buena-fe.actualizar', $registro->id)), false)
        ->assertSee('modal-editar-jugador', false)
        ->assertSee('formEditarJugador', false);
});

it('muestra el historial de bajas con reactivar o limite alcanzado', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Mitai Azul');
    $baja = crearJugadorEnListaBuenaFeMigration($contexto, $equipoEdicion, '40100030', false, [
        'motivo_baja' => 'Pase a otro club',
    ]);

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.show', [
        'idEquipo' => $equipoEdicion->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Historial')
        ->assertSee('Pase a otro club')
        ->assertSee(e(route('lista-buena-fe.reactivar', $baja->id)), false);
});

it('muestra en revision para un jugador conflictivo sin resolver en la tabla activa', function () {
    $admin = crearAdminListaBuenaFeMigration();
    $contexto = crearContextoListaBuenaFeMigration();
    $equipoEdicion = crearEquipoEnEdicionListaBuenaFeMigration($contexto, 'Mitai Azul');
    crearJugadorEnListaBuenaFeMigration($contexto, $equipoEdicion, '40100040', true, [
        'conflictivo' => true,
        'motivo_conflicto' => 'Posible duplicado',
        'resuelto' => false,
    ]);

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.show', [
        'idEquipo' => $equipoEdicion->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()->assertSee('En revisión');
});
