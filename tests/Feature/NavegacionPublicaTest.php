<?php

use App\Models\Edicion;
use App\Models\User;

function crearEdicionDePrueba(string $nombre): Edicion
{
    return Edicion::create([
        'nombre' => $nombre,
    ]);
}

function navPublica(string $html): string
{
    // La navegación pública renderiza UNA sola barra sticky (Ronda 4). Se
    // concatenan todas las navs para inspeccionarlas juntas.
    preg_match_all('/<nav\b.*?<\/nav>/s', $html, $coincidencias);

    return implode("\n", $coincidencias[0]);
}

function linkDeSeccion(string $html, string $textoEsperado): ?string
{
    preg_match_all('/<a\b[^>]*>(.*?)<\/a>/s', $html, $coincidencias, PREG_SET_ORDER);

    foreach ($coincidencias as $coincidencia) {
        if (str_contains(trim(strip_tags($coincidencia[1])), $textoEsperado)) {
            return $coincidencia[0];
        }
    }

    return null;
}

it('muestra los cinco links de seccion en la navegacion publica del home', function () {
    $edicion = crearEdicionDePrueba('Edicion Activa');

    $html = $this->get(route('home'))->getContent();
    $nav = navPublica($html);

    // Ronda 4: una sola barra sticky unificada; la barra separada de
    // secciones ya no existe.
    expect(substr_count($html, 'aria-label="Navegación principal"'))->toBe(1)
        ->and($html)->not->toContain('Secciones del torneo')
        ->and($html)->toContain('sticky top-0');

    foreach (['Inicio', 'Partidos', 'Posiciones', 'Goleadores', 'Finales'] as $seccion) {
        expect(linkDeSeccion($nav, $seccion))->not->toBeNull();
    }

    expect(linkDeSeccion($nav, 'Partidos'))->toContain('idEdicion='.$edicion->id)
        ->and(linkDeSeccion($nav, 'Posiciones'))->toContain('idEdicion='.$edicion->id)
        ->and(linkDeSeccion($nav, 'Goleadores'))->toContain('idEdicion='.$edicion->id)
        ->and(linkDeSeccion($nav, 'Finales'))->toContain('idEdicion='.$edicion->id);
});

it('resuelve por fallback del composer la edicion mas reciente cuando el controlador no la pasa', function () {
    Edicion::create(['nombre' => 'Edicion Antigua']);
    $reciente = crearEdicionDePrueba('Edicion Reciente');

    $nav = navPublica($this->get(route('fixture'))->getContent());

    expect(linkDeSeccion($nav, 'Partidos'))->toContain('idEdicion='.$reciente->id)
        ->and(linkDeSeccion($nav, 'Inicio'))->toContain('idEdicion='.$reciente->id)
        ->and(linkDeSeccion($nav, 'Posiciones'))->toContain('idEdicion='.$reciente->id);
});

it('no incluye idCategoria en el link de posiciones de la navegacion', function () {
    crearEdicionDePrueba('Edicion Posiciones');

    $nav = navPublica($this->get(route('home'))->getContent());

    expect(linkDeSeccion($nav, 'Posiciones'))->not->toBeNull()
        ->and(linkDeSeccion($nav, 'Posiciones'))->not->toContain('idCategoria');
});

it('marca con aria-current solo el link de partidos dentro de la vista fixture', function () {
    $edicion = crearEdicionDePrueba('Edicion Fixture');

    $nav = navPublica($this->get(route('fixture', ['idEdicion' => $edicion->id]))->getContent());

    // Cada link de seccion vive TRES veces en el DOM (links inline de desktop,
    // chips mobile y tab bar movil; por viewport solo uno es visible): el
    // activo se marca exactamente una vez por representacion y los demas no
    // se marcan.
    expect(preg_match_all('/<a\b[^>]*aria-current="page"/', $nav))->toBe(3);

    expect(linkDeSeccion($nav, 'Partidos'))->toContain('aria-current="page"');

    foreach (['Inicio', 'Posiciones', 'Goleadores', 'Finales'] as $seccion) {
        expect(linkDeSeccion($nav, $seccion))->not->toContain('aria-current');
    }
});

it('marca solo el link de inicio con aria-current en el home', function () {
    crearEdicionDePrueba('Edicion Home');

    $nav = navPublica($this->get(route('home'))->getContent());

    // Mismo criterio: el activo aparece marcado en las tres representaciones
    // (desktop inline + chips mobile + tab bar movil), nunca en los otros links.
    expect(preg_match_all('/<a\b[^>]*aria-current="page"/', $nav))->toBe(3);

    expect(linkDeSeccion($nav, 'Inicio'))->toContain('aria-current="page"');

    foreach (['Partidos', 'Posiciones', 'Goleadores', 'Finales'] as $seccion) {
        expect(linkDeSeccion($nav, $seccion))->not->toContain('aria-current');
    }
});

