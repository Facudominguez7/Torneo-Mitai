<?php

// Preferencias y baja desde los enlaces firmados de cada correo.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Suscriptor;
use App\Services\SuscripcionService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config(['suscripciones.mailer' => 'resend', 'suscripciones.resend.sincronizar' => false]);
    Mail::fake();
});

/** @return array{0: Equipo, 1: Equipo} */
function preferenciasEquipos(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Preferencias']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoría 2013']);
    $crear = fn (string $nombre): Equipo => Equipo::create(['nombre' => $nombre, 'idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'foto' => 'e.png']);

    return [$crear('Huracán'), $crear('Defensores')];
}

function urlPreferencias(Suscriptor $suscriptor): string
{
    return app(SuscripcionService::class)->urlPreferencias($suscriptor);
}

/** @param  array<string, mixed>  $datos */
function guardarPreferencias(Suscriptor $suscriptor, array $datos): TestResponse
{
    return test()->withSession(['_token' => 'token-preferencias'])
        ->from(urlPreferencias($suscriptor))
        ->put(urlPreferencias($suscriptor), ['_token' => 'token-preferencias', ...$datos]);
}

it('muestra las preferencias con el enlace firmado, fuera del indice', function (): void {
    [$huracan, $defensores] = preferenciasEquipos();
    $suscriptor = Suscriptor::factory()->activo()->create([
        'email' => 'madre@correo.com', 'recibe_resumen_fecha' => true, 'recibe_avisos' => false, 'recibe_proxima_fecha' => true,
    ]);
    $suscriptor->equipos()->attach($huracan->id);

    $this->get(urlPreferencias($suscriptor))
        ->assertOk()
        ->assertSee('madre@correo.com')
        ->assertSee('value="resumen_fecha" checked', false)
        ->assertDontSee('value="avisos" checked', false)
        ->assertSee('value="'.$huracan->id.'" checked', false)
        ->assertSee('<option value="'.$defensores->id.'">Defensores</option>', false)
        ->assertSee('<meta name="robots" content="noindex', false)
        ->assertDontSee('rel="canonical"', false);
});

it('no muestra las preferencias sin la firma', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create();

    $this->get(route('suscripcion.preferencias', $suscriptor))->assertForbidden();
});

it('guarda los avisos y los equipos elegidos', function (): void {
    [$huracan, $defensores] = preferenciasEquipos();
    $suscriptor = Suscriptor::factory()->activo()->create();
    $suscriptor->equipos()->attach($huracan->id);

    guardarPreferencias($suscriptor, ['avisos' => ['avisos'], 'equipos' => [$defensores->id]])
        ->assertRedirect(urlPreferencias($suscriptor))
        ->assertSessionHas('suscripcion', 'Guardamos tus preferencias.');

    $suscriptor->refresh();
    expect($suscriptor->recibe_resumen_fecha)->toBeFalse()
        ->and($suscriptor->recibe_avisos)->toBeTrue()
        ->and($suscriptor->recibe_proxima_fecha)->toBeFalse()
        ->and($suscriptor->equipos->pluck('id')->all())->toBe([$defensores->id])
        ->and($suscriptor->resend_sincronizado_at)->toBeNull();
});

it('vuelve a activar a quien se habia dado de baja si guarda sus preferencias', function (): void {
    $suscriptor = Suscriptor::factory()->deBaja()->create();

    $this->get(urlPreferencias($suscriptor))->assertSee('Ahora no recibís avisos.');
    guardarPreferencias($suscriptor, ['avisos' => ['resumen_fecha']]);

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('valida las preferencias', function (array $datos, string $campo): void {
    $suscriptor = Suscriptor::factory()->activo()->create();

    guardarPreferencias($suscriptor, $datos)->assertSessionHasErrors($campo, null, 'suscripcion');

    expect($suscriptor->fresh()->recibe_resumen_fecha)->toBeTrue();
})->with([
    'nada elegido' => [['avisos' => ''], 'avisos'],
    'aviso desconocido' => [['avisos' => ['spam']], 'avisos.0'],
    'equipo inexistente' => [['avisos' => [], 'equipos' => [999999]], 'equipos.0'],
]);

it('no guarda preferencias sin la firma', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create();

    $this->withSession(['_token' => 'x'])
        ->put(route('suscripcion.preferencias.update', $suscriptor), ['_token' => 'x', 'avisos' => ['avisos']])
        ->assertForbidden();
});

it('el enlace de baja del correo abre una pagina que pide confirmar', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create(['email' => 'padre@correo.com']);
    $urlBaja = app(SuscripcionService::class)->urlBaja($suscriptor);

    $this->get($urlBaja)
        ->assertOk()
        ->assertSee('padre@correo.com')
        ->assertSee('action="'.e($urlBaja).'"', false)
        ->assertSee('Darme de baja')
        ->assertSee('href="'.e(urlPreferencias($suscriptor)).'"', false)
        ->assertSee('<meta name="robots" content="noindex', false);

    expect($suscriptor->fresh()->estaActivo())->toBeTrue();
});

it('la pagina de baja avisa si ya estaba dado de baja', function (): void {
    $suscriptor = Suscriptor::factory()->deBaja()->create();

    $this->get(app(SuscripcionService::class)->urlBaja($suscriptor))
        ->assertOk()
        ->assertSee('Ya estás dado de baja.')
        ->assertDontSee('Darme de baja');
});

it('no abre la pagina de baja sin la firma', function (): void {
    $suscriptor = Suscriptor::factory()->activo()->create();

    $this->get(route('suscripcion.baja.show', $suscriptor))->assertForbidden();
});
