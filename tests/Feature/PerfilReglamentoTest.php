<?php

use App\Models\User;

it('muestra la cabecera Reglamento con los datos de la cuenta', function () {
    $usuario = User::factory()->create([
        'name' => 'Lucía Benítez',
        'email' => 'lucia.benitez@gmail.com',
        'rol' => 'regular',
    ]);

    $this->actingAs($usuario)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('class="legal perfil"', false)
        ->assertSee('Tu cuenta')
        ->assertSee('Lucía Benítez')
        ->assertSee('lucia.benitez@gmail.com')
        ->assertSee('Desde ')
        ->assertSee('<span>Usuario</span>', false)
        ->assertDontSee('Con Google');
});

it('nombra el rol del administrador', function () {
    $usuario = User::factory()->create(['rol' => 'admin']);

    $this->actingAs($usuario)
        ->get(route('profile.edit'))
        ->assertSee('<span>Administrador</span>', false);
});

it('numera las tres secciones y las enlaza desde el índice', function () {
    $usuario = User::factory()->create();

    $respuesta = $this->actingAs($usuario)->get(route('profile.edit'));

    $respuesta->assertSeeInOrder(['href="#datos"', 'href="#contrasena"', 'href="#eliminar"'], false)
        ->assertSeeInOrder([
            'id="datos"', '<span>01</span>Tus datos',
            'id="contrasena"', '<span>02</span>Contraseña',
            'id="eliminar"', '<span>03</span>Eliminar cuenta',
        ], false)
        ->assertSee('<span>3 secciones</span>', false);
});

it('oculta la contraseña en las cuentas de Google y renumera las secciones', function () {
    $usuario = User::factory()->create([
        'google_id' => '1234567890',
        'google_avatar' => 'https://lh3.googleusercontent.com/a/foto',
    ]);

    $this->actingAs($usuario)
        ->get(route('profile.edit'))
        ->assertSee('Con Google')
        ->assertDontSee('href="#contrasena"', false)
        ->assertDontSee('id="contrasena"', false)
        ->assertDontSee(route('password.update'), false)
        ->assertDontSee('name="password"', false)
        ->assertSee('<span>02</span>Eliminar cuenta', false)
        ->assertSee('<span>2 secciones</span>', false);
});

it('muestra la foto de Google con respaldo por inicial', function () {
    $usuario = User::factory()->create([
        'name' => 'Lucía Benítez',
        'google_id' => '1234567890',
        'google_avatar' => 'https://lh3.googleusercontent.com/a/foto',
    ]);

    $this->actingAs($usuario)
        ->get(route('profile.edit'))
        ->assertSee('src="https://lh3.googleusercontent.com/a/foto"', false)
        ->assertSee('referrerpolicy="no-referrer"', false)
        ->assertSee('onerror="this.hidden = true; this.nextElementSibling.hidden = false"', false)
        ->assertSee('<span hidden>L</span>', false);
});

it('mantiene los formularios con sus rutas y campos', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('profile.edit'))
        ->assertSee('action="'.route('profile.update').'"', false)
        ->assertSee('<input type="hidden" name="_method" value="patch">', false)
        ->assertSee('action="'.route('password.update').'"', false)
        ->assertSee('<input type="hidden" name="_method" value="put">', false)
        ->assertSee('action="'.route('profile.destroy').'"', false)
        ->assertSee('<input type="hidden" name="_method" value="delete">', false)
        ->assertSee('name="current_password"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertSee('Te vamos a pedir tu contraseña para confirmar.');
});

it('avisa cuando el correo no está verificado', function () {
    $usuario = User::factory()->unverified()->create();

    $this->actingAs($usuario)
        ->get(route('profile.edit'))
        ->assertSee('Tu correo todavía no está verificado.')
        ->assertSee('form="send-verification"', false);
});

it('confirma los datos guardados', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->withSession(['status' => 'profile-updated'])
        ->get(route('profile.edit'))
        ->assertSee('Datos guardados');
});

it('anima la cabecera y las secciones solo sin movimiento reducido', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.perfil-avatar')
        ->and($css)->toContain('.perfil-seccion--peligro')
        ->and($css)->toMatch('/prefers-reduced-motion: no-preference\)\s*\{[^@]*\.perfil-seccion[^{]*\{[^}]*animation/s');
});
