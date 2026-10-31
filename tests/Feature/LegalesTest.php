<?php

// Términos y condiciones y Política de privacidad: páginas públicas enlazadas desde el sitio y el acceso.

it('muestra los terminos y condiciones sin iniciar sesion', function (): void {
    $this->get('/terminos-y-condiciones')->assertOk()
        ->assertSee('Términos y condiciones')
        ->assertSee('Última actualización')
        ->assertSee(route('privacidad'), false);
});

it('muestra la politica de privacidad con los datos que guarda el sitio', function (): void {
    $this->get('/politica-de-privacidad')->assertOk()
        ->assertSee('Política de privacidad')
        ->assertSee('DNI')
        ->assertSee('Google Analytics')
        ->assertSee('Ley 25.326')
        ->assertSee(route('terminos'), false);
});

it('enlaza las paginas legales al pie de las paginas publicas sin credito ni redes', function (): void {
    // El pie con credito y redes se elimino por decision del usuario; los
    // enlaces legales viven en una linea propia al pie de cada pantalla.
    $this->get('/fixture')->assertOk()
        ->assertSee(route('terminos'), false)
        ->assertSee(route('privacidad'), false)
        ->assertDontSeeText('Sitio desarrollado por')
        ->assertDontSeeText('Conéctate conmigo en LinkedIn');
});

it('arma el indice con un ancla por articulo', function (string $url, array $articulos): void {
    $respuesta = $this->get($url)->assertOk();

    foreach ($articulos as $numero => $ancla) {
        $respuesta->assertSee('href="#'.$ancla.'"', false)
            ->assertSee('id="'.$ancla.'"', false)
            ->assertSee('ART. '.str_pad((string) ($numero + 1), 2, '0', STR_PAD_LEFT), false);
    }
})->with([
    'terminos' => ['/terminos-y-condiciones', ['alcance', 'aceptacion', 'cuentas', 'informacion-del-torneo', 'uso-permitido', 'panel-de-administracion', 'comunicaciones', 'propiedad-intelectual', 'suspension', 'cambios', 'ley-aplicable', 'contacto']],
    'privacidad' => ['/politica-de-privacidad', ['responsable', 'datos', 'usos', 'publicacion', 'menores', 'terceros', 'conservacion', 'derechos', 'seguridad', 'cambios']],
]);

it('marca la pestaña del documento actual y enlaza el otro', function (string $url, string $actual, string $otro): void {
    $this->get($url)->assertOk()
        ->assertSee('href="'.route($actual).'" aria-current="page"', false)
        ->assertSee('href="'.route($otro).'"', false);
})->with([
    'terminos' => ['/terminos-y-condiciones', 'terminos', 'privacidad'],
    'privacidad' => ['/politica-de-privacidad', 'privacidad', 'terminos'],
]);

it('muestra el resumen en tres puntos de cada documento', function (string $url, array $puntos): void {
    $this->get($url)->assertOk()
        ->assertSee('class="legal-resumen', false)
        ->assertSeeInOrder($puntos);
})->with([
    'terminos' => ['/terminos-y-condiciones', ['Información pública', 'Planilla oficial', 'Menores']],
    'privacidad' => ['/politica-de-privacidad', ['No se publica', 'Contraseña cifrada', 'Darte de baja']],
]);

it('muestra la cantidad de articulos y el tiempo de lectura', function (): void {
    $this->get('/terminos-y-condiciones')->assertOk()
        ->assertSee('12 artículos')
        ->assertSee('min de lectura');
});
