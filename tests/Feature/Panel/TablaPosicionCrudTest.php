<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;
use Illuminate\Support\Str;

function crearAdminTablaPosiciones(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoTablaPosicionesCrud(string $nombreEdicion, string $nombreCategoria, string $nombreGrupo): array
{
    $edicion = Edicion::create([
        'nombre' => $nombreEdicion,
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $nombreCategoria,
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombreGrupo,
    ]);

    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Azul',
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]);

    return compact('edicion', 'categoria', 'grupo', 'equipo');
}

function crearTablaPosicionContextual(array $contexto, array $atributos = []): TablaPosicion
{
    return TablaPosicion::create(array_merge([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 12,
        'golesFavor' => 10,
        'golesContra' => 4,
        'diferenciaGoles' => 6,
        'jugado' => 4,
        'ganado' => 4,
        'perdido' => 0,
        'empatado' => 0,
    ], $atributos));
}

function csrfPayload(string $token, array $datos): array
{
    return array_merge(['_token' => $token], $datos);
}

it('muestra el enlace a tabla de posiciones en la barra lateral del panel', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Sidebar', 'Sub 16', 'Grupo A');

    $response = $this->actingAs($admin)->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee(e(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id])), false)
        ->assertSee('Tabla de Posiciones');
});

it('mantiene idCategoria e idGrupo en el boton de crear del index', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Index', 'Sub 12', 'Grupo A');

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee(e(route('tabla_posicion.create', [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
        ])), false);
});

it('renderiza el formulario de creacion con retorno contextual completo', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Create', 'Sub 13', 'Grupo B');

    $response = $this->actingAs($admin)->get(route('tabla_posicion.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $response->assertOk()
        ->assertSee('Nueva Posición')
        ->assertSee(e(route('tabla_posicion.index', [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
        ])), false);
});

it('renderiza la edicion correcta y conserva el contexto al volver', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Edit', 'Sub 14', 'Grupo C');
    $tablaPosicion = crearTablaPosicionContextual($contexto, [
        'puntos' => 21,
    ]);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.edit', array_merge([
        'tabla_posicion' => $tablaPosicion,
    ], [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ])));

    $response->assertOk()
        ->assertSee('Editar Posición')
        ->assertSee('Mitai Azul')
        ->assertSee(e(route('tabla_posicion.index', [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
        ])), false);
});

it('muestra las posiciones de la edicion seleccionada en el panel', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Listado', 'Sub 17', 'Grupo B');

    crearTablaPosicionContextual($contexto);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Tabla de Posiciones')
        ->assertSee('Mitai Azul')
        ->assertSee('12')
        ->assertSee('Agregar Posición');
});

it('muestra el estado vacio cuando la edicion no tiene posiciones', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Vacia', 'Sub 18', 'Grupo C');

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('No existen registros');
});

it('crea una posicion y vuelve al contexto de la edicion', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Alta', 'Sub 19', 'Grupo D');

    $token = Str::random(40);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->post(route('tabla_posicion.store'), csrfPayload($token, [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
            'idEquipo' => $contexto['equipo']->id,
            'puntos' => 15,
            'golesFavor' => 14,
            'golesContra' => 5,
            'diferenciaGoles' => 9,
            'jugado' => 5,
            'ganado' => 5,
            'perdido' => 0,
            'empatado' => 0,
        ]));

    $response->assertRedirect(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $this->assertDatabaseHas('tabla_posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'puntos' => 15,
    ]);
});

it('actualiza una posicion sin perder la edicion activa', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Update', 'Sub 20', 'Grupo E');
    $tablaPosicion = TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 8,
        'golesFavor' => 7,
        'golesContra' => 6,
        'diferenciaGoles' => 1,
        'jugado' => 4,
        'ganado' => 2,
        'perdido' => 1,
        'empatado' => 1,
    ]);

    $token = Str::random(40);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->put(route('tabla_posicion.update', $tablaPosicion), csrfPayload($token, [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
            'idEquipo' => $contexto['equipo']->id,
            'puntos' => 18,
            'golesFavor' => 16,
            'golesContra' => 5,
            'diferenciaGoles' => 11,
            'jugado' => 6,
            'ganado' => 6,
            'perdido' => 0,
            'empatado' => 0,
        ]));

    $response->assertRedirect(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $this->assertDatabaseHas('tabla_posiciones', [
        'id' => $tablaPosicion->id,
        'puntos' => 18,
        'golesFavor' => 16,
        'golesContra' => 5,
        'diferenciaGoles' => 11,
    ]);
});

