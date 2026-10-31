<?php

it('ubica el selector antes del título y por encima del buscador al abrirlo', function () {
    $vista = file_get_contents(resource_path('views/welcome.blade.php'));
    $estilos = file_get_contents(resource_path('css/app.css'));

    expect(strpos($vista, 'id="selector-edicion"'))->toBeLessThan(strpos($vista, '¿De qué equipo sos?'))
        ->and($estilos)->toMatch('/\.tm-selector-edicion\.abierto\s*\{\s*z-index:\s*51;/')
        ->and($vista)->toContain('id="seguir-equipo-selector" class="tm-anim-in tm-d2 relative z-50');
});

it('mantiene el velo mientras cualquiera de los dos selectores esté abierto', function () {
    $script = file_get_contents(resource_path('js/welcome.js'));

    expect($script)->toContain("!document.getElementById('selector-edicion')?.classList.contains('abierto')")
        ->and($script)->toContain("!document.getElementById('seguir-equipo-selector')?.classList.contains('abierto')");
});
