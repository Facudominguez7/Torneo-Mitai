<?php

// Feature tests para Podio (T8b, panel-admin-redesign): fusión de Campeones
// y Subcampeones. Cubre el listado (tarjetas por categoría, guardado con
// editar/borrar, sugerencia con Confirmar, links de carga manual, link al
// portal y estados vacíos), la confirmación (crea campeón+subcampeón en
// transacción, nunca confía en ids del cliente, rechaza duplicados/sin
// sugerencia/ids inválidos), los redirects de campeon.index/subcampeon.index
// y que el CRUD de campeón/subcampeón siga funcionando igual que antes.

use App\Models\Campeon;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Models\Subcampeon;
use App\Models\User;
use Illuminate\Support\Facades\Log;

function crearAdminPodio(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $atributos
 * @return array{edicion: Edicion, categoria: Categoria}
 */
function crearContextoPodio(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Podio Feature']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 15 Podio Feature',
    ]);

    return compact('edicion', 'categoria');
}

function crearEquipoParaPodio(Edicion $edicion, Categoria $categoria, string $nombre): Equipo
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
 * @param  array{edicion: Edicion, categoria: Categoria}  $contexto
 * @param  array<string, mixed>  $atributos
 */
function crearFinalParaPodio(array $contexto, Copa $copa, string $nombreFase, Equipo $local, Equipo $visitante, array $atributos = []): InstanciaFinal
{
    return InstanciaFinal::create(array_merge([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCopa' => $copa->id,
        'idFase' => Fase::firstOrCreate(['nombre' => $nombreFase])->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'horario' => '2026-05-01 10:00:00',
        'cancha' => 1,
    ], $atributos));
}

// ---- Acceso -----------------------------------------------------------

it('un invitado no puede ver Podio', function () {
    $this->get(route('podio.index'))->assertRedirect('/login');
});

it('un usuario sin rol admin no puede ver Podio', function () {
    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);

    $this->actingAs($usuario)->get(route('podio.index'))->assertRedirect('/');
});

// ---- Listado ------------------------------------------------------------

it('renderiza el encabezado con el subtitulo y el link al portal', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();

    $response = $this->actingAs($admin)->get(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Podio')
        ->assertSee('Campeones y subcampeones de cada copa, por categoría. Se sugieren solos cuando se juega la final.')
        ->assertSee('Ver en el portal')
        ->assertSee(e(route('campeones', ['idEdicion' => $contexto['edicion']->id])), false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener"', false);
});

it('renderiza el estado vacio de seleccionar edicion cuando no hay edicion en contexto', function () {
    $admin = crearAdminPodio();

    $this->actingAs($admin)->get(route('podio.index'))
        ->assertOk()
        ->assertSee('Seleccioná una edición para ver el podio');
});

it('renderiza el estado vacio con direccion cuando la edicion no tiene instancias ni podio cargado', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();

    $response = $this->actingAs($admin)->get(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Todavía no hay instancias finales ni podio cargado en esta edición')
        ->assertSee('Ir a Instancias finales')
        ->assertSee(e(route('instancia_final.index', ['idEdicion' => $contexto['edicion']->id])), false);
});

it('muestra la tarjeta de categoria con la sugerencia y el formulario Confirmar', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Sugerencia Feature']);
    $local = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Local Sugerencia Feature');
    $visitante = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Sugerencia Feature');
    crearFinalParaPodio($contexto, $copa, 'Final', $local, $visitante, [
        'golesEquipoLocal' => 3, 'golesEquipoVisitante' => 1,
    ]);

    $response = $this->actingAs($admin)->get(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee($contexto['categoria']->nombreCategoria)
        ->assertSee('Final jugada')
        ->assertSee('Sugerido por el resultado de la final')
        ->assertSee('Local Sugerencia Feature')
        ->assertSee('Visitante Sugerencia Feature')
        ->assertSee('action="'.route('podio.confirmar').'"', false)
        ->assertSee('name="idCopa" value="'.$copa->id.'"', false)
        ->assertSee('name="idCategoria" value="'.$contexto['categoria']->id.'"', false)
        ->assertSee('Confirmar');
});

it('muestra los campeones y subcampeones guardados con editar y borrar', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Guardado Feature']);
    $equipoCampeon = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Campeon Guardado Feature');
    $equipoSubcampeon = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Subcampeon Guardado Feature');

    $campeon = Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipoCampeon->id,
    ]);
    $subcampeon = Subcampeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipoSubcampeon->id,
    ]);

    $response = $this->actingAs($admin)->get(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Campeon Guardado Feature')
        ->assertSee('Subcampeon Guardado Feature')
        ->assertSee('Definido')
        ->assertSee(e(route('campeon.edit', $campeon)), false)
        ->assertSee(e(route('subcampeon.edit', $subcampeon)), false)
        ->assertSee('data-confirm-delete', false)
        ->assertSee('name="_method"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee(e(route('campeon.destroy', $campeon)), false)
        ->assertSee(e(route('subcampeon.destroy', $subcampeon)), false)
        ->assertSee('name="idEdicion" value="'.$campeon->idEdicion.'"', false);
});