it('elimina una posicion desde el panel', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Delete', 'Sub 21', 'Grupo F');
    $tablaPosicion = TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 3,
        'golesFavor' => 2,
        'golesContra' => 4,
        'diferenciaGoles' => -2,
        'jugado' => 3,
        'ganado' => 1,
        'perdido' => 2,
        'empatado' => 0,
    ]);

    $token = Str::random(40);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->delete(route('tabla_posicion.destroy', $tablaPosicion), csrfPayload($token, [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
        ]));

    $response->assertRedirect(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $this->assertDatabaseMissing('tabla_posiciones', [
        'id' => $tablaPosicion->id,
    ]);
});

it('permite ver posiciones por categoria y grupo desde tabs y filtro', function () {
    $admin = crearAdminTablaPosiciones();

    $edicion = Edicion::create([
        'nombre' => 'Edicion Tabs',
    ]);

    $categoriaA = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);

    $categoriaB = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 13',
    ]);

    $grupoA = Grupo::create([
        'idCategoria' => $categoriaA->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Único',
    ]);

    $grupoB = Grupo::create([
        'idCategoria' => $categoriaB->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Único',
    ]);

    $equipoA = Equipo::create([
        'idCategoria' => $categoriaA->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Blanco',
        'foto' => 'escudo.png',
    ]);

    $equipoB = Equipo::create([
        'idCategoria' => $categoriaB->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Negro',
        'foto' => 'escudo.png',
    ]);

    TablaPosicion::create([
        'idGrupo' => $grupoA->id,
        'idEquipo' => $equipoA->id,
        'idEdicion' => $edicion->id,
        'puntos' => 9,
        'golesFavor' => 7,
        'golesContra' => 2,
        'diferenciaGoles' => 5,
        'jugado' => 3,
        'ganado' => 3,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    TablaPosicion::create([
        'idGrupo' => $grupoB->id,
        'idEquipo' => $equipoB->id,
        'idEdicion' => $edicion->id,
        'puntos' => 6,
        'golesFavor' => 5,
        'golesContra' => 3,
        'diferenciaGoles' => 2,
        'jugado' => 3,
        'ganado' => 2,
        'perdido' => 1,
        'empatado' => 0,
    ]);

    $responseCategoria = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoriaA->id,
    ]));

    $responseCategoria->assertOk()
        ->assertSee('Sub 12')
        ->assertSee('Sub 13')
        ->assertSee('Mitai Blanco')
        ->assertDontSee('Mitai Negro');

    $responseGrupo = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoriaB->id,
        'idGrupo' => $grupoB->id,
    ]));

    $responseGrupo->assertOk()
        ->assertSee('Mitai Negro')
        ->assertDontSee('Mitai Blanco')
        ->assertSee('Sub 13 · Grupo Único');
});

it('usa por defecto la ultima categoria cargada cuando no llega idCategoria', function () {
    $admin = crearAdminTablaPosiciones();

    $edicion = Edicion::create([
        'nombre' => 'Edicion Categoria Default',
    ]);

    $categoriaVieja = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);

    $categoriaNueva = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 13',
    ]);

    $grupoViejo = Grupo::create([
        'idCategoria' => $categoriaVieja->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Único',
    ]);

    $grupoNuevo = Grupo::create([
        'idCategoria' => $categoriaNueva->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Único',
    ]);

    $equipoViejo = Equipo::create([
        'idCategoria' => $categoriaVieja->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Viejo',
        'foto' => 'escudo.png',
    ]);

    $equipoNuevo = Equipo::create([
        'idCategoria' => $categoriaNueva->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Nuevo',
        'foto' => 'escudo.png',
    ]);

    TablaPosicion::create([
        'idGrupo' => $grupoViejo->id,
        'idEquipo' => $equipoViejo->id,
        'idEdicion' => $edicion->id,
        'puntos' => 1,
        'golesFavor' => 1,
        'golesContra' => 1,
        'diferenciaGoles' => 0,
        'jugado' => 1,
        'ganado' => 0,
        'perdido' => 0,
        'empatado' => 1,
    ]);

    TablaPosicion::create([
        'idGrupo' => $grupoNuevo->id,
        'idEquipo' => $equipoNuevo->id,
        'idEdicion' => $edicion->id,
        'puntos' => 3,
        'golesFavor' => 2,
        'golesContra' => 0,
        'diferenciaGoles' => 2,
        'jugado' => 1,
        'ganado' => 1,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', [
        'idEdicion' => $edicion->id,
    ]));

    $response->assertOk()
        ->assertSee('Mitai Nuevo')
        ->assertDontSee('Mitai Viejo');
});

