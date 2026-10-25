<?php

// Términos y condiciones y Política de privacidad: páginas públicas enlazadas desde el sitio y el acceso.

it('muestra los terminos y condiciones sin iniciar sesion', function (): void {
    $this->get('/terminos-y-condiciones')->assertOk()
        ->assertSeeText('Términos y condiciones')
        ->assertSeeText('Última actualización')
        ->assertSee(route('privacidad'), false);
});

it('muestra la politica de privacidad con los datos que guarda el sitio', function (): void {
    $this->get('/politica-de-privacidad')->assertOk()
        ->assertSeeText('Política de privacidad')
        ->assertSeeText('DNI')
        ->assertSeeText('Google Analytics')
        ->assertSeeText('Ley 25.326')
        ->assertSee(route('terminos'), false);
});

it('enlaza las paginas legales desde el pie del sitio', function (): void {
    $this->blade("@include('layouts.footer')")
        ->assertSee(route('terminos'), false)
        ->assertSee(route('privacidad'), false);
});