it('muestra los links para cargar campeon y subcampeon manualmente cuando falta algo', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Manual Feature']);
    $equipoCampeon = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Campeon Manual Feature');

    Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipoCampeon->id,
    ]);

    $response = $this->actingAs($admin)->get(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Cargar subcampeón')
        ->assertSee(e(route('seleccionar-categoria', [
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'tipo' => 'subcampeon',
        ])), false)
        ->assertDontSee('Cargar campeón');
});

it('muestra todos los campeones duplicados de una copa para que ninguno quede inalcanzable', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Duplicados Feature']);
    $equipoA = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Campeon Duplicado A');
    $equipoB = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Campeon Duplicado B');

    $campeonA = Campeon::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'idCopa' => $copa->id, 'idEquipo' => $equipoA->id]);
    $campeonB = Campeon::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'idCopa' => $copa->id, 'idEquipo' => $equipoB->id]);

    $response = $this->actingAs($admin)->get(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Campeon Duplicado A')
        ->assertSee('Campeon Duplicado B')
        ->assertSee(e(route('campeon.destroy', $campeonA)), false)
        ->assertSee(e(route('campeon.destroy', $campeonB)), false);
});

// ---- Confirmar ----------------------------------------------------------

it('confirmar crea exactamente un campeon y un subcampeon con los equipos sugeridos', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Confirmar Feature']);
    $local = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Local Confirmar Feature');
    $visitante = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Confirmar Feature');
    crearFinalParaPodio($contexto, $copa, 'Final', $local, $visitante, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0,
    ]);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
    ]);

    $response->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('status');

    expect(Campeon::where('idCopa', $copa->id)->count())->toBe(1)
        ->and(Subcampeon::where('idCopa', $copa->id)->count())->toBe(1)
        ->and(Campeon::where('idCopa', $copa->id)->first()->idEquipo)->toBe($local->id)
        ->and(Subcampeon::where('idCopa', $copa->id)->first()->idEquipo)->toBe($visitante->id);
});

it('confirmar ignora los ids de equipo que mande el cliente', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Intruso Feature']);
    $local = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Local Intruso Feature');
    $visitante = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Intruso Feature');
    $intruso = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Intruso Feature');
    crearFinalParaPodio($contexto, $copa, 'Final', $local, $visitante, [
        'golesEquipoLocal' => 4, 'golesEquipoVisitante' => 1,
    ]);

    $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipoCampeon' => $intruso->id,
        'idEquipoSubcampeon' => $intruso->id,
        'idEquipo' => $intruso->id,
    ]);

    expect(Campeon::where('idCopa', $copa->id)->first()->idEquipo)->toBe($local->id)
        ->and(Subcampeon::where('idCopa', $copa->id)->first()->idEquipo)->toBe($visitante->id)
        ->and(Campeon::where('idEquipo', $intruso->id)->exists())->toBeFalse();
});

it('confirmar rechaza si ya existe campeon o subcampeon en esa copa', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Ya Cargada Feature']);
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Campeon Ya Cargado Feature');

    Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
    ]);

    $response->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error');
    expect(Subcampeon::where('idCopa', $copa->id)->count())->toBe(0)
        ->and(Campeon::where('idCopa', $copa->id)->count())->toBe(1);
});

it('confirmar rechaza si no hay una sugerencia vigente', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Sin Sugerencia Feature']);
    $local = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Local Sin Sugerencia Feature');
    $visitante = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Sin Sugerencia Feature');
    crearFinalParaPodio($contexto, $copa, 'Final', $local, $visitante); // sin jugar

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
    ]);

    $response->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error');
    expect(Campeon::where('idCopa', $copa->id)->count())->toBe(0)
        ->and(Subcampeon::where('idCopa', $copa->id)->count())->toBe(0);
});

