<?php

// Behavioral tests for x-ui.confirm-delete (T3, panel-admin-redesign).
//
// Replaces the 12-of-14 raw `<form method="post">@method('DELETE')@csrf...`
// delete forms across the Panel that submit without asking for confirmation.
// The form itself is real (POST + method spoofing + CSRF) so it works with
// JS disabled; the confirmation step (SweetAlert2 if window.Swal exists,
// else window.confirm) lives in resources/js/admin/confirm-delete.js via
// event delegation on [data-confirm-delete], so it never depends on Alpine.

use Illuminate\Support\Facades\Blade;

function renderUiConfirmDelete(string $attributes = '', string $slots = ''): string
{
    return Blade::render("<x-ui.confirm-delete {$attributes}>{$slots}</x-ui.confirm-delete>");
}

it('renders a real POST form with DELETE method spoofing and CSRF', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5"');

    expect($html)->toContain('<form')
        ->and($html)->toContain('method="POST"')
        ->and($html)->toContain('action="/panel/equipo/5"')
        ->and($html)->toContain('name="_method"')
        ->and($html)->toContain('value="DELETE"')
        ->and($html)->toContain('name="_token"');
});

it('marks the form for the event-delegated confirmation script', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5"');

    expect($html)->toContain('data-confirm-delete');
});

it('renders the label prop on the submit control', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5" label="Eliminar equipo"');

    expect($html)->toContain('Eliminar equipo');
});

it('defaults the label to Eliminar', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5"');

    expect($html)->toContain('>Eliminar<');
});

it('builds a default confirmation message from the entity prop', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5" entity="el equipo Mitai Verde"');

    expect($html)->toContain('data-confirm-message=')
        ->and($html)->toContain('el equipo Mitai Verde');
});

it('lets an explicit message prop override the default wording', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5" message="¿Seguro?"');

    expect($html)->toContain('¿Seguro?');
});

it('renders extra hidden fields for controllers that need more than the id', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5" :fields="[\'idEdicion\' => 21]"');

    expect($html)->toContain('type="hidden"')
        ->and($html)->toContain('name="idEdicion"')
        ->and($html)->toContain('value="21"');
});

it('renders the danger button variant for the submit control', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5"');

    expect($html)->toContain('bg-red-600');
});

it('uses a real submit button, not a link, so it keeps working without JS', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5"');

    expect($html)->toContain('type="submit"');
});

it('passes through arbitrary caller-supplied attributes via the attribute bag', function () {
    $html = renderUiConfirmDelete('action="/panel/equipo/5" data-testid="borrar-equipo-5"');

    expect($html)->toContain('data-testid="borrar-equipo-5"');
});

it('forwards a size to its button so it lines up with other row actions', function () {
    $pequeno = Blade::render('<x-ui.confirm-delete action="/Panel/equipo/1" size="sm" />');
    $normal = Blade::render('<x-ui.confirm-delete action="/Panel/equipo/1" />');

    expect($pequeno)->toContain('px-3 py-1.5 text-xs')
        ->and($normal)->not->toContain('px-3 py-1.5 text-xs');
});

it('does not double-escape an echoed action or entity', function () {
    $html = Blade::render(
        '<x-ui.confirm-delete action="{{ $accion }}" entity="{{ $entidad }}" />',
        ['accion' => '/Panel/equipo/1?idEdicion=10&tipo=x', 'entidad' => 'el equipo Ñandú & Cía']
    );

    expect($html)->toContain('action="/Panel/equipo/1?idEdicion=10&amp;tipo=x"')
        ->and($html)->toContain('¿Eliminar el equipo Ñandú &amp; Cía?')
        ->and($html)->not->toContain('&amp;amp;');
});
