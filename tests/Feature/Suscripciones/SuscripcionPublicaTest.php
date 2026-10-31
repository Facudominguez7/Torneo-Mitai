<?php

// Interfaz pública de la suscripción: tarjeta de "Seguir mi equipo", aviso en la home, footer y página propia.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Suscriptor;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config(['suscripciones.mailer' => 'resend', 'suscripciones.resend.sincronizar' => false]);
    Mail::fake();
});

function suscripcionPublicaEquipo(): Equipo
{
    $edicion = Edicion::create(['nombre' => 'Edicion Suscripcion Publica']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoría 2014']);

    return Equipo::create(['nombre' => 'Huracán', 'idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'foto' => 'h.png']);
}

it('la home trae el formulario de avisos para la tarjeta del equipo seguido', function (): void {
    suscripcionPublicaEquipo();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="seguir-equipo-suscripcion"', false)
        ->assertSee('action="'.route('suscripcion.store').'"', false)
        ->assertSee('Avisame por correo')
        ->assertDontSee('name="equipos[]"', false)
        ->assertSee('value="resumen_fecha" checked', false)
        ->assertSee('value="proxima_fecha" checked', false)
        ->assertDontSee('value="avisos" checked', false)
        ->assertSee('Te mandamos un correo para confirmar.');
});

it('la home muestra el resultado de la suscripcion', function (): void {
    $this->withSession(['suscripcion' => 'Te enviamos un correo a tia@correo.com. Abrilo y confirmá la suscripción.'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('id="suscripcion-aviso"', false)
        ->assertSee('Te enviamos un correo a tia@correo.com. Abrilo y confirmá la suscripción.');
});

it('la home muestra el error de la suscripcion y conserva el correo escrito', function (): void {
    suscripcionPublicaEquipo();

    $this->withSession(['_token' => 'token-suscripcion'])
        ->from(route('home'))
        ->post(route('suscripcion.store'), ['_token' => 'token-suscripcion', 'email' => 'no-es-un-correo', 'avisos' => ['resumen_fecha']])
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('email', null, 'suscripcion');

    $this->get(route('home'))
        ->assertSee('Ese correo no parece válido. Revisalo.')
        ->assertSee('value="no-es-un-correo"', false);
});

it('pide al menos un aviso', function (): void {
    $this->withSession(['_token' => 'token-suscripcion'])
        ->from(route('suscripcion.create'))
        ->post(route('suscripcion.store'), ['_token' => 'token-suscripcion', 'email' => 'tio@correo.com', 'avisos' => ''])
        ->assertSessionHasErrors(['avisos' => 'Elegí al menos un aviso.'], null, 'suscripcion');

    expect(Suscriptor::query()->count())->toBe(0);
});

it('ignora los equipos que lleguen en el pedido', function (): void {
    $equipo = suscripcionPublicaEquipo();

    $this->withSession(['_token' => 'token-suscripcion'])
        ->from(route('home'))
        ->post(route('suscripcion.store'), ['_token' => 'token-suscripcion', 'email' => 'tio@correo.com', 'equipos' => [$equipo->id], 'avisos' => ['resumen_fecha']])
        ->assertSessionHasNoErrors();

    $suscriptor = Suscriptor::query()->sole();
    expect($suscriptor->recibe_resumen_fecha)->toBeTrue()
        ->and($suscriptor->equipos)->toBeEmpty();
});

it('con solo equipos y sin avisos pide elegir un aviso', function (): void {
    $equipo = suscripcionPublicaEquipo();

    $this->withSession(['_token' => 'token-suscripcion'])
        ->from(route('home'))
        ->post(route('suscripcion.store'), ['_token' => 'token-suscripcion', 'email' => 'tio@correo.com', 'equipos' => [$equipo->id], 'avisos' => ''])
        ->assertSessionHasErrors(['avisos' => 'Elegí al menos un aviso.'], null, 'suscripcion');

    expect(Suscriptor::query()->count())->toBe(0);
});

it('tiene una pagina propia para suscribirse sin seguir un equipo', function (): void {
    $this->get(route('suscripcion.create'))
        ->assertOk()
        ->assertSee('<title>Avisos por correo · Torneos Mita&#039;í</title>', false)
        ->assertSee('action="'.route('suscripcion.store').'"', false)
        ->assertSee('name="avisos[]"', false)
        ->assertDontSee('name="equipos[]"', false);
});

it('la pagina propia muestra el resultado y los errores', function (): void {
    $this->withSession(['suscripcion' => '¡Listo! Ya estás suscripto a los avisos de Torneos Mita\'í.'])
        ->get(route('suscripcion.create'))
        ->assertSee('¡Listo! Ya estás suscripto a los avisos de Torneos Mita&#039;í.', false);
});

it('los footers enlazan a los avisos por correo', function (): void {
    $enlace = 'href="'.route('suscripcion.create').'"';

    $this->get(route('home'))->assertSee($enlace, false);
    $this->get(route('terminos'))->assertSee($enlace, false);
});