it('confirmar dos veces seguidas para la misma copa no crea duplicados', function () {
    // Envío secuencial (doble click / dos pestañas, uno después del otro):
    // simula la carrera sin poder probar concurrencia real en Pest.
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Doble Envio Feature']);
    $local = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Local Doble Envio Feature');
    $visitante = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Doble Envio Feature');
    crearFinalParaPodio($contexto, $copa, 'Final', $local, $visitante, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0,
    ]);

    $payload = [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
    ];

    // Cada assertSessionHas se hace apenas llega la respuesta: el store de
    // sesión es compartido, así que si se difiere el segundo post ya
    // pisó el flash del primero.
    $primero = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), $payload);
    $primero->assertSessionHas('status');

    $segundo = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), $payload);
    $segundo->assertSessionHas('error', 'Esta copa ya tiene campeón o subcampeón cargado.');

    expect(Campeon::where('idCopa', $copa->id)->count())->toBe(1)
        ->and(Subcampeon::where('idCopa', $copa->id)->count())->toBe(1);
});

it('si falla la confirmacion revierte todo, avisa y loguea la excepcion', function () {
    Log::spy();

    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $copa = Copa::create(['nombre' => 'Copa Fallo Confirmar Feature']);
    $local = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Local Fallo Confirmar Feature');
    $visitante = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Fallo Confirmar Feature');
    crearFinalParaPodio($contexto, $copa, 'Final', $local, $visitante, [
        'golesEquipoLocal' => 3, 'golesEquipoVisitante' => 0,
    ]);

    Subcampeon::creating(function (): void {
        throw new RuntimeException('Fallo simulado al confirmar el podio');
    });

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
    ]);

    $response->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
    $response->assertSessionHas('error', 'No se pudo confirmar el podio. Intentá nuevamente.');

    expect(Campeon::where('idCopa', $copa->id)->count())->toBe(0)
        ->and(Subcampeon::where('idCopa', $copa->id)->count())->toBe(0);

    Log::shouldHaveReceived('error')->once()->with(
        'No se pudo confirmar el podio sugerido',
        Mockery::on(function (array $contexto) {
            return array_key_exists('exception', $contexto)
                && $contexto['exception'] instanceof RuntimeException
                && $contexto['exception']->getMessage() === 'Fallo simulado al confirmar el podio';
        })
    );
});

it('confirmar rechaza ids inexistentes o invalidos', function () {
    $admin = crearAdminPodio();

    $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), [
        'idEdicion' => 999999,
        'idCategoria' => 999999,
        'idCopa' => 999999,
    ])->assertSessionHasErrors(['idEdicion', 'idCategoria', 'idCopa']);

    $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('podio.confirmar'), [])
        ->assertSessionHasErrors(['idEdicion', 'idCategoria', 'idCopa']);
});

// ---- Redirects de campeon.index / subcampeon.index ----------------------

