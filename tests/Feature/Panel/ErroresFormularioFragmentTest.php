<?php

// Behavioral tests for Panel.fragment._errores-formulario (T3,
// panel-admin-redesign). Restyles the fragment (bare <div> per error) into
// an accessible role="alert" summary. Included, unparameterized, by ~32
// Panel/**/create|edit.blade.php views, so every one of them benefits
// without any per-view change — the include contract (view name, no
// required variables, relies on the shared $errors ViewErrorBag) stays
// identical.

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

function renderResumenErroresFormulario(array $errores = []): string
{
    $bolsa = new ViewErrorBag;

    if ($errores !== []) {
        $bolsa = $bolsa->put('default', new MessageBag($errores));
    }

    view()->share('errors', $bolsa);

    return view('Panel.fragment._errores-formulario')->render();
}

it('no renderiza nada cuando no hay errores de validación', function () {
    $html = renderResumenErroresFormulario();

    expect(trim($html))->toBe('');
});

it('renderiza un alert accesible cuando hay errores', function () {
    $html = renderResumenErroresFormulario(['nombre' => 'El nombre es obligatorio.']);

    expect($html)->toContain('role="alert"');
});

it('incluye el título del resumen', function () {
    $html = renderResumenErroresFormulario(['nombre' => 'El nombre es obligatorio.']);

    expect($html)->toContain('Revisá los campos marcados');
});

it('lista cada mensaje de error de validación', function () {
    $html = renderResumenErroresFormulario([
        'nombre' => 'El nombre es obligatorio.',
        'idCategoria' => 'La categoría es obligatoria.',
    ]);

    expect($html)->toContain('El nombre es obligatorio.')
        ->and($html)->toContain('La categoría es obligatoria.');
});
