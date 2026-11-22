<?php

it('alinea la barra fija de filtros con la capsula de navegacion', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));
    $vista = file_get_contents(resource_path('views/layouts/instancias-finales.blade.php'));

    expect($css)
        ->toContain('--nav-capsula-ancho: min(60rem, calc(100% - 2rem))')
        ->toContain('--nav-capsula-ancho: calc(100% - 1.5rem)')
        ->toMatch('/\.nav-condensada \.nav-capsula \{\s+max-width: var\(--nav-capsula-ancho\)/')
        ->toMatch('/\.finales-barra--flotante \{\s+max-width: var\(--nav-capsula-ancho\)/');

    expect($vista)
        ->toContain("classList.toggle('finales-barra--flotante'")
        ->toContain("navegacion.addEventListener('transitionend', ubicarBarra)");
});
