<?php

it('carga Barlow en el head compartido por el sitio y el panel', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&display=swap', false);
});

it('declara Barlow como fuente sans de Tailwind en lugar de la de Breeze', function (): void {
    $configuracion = file_get_contents(base_path('tailwind.config.js'));

    expect($configuracion)->toContain("sans: ['Barlow', ...defaultTheme.fontFamily.sans]")
        ->and($configuracion)->not->toContain('Figtree');
});
