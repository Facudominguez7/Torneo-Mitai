<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Institucion;
use App\Models\User;
use App\Services\ValidadorReglasJugador;
use Illuminate\Support\Facades\DB;

function crearEquipoEnCategoriaRango(array $atributosCategoria): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Rango Anios', 'jugadores_por_planilla' => 30]);
    $categoria = Categoria::create(array_merge(['idEdicion' => $edicion->id], $atributosCategoria));
    $institucion = Institucion::create(['nombre' => 'Club Rango']);
    $equipo = Equipo::create([
        'nombre' => 'Equipo Rango',
        'foto' => 'rango.png',
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'idInstitucion' => $institucion->id,
    ]);
    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]);

    return compact('edicion', 'categoria', 'equipo');
}

function validarEdadEnCategoriaRango(array $contexto, string $fechaNacimiento): ?array
{
    return app(ValidadorReglasJugador::class)->validarSoloCategoriaYEdad(
        '40600111',
        $contexto['equipo']->id,
        $contexto['edicion']->id,
        $contexto['categoria']->id,
        $fechaNacimiento
    );
}

function crearAdminCategoriaRango(): User
{
    return User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
}

function postCategoriaRango($test, User $admin, array $datos)
{
    return $test->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-categoria-rango'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-categoria-rango'])
        ->post(route('categoria.store'), $datos);
}

it('usa anio_desde de la categoria y no su nombre para bloquear a los mayores', function () {
    $contexto = crearEquipoEnCategoriaRango([
        'nombreCategoria' => 'Categoria Mini',
        'anio_desde' => 2020,
        'anio_hasta' => 2021,
    ]);

    $resultado = validarEdadEnCategoriaRango($contexto, '2019-12-31');

    expect($resultado)->toBeArray()
        ->and($resultado['type'])->toBe('error')
        ->and($resultado['message'])->toContain('El jugador es mayor que la edad permitida.');
});

it('permite a los nacidos dentro del rango y a los mas chicos que suben', function (string $fechaNacimiento) {
    $contexto = crearEquipoEnCategoriaRango([
        'nombreCategoria' => 'Categoria Mini',
        'anio_desde' => 2020,
        'anio_hasta' => 2021,
    ]);

    expect(validarEdadEnCategoriaRango($contexto, $fechaNacimiento))->toBeNull();
})->with([
    'anio_desde' => '2020-01-01',
    'anio_hasta' => '2021-12-31',
    'mas chico' => '2022-06-15',
]);

it('sin rango cargado toma el anio mas viejo del nombre aunque este invertido', function () {
    $contexto = crearEquipoEnCategoriaRango(['nombreCategoria' => '2021/2020']);

    expect(validarEdadEnCategoriaRango($contexto, '2020-03-10'))->toBeNull()
        ->and(validarEdadEnCategoriaRango($contexto, '2019-03-10')['type'])->toBe('error');
});

it('sigue bloqueando por el nombre una categoria de un solo anio sin rango cargado', function () {
    $contexto = crearEquipoEnCategoriaRango(['nombreCategoria' => 'Categoria 2015']);

    expect(validarEdadEnCategoriaRango($contexto, '2014-05-10')['type'])->toBe('error')
        ->and(validarEdadEnCategoriaRango($contexto, '2015-05-10'))->toBeNull();
});

it('guarda el rango de anios al crear una categoria', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Rango Store']);

    $response = postCategoriaRango($this, crearAdminCategoriaRango(), [
        'nombreCategoria' => '2020/2021',
        'idEdicion' => $edicion->id,
        'anio_desde' => 2020,
        'anio_hasta' => 2021,
    ]);

    $response->assertRedirect(route('categoria.index', ['idEdicion' => $edicion->id]));
    $categoria = Categoria::where('nombreCategoria', '2020/2021')->firstOrFail();
    expect($categoria->anio_desde)->toBe(2020)
        ->and($categoria->anio_hasta)->toBe(2021);
});

it('rechaza un anio_hasta anterior al anio_desde', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Rango Invalido']);

    $response = postCategoriaRango($this, crearAdminCategoriaRango(), [
        'nombreCategoria' => '2020/2021',
        'idEdicion' => $edicion->id,
        'anio_desde' => 2021,
        'anio_hasta' => 2020,
    ]);

    $response->assertSessionHasErrors('anio_hasta');
    expect(Categoria::where('nombreCategoria', '2020/2021')->exists())->toBeFalse();
});

