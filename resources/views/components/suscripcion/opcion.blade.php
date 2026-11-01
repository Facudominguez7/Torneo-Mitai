@props(['nombre', 'valor', 'marcado' => false, 'detalle' => null])

{{-- Casilla de un aviso de la suscripción, con su descripción. --}}
<label class="flex cursor-pointer items-start gap-3 rounded-[10px] border border-tema-linea/15 bg-[var(--tm-superficie-bg)] px-3.5 py-3 transition hover:border-tema-acento/60 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand-gold">
    <input type="checkbox" name="{{ $nombre }}" value="{{ $valor }}" @checked($marcado)
        {{ $attributes->merge(['class' => 'mt-0.5 h-5 w-5 shrink-0 rounded border-tema-linea/30 bg-transparent text-brand-teal focus:ring-0 focus:ring-offset-0']) }}>
    <span class="flex flex-col gap-0.5">
        <span class="text-[15px] font-semibold text-tema-tinta">{{ $slot }}</span>
        @if ($detalle)
            <span class="text-sm text-tema-dim/70">{{ $detalle }}</span>
        @endif
    </span>
</label>
