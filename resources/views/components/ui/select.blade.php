{{--
    x-ui.select — labeled <select> (issue #110, Phase 1, T1.3).

    Extracted from the 79 hand-styled <select> elements across
    resources/views/Panel/**/_form.blade.php (e.g.
    Panel/equipogrupo/_form.blade.php, Panel/valla/_form.blade.php), each
    retyping the same border/rounded/focus utility classes.

    @tailwindcss/forms is active and already resets native <select>
    styling (appearance, base border, focus ring removal). This component
    layers the design-system border/focus treatment on top of that reset
    rather than fighting it — it does not reimplement what the plugin
    already normalizes.

    The caller supplies <option> elements through the default slot, so the
    component never owns the option data source (a Panel _form.blade.php
    keeps its own $foreach loop, old()/selected logic, etc.).
--}}
@props([
    'label' => null,
    'name' => null,
    'id' => null,
    // panel | portal — which surface this select is drawn on.
    'tone' => 'panel',
    'required' => false,
])

@php
    $toneClasses = [
        'panel' => 'border-gray-300 rounded-md bg-white text-gray-700 focus:border-brand-navy focus:ring-brand-navy/30',
        'portal' => 'rounded-md border-white/20 bg-white/5 text-white focus:border-brand-gold/60 focus:ring-brand-gold/40',
    ];

    // Unknown tone falls back to panel instead of rendering unstyled — see
    // the same decision on x-ui.button.
    $resolvedTone = array_key_exists($tone, $toneClasses) ? $tone : 'panel';

    $classes = collect([
        'block w-full py-2 px-3 text-sm shadow-sm',
        'focus:outline-none focus:ring-2 focus-visible:ring-2',
        $toneClasses[$resolvedTone],
    ])->implode(' ');

    $resolvedId = $id ?? $name;
@endphp

<div>
    @if ($label)
        <label for="{{ $resolvedId }}" class="mb-1 block text-sm font-medium {{ $resolvedTone === 'portal' ? 'text-white' : 'text-gray-700' }}">
            {{ $label }}
        </label>
    @endif

    <select
        {{ $attributes->merge([
            'id' => $resolvedId,
            'name' => $name,
            'class' => $classes,
        ]) }}
        @required($required)
        @if ($required) aria-required="true" @endif
    >{{ $slot }}</select>
</div>
