<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand-navy px-6 py-3 text-sm font-bold uppercase tracking-widest text-white shadow-lg transition hover:bg-[#093240] focus:outline-none focus:ring-2 focus:ring-brand-navy/50']) }}>
    {{ $slot }}
</button>