it('campeon.index redirige a Podio conservando idEdicion', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();

    $this->actingAs($admin)->get(route('campeon.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('subcampeon.index redirige a Podio conservando idEdicion', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();

    $this->actingAs($admin)->get(route('subcampeon.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('campeon.index sin idEdicion redirige a Podio sin parametros', function () {
    $admin = crearAdminPodio();

    $this->actingAs($admin)->get(route('campeon.index'))
        ->assertRedirect(route('podio.index'));
});

// ---- CRUD de Campeón / Subcampeón (paridad) ------------------------------

it('el alta de campeon usa x-ui.form-card, guarda y termina en Podio', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Alta Campeon Feature');
    $copa = Copa::create(['nombre' => 'Copa Alta Campeon Feature']);

    $urlCreate = route('campeon.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $this->actingAs($admin)->get($urlCreate)
        ->assertOk()
        ->assertSee('Guardar campeón')
        ->assertSee('id="idEquipo"', false)
        ->assertSee('id="idCopa"', false);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('campeon.store'), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCopa' => $copa->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('campeon.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(Campeon::where('idEquipo', $equipo->id)->exists())->toBeTrue();

    $this->actingAs($admin)->get($response->headers->get('Location'))
        ->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('la edicion de campeon usa Guardar cambios y actualiza', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Editar Campeon Feature');
    $copa = Copa::create(['nombre' => 'Copa Editar Campeon Feature']);
    $campeon = Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $this->actingAs($admin)->get(route('campeon.edit', $campeon))
        ->assertOk()
        ->assertSee('Guardar cambios')
        ->assertSee('action="'.route('campeon.update', $campeon->id).'"', false);

    $otroEquipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Otro Equipo Campeon Feature');
    $otraCopa = Copa::create(['nombre' => 'Otra Copa Editar Campeon Feature']);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->put(route('campeon.update', $campeon->id), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $otroEquipo->id,
        'idCopa' => $otraCopa->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('campeon.index', ['idEdicion' => $contexto['edicion']->id]));
    expect($campeon->refresh()->idEquipo)->toBe($otroEquipo->id);
});

it('la edicion de campeon preselecciona el equipo actual con su nombre', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Previo Campeon');
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Actual Campeon');
    $copa = Copa::create(['nombre' => 'Copa Preseleccion Campeon']);
    $registro = Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $html = $this->actingAs($admin)->get(route('campeon.edit', $registro))->assertOk()->getContent();

    preg_match('/<select[^>]*name="idEquipo"[^>]*>(.*?)<\/select>/s', $html, $selectEquipo);

    expect($selectEquipo[1] ?? '')
        ->toMatch('/<option value="'.$equipo->id.'"\s+selected\s*>\s*Equipo Actual Campeon\s*<\/option>/')
        ->not->toMatch('/<option value=""/');
});

it('destroy de campeon elimina el registro y termina en Podio', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Borrar Campeon Feature');
    $copa = Copa::create(['nombre' => 'Copa Borrar Campeon Feature']);
    $campeon = Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->delete(route('campeon.destroy', $campeon), [
        'idEdicion' => $contexto['edicion']->id,
    ]);

    $response->assertRedirect(route('campeon.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(Campeon::find($campeon->id))->toBeNull();
});

it('el alta de subcampeon usa x-ui.form-card, guarda y termina en Podio', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Alta Subcampeon Feature');
    $copa = Copa::create(['nombre' => 'Copa Alta Subcampeon Feature']);

    $urlCreate = route('subcampeon.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $this->actingAs($admin)->get($urlCreate)
        ->assertOk()
        ->assertSee('Guardar subcampeón')
        ->assertSee('id="idEquipo"', false)
        ->assertSee('id="idCopa"', false);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->post(route('subcampeon.store'), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCopa' => $copa->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('subcampeon.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(Subcampeon::where('idEquipo', $equipo->id)->exists())->toBeTrue();

    $this->actingAs($admin)->get($response->headers->get('Location'))
        ->assertRedirect(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));
});

it('la edicion de subcampeon usa Guardar cambios y actualiza', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Editar Subcampeon Feature');
    $copa = Copa::create(['nombre' => 'Copa Editar Subcampeon Feature']);
    $subcampeon = Subcampeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $this->actingAs($admin)->get(route('subcampeon.edit', $subcampeon))
        ->assertOk()
        ->assertSee('Guardar cambios')
        ->assertSee('action="'.route('subcampeon.update', $subcampeon->id).'"', false);

    $otroEquipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Otro Equipo Subcampeon Feature');
    $otraCopa = Copa::create(['nombre' => 'Otra Copa Editar Subcampeon Feature']);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->put(route('subcampeon.update', $subcampeon->id), [
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $otroEquipo->id,
        'idCopa' => $otraCopa->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response->assertRedirect(route('subcampeon.index', ['idEdicion' => $contexto['edicion']->id]));
    expect($subcampeon->refresh()->idEquipo)->toBe($otroEquipo->id);
});

it('la edicion de subcampeon preselecciona el equipo actual con su nombre', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Previo Subcampeon');
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Actual Subcampeon');
    $copa = Copa::create(['nombre' => 'Copa Preseleccion Subcampeon']);
    $registro = Subcampeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $html = $this->actingAs($admin)->get(route('subcampeon.edit', $registro))->assertOk()->getContent();

    preg_match('/<select[^>]*name="idEquipo"[^>]*>(.*?)<\/select>/s', $html, $selectEquipo);

    expect($selectEquipo[1] ?? '')
        ->toMatch('/<option value="'.$equipo->id.'"\s+selected\s*>\s*Equipo Actual Subcampeon\s*<\/option>/')
        ->not->toMatch('/<option value=""/');
});

it('destroy de subcampeon elimina el registro y termina en Podio', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();
    $equipo = crearEquipoParaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo Borrar Subcampeon Feature');
    $copa = Copa::create(['nombre' => 'Copa Borrar Subcampeon Feature']);
    $subcampeon = Subcampeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $response = $this->actingAs($admin)->withSession(['_token' => 'token-csrf-podio'])->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-podio'])->delete(route('subcampeon.destroy', $subcampeon), [
        'idEdicion' => $contexto['edicion']->id,
    ]);

    $response->assertRedirect(route('subcampeon.index', ['idEdicion' => $contexto['edicion']->id]));
    expect(Subcampeon::find($subcampeon->id))->toBeNull();
});

// ---- Navegación -----------------------------------------------------------

it('el sidebar muestra Podio y no Campeones ni Subcampeones sueltos', function () {
    $admin = crearAdminPodio();
    $contexto = crearContextoPodio();

    $response = $this->actingAs($admin)->get(route('podio.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('>Podio<', false)
        ->assertDontSee('>Campeones<', false)
        ->assertDontSee('>Subcampeones<', false);
});