it('usa la edicion explicita del controlador sin pisarla con la ultima edicion', function () {
    $vieja = crearEdicionDePrueba('Edicion Vieja');
    $reciente = crearEdicionDePrueba('Edicion Reciente Explicita');

    $nav = navPublica($this->get(route('fixture', ['idEdicion' => $vieja->id]))->getContent());

    expect(linkDeSeccion($nav, 'Partidos'))->toContain('idEdicion='.$vieja->id)
        ->and(linkDeSeccion($nav, 'Partidos'))->not->toContain('idEdicion='.$reciente->id)
        ->and(linkDeSeccion($nav, 'Finales'))->toContain('idEdicion='.$vieja->id);
});

it('muestra las opciones de navegacion correspondientes al rol del usuario', function () {
    crearEdicionDePrueba('Edicion Roles');
    $usuario = User::factory()->create(['name' => 'Jugador Prueba']);
    $admin = User::factory()->create(['name' => 'Admin Prueba', 'rol' => 'admin']);

    $navInvitado = navPublica($this->get(route('home'))->getContent());
    expect($navInvitado)->toContain('Iniciar sesión')
        ->and($navInvitado)->not->toContain('Perfil')
        ->and($navInvitado)->not->toContain('Panel de administración');

    $navUsuario = navPublica($this->actingAs($usuario)->get(route('home'))->getContent());
    expect($navUsuario)->toContain('Jugador Prueba')
        ->and($navUsuario)->toContain('Perfil')
        ->and($navUsuario)->not->toContain('Iniciar sesión')
        ->and($navUsuario)->not->toContain('Panel de administración');

    $navAdmin = navPublica($this->actingAs($admin)->get(route('home'))->getContent());
    expect($navAdmin)->toContain('Perfil')
        ->and($navAdmin)->toContain('Panel de administración');
});

it('responde correctamente en home y fixture con una edicion valida', function () {
    $edicion = crearEdicionDePrueba('Edicion Viva');

    $this->get(route('home'))->assertOk();
    $this->get(route('fixture', ['idEdicion' => $edicion->id]))->assertOk();
});

it('carga la foto de google sin referer y conserva la inicial si la foto falla', function () {
    crearEdicionDePrueba('Edicion Avatar');
    $usuario = User::factory()->create(['name' => 'Facundo Prueba']);
    $usuario->forceFill(['google_avatar' => 'https://lh3.googleusercontent.com/a/foto-de-prueba'])->save();

    $nav = navPublica($this->actingAs($usuario)->get(route('home'))->getContent());

    expect($nav)->toContain('src="https://lh3.googleusercontent.com/a/foto-de-prueba"')
        ->and($nav)->toContain('referrerpolicy="no-referrer"')
        ->and($nav)->toMatch('/onerror="[^"]*nextElementSibling[^"]*"/')
        ->and($nav)->toMatch('/<span[^>]*hidden[^>]*>\s*F\s*<\/span>/');
});

it('mantiene en una sola fila los controles de la cuenta del administrador', function () {
    crearEdicionDePrueba('Edicion Admin');
    $admin = User::factory()->create(['name' => 'Admin Con Un Nombre Bastante Largo', 'rol' => 'admin']);
    $css = file_get_contents(resource_path('css/app.css'));

    $nav = navPublica($this->actingAs($admin)->get(route('home'))->getContent());

    expect($nav)->toContain('sm:flex-nowrap')
        ->and($nav)->toMatch('/<span class="xl:hidden">Panel<\/span>/')
        ->and($nav)->toMatch('/class="[^"]*truncate[^"]*">Admin Con Un Nombre Bastante Largo</')
        ->and($css)->not->toContain('.nav-compactable { max-width: 32rem;');
});

it('no tiñe con el tema claro el texto del menu de la cuenta sobre fondo oscuro', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    // El botón y el menú de la cuenta conservan su degradado navy en ambos temas;
    // su texto tiene que seguir blanco aunque el resto de la barra pase a navy.
    foreach (['[class*="text-white"]:not([class*="bg-gradient"]):not([class*="text-white/"])', '[class*="text-white/60"]', '[class*="text-white/80"]', 'a:hover:not([class*="bg-gradient"])', 'button:hover'] as $selector) {
        expect($css)->toContain('.tm-home #nav-principal '.$selector.':not([class*="bg-gradient"] *)');
    }
});

it('ancla a la derecha el menu de la cuenta para que no desborde en el celular', function () {
    crearEdicionDePrueba('Edicion Menu');
    $usuario = User::factory()->create(['name' => 'Usuario Menu']);

    $nav = navPublica($this->actingAs($usuario)->get(route('home'))->getContent());

    // El contenedor del menú es absoluto: sin end-0 arranca en el borde izquierdo
    // del botón y, con el botón angosto del celular, se sale de la pantalla.
    expect($nav)->toMatch('/<div x-show="open"[^>]*class="absolute[^"]*\bend-0\b/');
});
