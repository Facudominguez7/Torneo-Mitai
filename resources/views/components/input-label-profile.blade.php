@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold uppercase tracking-[0.25em] text-white/60']) }}>
    {{ $value ?? $slot }}
</label>