it('muestra los campos de rango en el formulario de categoria', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Rango Form']);

    $this->actingAs(crearAdminCategoriaRango())
        ->get(route('categoria.create', ['idEdicion' => $edicion->id]))
        ->assertOk()
        ->assertSee('name="anio_desde"', false)
        ->assertSee('name="anio_hasta"', false);
});

it('completa el rango de las categorias existentes a partir de su nombre', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Rango Backfill']);
    $ids = collect(['2020/2021', 'Categoria 2015', 'Femenino Sub 13', '2021-2020'])
        ->mapWithKeys(fn (string $nombre) => [$nombre => Categoria::create([
            'idEdicion' => $edicion->id,
            'nombreCategoria' => $nombre,
        ])->id]);
    $migracion = require database_path('migrations/2026_10_31_200000_add_rango_anios_to_categorias_table.php');

    $migracion->completarRangosDesdeNombre();

    $rango = fn (string $nombre) => (array) DB::table('categorias')
        ->where('id', $ids[$nombre])
        ->first(['anio_desde', 'anio_hasta']);
    expect($rango('2020/2021'))->toBe(['anio_desde' => 2020, 'anio_hasta' => 2021])
        ->and($rango('Categoria 2015'))->toBe(['anio_desde' => 2015, 'anio_hasta' => null])
        ->and($rango('Femenino Sub 13'))->toBe(['anio_desde' => null, 'anio_hasta' => null])
        ->and($rango('2021-2020'))->toBe(['anio_desde' => 2020, 'anio_hasta' => 2021]);
});

it('copia el rango de anios al crear la categoria al importar un equipo de otra edicion', function () {
    $origen = Edicion::create(['nombre' => 'Edicion Origen Rango']);
    $destino = Edicion::create(['nombre' => 'Edicion Destino Rango']);
    $categoriaOrigen = Categoria::create([
        'idEdicion' => $origen->id,
        'nombreCategoria' => 'Categoria Mini',
        'anio_desde' => 2020,
        'anio_hasta' => 2021,
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoriaOrigen->id,
        'idEdicion' => $origen->id,
        'nombre' => 'Equipo Mini Que Vuelve',
        'foto' => 'mini.png',
    ]);

    $this->actingAs(crearAdminCategoriaRango())
        ->withSession(['_token' => 'token-csrf-categoria-rango'])
        ->post(route('equipoedicion.store'), [
            '_token' => 'token-csrf-categoria-rango',
            'idEquipo' => $equipo->id,
            'idEdicion' => $destino->id,
            'edicionfiltro' => $origen->id,
            'idCategoria' => $categoriaOrigen->id,
        ])
        ->assertSessionHasNoErrors();

    $categoriaDestino = Categoria::where('idEdicion', $destino->id)->where('nombreCategoria', 'Categoria Mini')->firstOrFail();
    expect($categoriaDestino->anio_desde)->toBe(2020)
        ->and($categoriaDestino->anio_hasta)->toBe(2021);
});

it('permite cargar solo anio_desde para una categoria de un solo anio', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Rango Un Anio']);

    postCategoriaRango($this, crearAdminCategoriaRango(), [
        'nombreCategoria' => 'Categoria 2011',
        'idEdicion' => $edicion->id,
        'anio_desde' => 2011,
    ])->assertSessionHasNoErrors();

    $categoria = Categoria::where('nombreCategoria', 'Categoria 2011')->firstOrFail();
    expect($categoria->anio_desde)->toBe(2011)
        ->and($categoria->anio_hasta)->toBeNull();
});

it('exige anio_desde cuando se carga anio_hasta', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Rango Sin Desde']);

    postCategoriaRango($this, crearAdminCategoriaRango(), [
        'nombreCategoria' => 'Categoria 2011',
        'idEdicion' => $edicion->id,
        'anio_hasta' => 2011,
    ])->assertSessionHasErrors('anio_desde');

    expect(Categoria::where('nombreCategoria', 'Categoria 2011')->exists())->toBeFalse();
});
