{{--
    x-ui.confirm-delete — delete control with a real confirmation step (T3,
    panel-admin-redesign).

    Replaces the 12-of-14 raw `<form method="post">@method('DELETE')@csrf`
    delete forms across the Panel, which submit immediately with no
    confirmation. The <form> here is real — real POST, real DELETE method
    spoofing, real CSRF — so it works with JavaScript disabled; only the
    confirmation *step* is JS-driven (event delegation on
    `[data-confirm-delete]`, registered once in
    resources/js/admin/confirm-delete.js). It never depends on Alpine: it
    uses window.Swal when SweetAlert2 is present, falling back to the
    native window.confirm() otherwise, so a delete is never left
    unprotected.

    `fields`: some controllers need more than the model id to resolve the
    delete (e.g. ControladorEquipo::destroy reads `idEdicion` from the
    request to scope which EquipoEdicion/TablaPosicion rows to remove) —
    `fields` renders one hidden input per array entry for that.
--}}
@props([
    'action',
    'label' => 'Eliminar',
    'message' => null,
    'entity' => null,
    'fields' => [],
    'size' => 'md',
])

@php
    $resolvedMessage = $message
        ?? ($entity
            ? "¿Eliminar {$entity}? Esta acción no se puede deshacer."
            : '¿Confirmás esta acción? No se puede deshacer.');
@endphp

<form
    method="POST"
    action="{{ $action }}"
    data-confirm-delete
    data-confirm-message="{{ $resolvedMessage }}"
    {{ $attributes->merge(['class' => 'inline-flex']) }}
>
    @csrf
    @method('DELETE')

    @foreach ($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <x-ui.button type="submit" variant="danger" :size="$size">
        {{ $label }}
    </x-ui.button>
</form>
