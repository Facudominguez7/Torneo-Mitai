@props(['nombre', 'valor', 'marcado' => false, 'detalle' => null])

{{-- Casilla de un aviso de la suscripción, con su descripción. --}}
<label class="flex cursor-pointer items-start gap-3 rounded-[10px] border border-white/10 bg-white/[0.04] px-3.5 py-3 transition hover:border-brand-teal-light/60 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand-gold">
    <input type="checkbox" name="{{ $nombre }}" value="{{ $valor }}" @checked($marcado)
        {{ $attributes->merge(['class' => 'mt-0.5 h-5 w-5 shrink-0 rounded border-white/30 bg-transparent text-brand-teal focus:ring-0 focus:ring-offset-0']) }}>
    <span class="flex flex-col gap-0.5">
        <span class="text-[15px] font-semibold text-white">{{ $slot }}</span>
        @if ($detalle)
            <span class="text-sm text-white/70">{{ $detalle }}</span>
        @endif
    </span>
</label>
