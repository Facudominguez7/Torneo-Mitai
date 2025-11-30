<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-white/20 bg-white/10 px-5 py-2 text-sm font-semibold uppercase tracking-[0.3em] text-white/80 shadow-[0_12px_24px_-20px_rgba(255,255,255,0.5)] transition hover:bg-white/15 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/30 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
