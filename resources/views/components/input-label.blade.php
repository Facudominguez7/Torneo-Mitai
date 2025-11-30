@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-white/70']) }}>
    {{ $value ?? $slot }}
</label>
