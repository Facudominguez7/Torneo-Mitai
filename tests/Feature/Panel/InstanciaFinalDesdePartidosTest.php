<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $this->edicion = Edicion::create(['nombre' => 'Edicion Finales Desde Partidos']);
    $this->categoria = Categoria::create([
        'idEdicion' => $this->edicion->id,
        'nombreCategoria' => 'Sub 15 Desde Partidos',
    ]);
});

function equipoFinalDesdePartidos(Edicion $edicion, Categoria $categoria, string $nombre): Equipo
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

/**
 * @return array<string, mixed>
 */
function datosFinalDesdePartidos(Edicion $edicion, Categoria $categoria): array
{
    return [
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'idFase' => Fase::create(['nombre' => 'Final'])->id,
        'idEquipoLocal' => equipoFinalDesdePartidos($edicion, $categoria, 'Local Final')->id,
        'idEquipoVisitante' => equipoFinalDesdePartidos($edicion, $categoria, 'Visitante Final')->id,
        'horario' => '2026-12-05T10:00',
        'cancha' => 1,
    ];
}

it('ofrece cargar una instancia final desde Partidos con la categoria filtrada', function (): void {
    $url = route('instancia_final.create', [
        'idEdicion' => $this->edicion->id,
        'idCategoria' => $this->categoria->id,
        'origen' => 'partido',
    ]);

    $this->actingAs($this->admin)
        ->get(route('partido.index', ['idEdicion' => $this->edicion->id, 'idCategoria' => $this->categoria->id]))
        ->assertOk()
        ->assertSee('Instancia final')
        ->assertSee(e($url), false);
});

it('deriva al selector conservando el origen cuando Partidos no tiene categoria', function (): void {
    $url = route('seleccionar-categoria', [
        'idEdicion' => $this->edicion->id,
        'tipo' => 'instancia_final',
        'origen' => 'partido',
    ]);

    $this->actingAs($this->admin)
        ->get(route('partido.index', ['idEdicion' => $this->edicion->id]))
        ->assertOk()
        ->assertSee(e($url), false);

    $this->actingAs($this->admin)
        ->get($url)
        ->assertRedirect(route('instancia_final.create', [
            'origen' => 'partido',
            'idEdicion' => $this->edicion->id,
            'idCategoria' => $this->categoria->id,
        ]));
});

it('vuelve a Partidos desde el selector y el formulario cuando se entro desde ahi', function (): void {
    Categoria::create(['idEdicion' => $this->edicion->id, 'nombreCategoria' => 'Sub 17 Desde Partidos']);
    $volver = route('partido.index', ['idEdicion' => $this->edicion->id]);

    $this->actingAs($this->admin)
        ->get(route('seleccionar-categoria', [
            'idEdicion' => $this->edicion->id,
            'tipo' => 'instancia_final',
            'origen' => 'partido',
        ]))
        ->assertOk()
        ->assertSee('href="'.$volver.'"', false)
        ->assertSee('name="origen" value="partido"', false);

    $this->actingAs($this->admin)
        ->get(route('instancia_final.create', [
            'idEdicion' => $this->edicion->id,
            'idCategoria' => $this->categoria->id,
            'origen' => 'partido',
        ]))
        ->assertOk()
        ->assertSee('Nueva instancia final')
        ->assertSee(e(route('partido.index', ['idEdicion' => $this->edicion->id, 'idCategoria' => $this->categoria->id])), false)
        ->assertSee('name="origen" value="partido"', false);
});

it('ignora el origen en el selector de los demas tipos', function (): void {
    Categoria::create(['idEdicion' => $this->edicion->id, 'nombreCategoria' => 'Sub 17 Desde Partidos']);

    $this->actingAs($this->admin)
        ->get(route('seleccionar-categoria', [
            'idEdicion' => $this->edicion->id,
            'tipo' => 'campeon',
            'origen' => 'partido',
        ]))
        ->assertOk()
        ->assertSee('href="'.route('campeon.index', ['idEdicion' => $this->edicion->id]).'"', false)
        ->assertDontSee('name="origen"', false);
});

it('guarda la instancia final y regresa a Partidos', function (): void {
    $datos = datosFinalDesdePartidos($this->edicion, $this->categoria);

    $this->actingAs($this->admin)
        ->post(route('instancia_final.store'), [...$datos, 'origen' => 'partido'])
        ->assertRedirect(route('partido.index', [
            'idEdicion' => $this->edicion->id,
            'idCategoria' => $this->categoria->id,
        ]))
        ->assertSessionHas('status', 'Instancia final creada con éxito.');

    expect(InstanciaFinal::where('idEdicion', $this->edicion->id)->count())->toBe(1);
});

it('mantiene el regreso a Instancias finales sin origen o con un origen desconocido', function (?string $origen): void {
    $datos = datosFinalDesdePartidos($this->edicion, $this->categoria);

    $this->actingAs($this->admin)
        ->post(route('instancia_final.store'), array_filter([...$datos, 'origen' => $origen]))
        ->assertRedirect(route('instancia_final.index', ['idEdicion' => $this->edicion->id]))
        ->assertSessionHas('status', 'Partido creado con éxito.');
})->with(['sin origen' => null, 'origen desconocido' => 'https://externo.test']);
