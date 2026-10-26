<?php

use App\Models\User;

it('devuelve 404 a los invitados que intentan entrar al panel', function () {
    $this->get('/Panel/equipo')->assertNotFound();
});

it('redirige a la home a los usuarios autenticados sin rol admin', function () {
    $usuario = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($usuario)->get('/Panel/equipo')->assertRedirect('/');
});

it('mantiene el acceso al panel para usuarios admin', function () {
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);

    $this->actingAs($admin)->get('/Panel/equipo')->assertOk();
});
