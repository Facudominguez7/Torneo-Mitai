<?php

// UX of the per-team Lista de buena fe screen: one header with the squad
// counter, a single baja dialog that asks for the motivo, status badges next
// to the player's name, an instant filter by DNI or name and rows that
// stack as cards on mobile.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function crearAdminDetalleUx(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

/**
 * @return array{edicion: Edicion, categoria: Categoria, equipoEdicion: EquipoEdicion}
 */
function crearEquipoDetalleUx(string $nombreEquipo = 'Mitai Azul'): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Detalle UX']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 13 Detalle',
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombreEquipo,
        'foto' => 'escudo.png',
    ]);
    $equipoEdicion = EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    return compact('edicion', 'categoria', 'equipoEdicion');
}

function crearJugadorDetalleUx(array $contexto, string $dni, array $jugador = [], bool $activo = true): ListaBuenaFe
{
    Jugador::firstOrCreate(['dni' => $dni], array_merge([
        'nombre' => 'Jugador',
        'apellido' => $dni,
    ], $jugador));

    return ListaBuenaFe::create([
        'dni_jugador' => $dni,
        'idEquipo' => $contexto['equipoEdicion']->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2013-05-10',
        'fecha_alta' => now()->subDays(3),
        'activo' => $activo,
        'fecha_baja' => $activo ? null : now()->subDay(),
    ]);
}

function verDetalleUx(array $contexto, array $query = []): TestResponse
{
    return test()->actingAs(crearAdminDetalleUx())->get(route('lista-buena-fe.show', array_merge([
        'idEquipo' => $contexto['equipoEdicion']->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
    ], $query)));
}

it('shows the team once in the header with the edition and the squad counter', function () {
    $contexto = crearEquipoDetalleUx();
    crearJugadorDetalleUx($contexto, '40500001');
    crearJugadorDetalleUx($contexto, '40500002');

    $html = verDetalleUx($contexto)->assertOk()
        ->assertSee('Edicion Detalle UX')
        ->assertSee('data-contador-jugadores', false)
        ->assertSee('2/18')
        ->assertSee('Quedan 16 lugares')
        ->assertDontSee('Lista completa')
        ->getContent();

    expect(substr_count($html, 'Mitai Azul'))->toBe(1);
});

it('marks a full list as complete without the red limit error', function () {
    $contexto = crearEquipoDetalleUx();
    foreach (range(1, 18) as $i) {
        crearJugadorDetalleUx($contexto, (string) (40600000 + $i));
    }

    verDetalleUx($contexto)->assertOk()
        ->assertSee('18/18')
        ->assertSee('Lista completa')
        ->assertSee('Para sumar a alguien, primero da de baja a un jugador.')
        ->assertDontSee('ha alcanzado el límite')
        ->assertDontSee('id="formAgregarJugador"', false);
});

it('counts the whole squad even while a DNI search narrows the rows', function () {
    $contexto = crearEquipoDetalleUx();
    crearJugadorDetalleUx($contexto, '40700001');
    crearJugadorDetalleUx($contexto, '40700002');

    verDetalleUx($contexto, ['buscar_dni' => '40700001'])->assertOk()
        ->assertSee('2/18')
        ->assertSee('40700001')
        ->assertDontSee('40700002');
});

it('does not paint the PDF download as a destructive action', function () {
    $contexto = crearEquipoDetalleUx();
    crearJugadorDetalleUx($contexto, '40800001');

    $html = verDetalleUx($contexto)->assertOk()->assertSee('Descargar PDF')->getContent();
    $rutaPdf = e(route('lista-buena-fe.pdf', [
        'idEquipo' => $contexto['equipoEdicion']->idEquipo,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    preg_match('/<a href="'.preg_quote($rutaPdf, '/').'"[^>]*class="([^"]*)"/', $html, $coincidencia);

    expect($coincidencia)->not->toBeEmpty()
        ->and($coincidencia[1])->not->toContain('bg-red-600');
});

it('asks for the baja motivo in one dialog instead of an input per row', function () {
    $contexto = crearEquipoDetalleUx();
    $primero = crearJugadorDetalleUx($contexto, '40900001', ['nombre' => 'Lionel', 'apellido' => 'Gómez']);
    $segundo = crearJugadorDetalleUx($contexto, '40900002');

    $html = verDetalleUx($contexto)->assertOk()
        ->assertSee('id="modal-baja-jugador"', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('id="formBajaJugador"', false)
        ->assertSee('maxlength="255"', false)
        ->assertSee('data-nombre-completo="Lionel Gómez"', false)
        ->assertSee(e(route('lista-buena-fe.dar-baja', $primero->id)), false)
        ->assertSee(e(route('lista-buena-fe.dar-baja', $segundo->id)), false)
        ->assertDontSee("confirm('¿Dar de baja a este jugador?')", false)
        ->getContent();

    expect(substr_count($html, 'name="motivo_baja"'))->toBe(1)
        ->and(substr_count($html, 'data-nombre-completo='))->toBe(2);
});

it('drops the Estado column and keeps the status badges in the player cell', function () {
    $contexto = crearEquipoDetalleUx();
    $registro = crearJugadorDetalleUx($contexto, '41000001');
    $registro->update(['conflictivo' => true, 'resuelto' => false]);

    verDetalleUx($contexto)->assertOk()
        ->assertDontSee('<th scope="col">Estado</th>', false)
        ->assertSeeInOrder(['data-celda="jugador"', 'En revisión', 'data-label="Camiseta"'], false);
});

it('renders the instant filter and searchable rows for active and inactive players', function () {
    $contexto = crearEquipoDetalleUx();
    crearJugadorDetalleUx($contexto, '41100001', ['nombre' => 'Martín', 'apellido' => 'Núñez']);
    crearJugadorDetalleUx($contexto, '41100002', [], false);

    verDetalleUx($contexto)->assertOk()
        ->assertSee('id="buscar_dni"', false)
        ->assertSee('data-filtro-jugadores', false)
        ->assertSee('data-buscar="41100001 martin nunez"', false)
        ->assertSee('data-buscar="41100002 jugador 41100002"', false)
        ->assertSee('id="filtro-sin-resultados"', false);
});
