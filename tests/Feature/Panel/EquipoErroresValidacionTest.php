<?php

// Parity test: the Equipos create form still shows validation errors, now
// through the restyled Panel.fragment._errores-formulario (role="alert"),
// after an invalid submission redirects back with errors and old input.

use App\Models\Edicion;
use App\Models\User;

it('muestra el resumen de errores accesible al enviar el alta de equipo sin nombre', function () {
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
    $edicion = Edicion::create(['nombre' => 'Edicion Errores Equipo']);

    // El contenedor de testing expone APP_ENV=local, así que el middleware
    // CSRF valida de verdad (ver PartidoCrudTest::cabecerasCsrf); se inyecta
    // el token explícitamente para la petición mutante.
    $respuestaInvalida = $this->actingAs($admin)
        ->from(route('equipo.create', ['idEdicion' => $edicion->id]))
        ->withSession(['_token' => 'token-csrf-equipo-errores'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-equipo-errores'])
        ->post(route('equipo.store'), [
            'idCategoria' => '',
            'idEdicion' => $edicion->id,
        ]);

    $respuestaInvalida->assertSessionHasErrors(['nombre', 'idCategoria']);

    $formulario = $this->actingAs($admin)->get(route('equipo.create', ['idEdicion' => $edicion->id]));

    $formulario->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Revisá los campos marcados');
});