it('redirige al modulo tabla_posicion con contexto seguro cuando la query llega incompleta', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Incompleta', 'Sub 15', 'Grupo Z');

    $response = $this->actingAs($admin)->get(route('admin', [
        'idEdicion' => $contexto['edicion']->id,
        'tipo' => 'tabla_posicion',
    ]));

    $response->assertRedirect(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));
});

it('mantiene sin cambios la redireccion de otros tipos del panel', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion No Regresion', 'Sub 11', 'Grupo N');

    $response = $this->actingAs($admin)->get(route('admin', [
        'idEdicion' => $contexto['edicion']->id,
        'tipo' => 'partido',
    ]));

    $response->assertRedirect(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));
});

it('redirige al modulo tabla_posicion desde el selector global conservando el contexto completo', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Selector', 'Sub 15', 'Grupo D');

    $response = $this->actingAs($admin)->get(route('admin', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'tipo' => 'tabla_posicion',
    ]));

    $response->assertRedirect(route('tabla_posicion.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));
});

it('rechaza una categoria que no pertenece a la edicion seleccionada', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Valida', 'Sub 16', 'Grupo E');

    $edicionErronea = Edicion::create([
        'nombre' => 'Edicion Erronea',
    ]);

    $categoriaErronea = Categoria::create([
        'idEdicion' => $edicionErronea->id,
        'nombreCategoria' => 'Sub 99',
    ]);

    $token = Str::random(40);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->post(route('tabla_posicion.store'), csrfPayload($token, [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $categoriaErronea->id,
            'idGrupo' => $contexto['grupo']->id,
            'idEquipo' => $contexto['equipo']->id,
            'puntos' => 1,
            'golesFavor' => 1,
            'golesContra' => 0,
            'diferenciaGoles' => 1,
            'jugado' => 1,
            'ganado' => 1,
            'perdido' => 0,
            'empatado' => 0,
        ]));

    $response->assertSessionHasErrors(['idCategoria']);

    $this->assertDatabaseMissing('tabla_posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipo']->id,
    ]);
});

it('rechaza un grupo que no pertenece a la categoria seleccionada', function () {
    $admin = crearAdminTablaPosiciones();

    $edicion = Edicion::create([
        'nombre' => 'Edicion Cruce',
    ]);

    $categoriaA = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 17 A',
    ]);

    $categoriaB = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 17 B',
    ]);

    $grupoA = Grupo::create([
        'idCategoria' => $categoriaA->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    $grupoB = Grupo::create([
        'idCategoria' => $categoriaB->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo B',
    ]);

    $equipo = Equipo::create([
        'idCategoria' => $categoriaA->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Cruce',
        'foto' => 'escudo.png',
    ]);

    $token = Str::random(40);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->post(route('tabla_posicion.store'), csrfPayload($token, [
            'idEdicion' => $edicion->id,
            'idCategoria' => $categoriaA->id,
            'idGrupo' => $grupoB->id,
            'idEquipo' => $equipo->id,
            'puntos' => 4,
            'golesFavor' => 4,
            'golesContra' => 1,
            'diferenciaGoles' => 3,
            'jugado' => 2,
            'ganado' => 1,
            'perdido' => 0,
            'empatado' => 1,
        ]));

    $response->assertSessionHasErrors(['idGrupo']);

    $this->assertDatabaseMissing('tabla_posiciones', [
        'idEdicion' => $edicion->id,
        'idGrupo' => $grupoB->id,
        'idEquipo' => $equipo->id,
    ]);
});
