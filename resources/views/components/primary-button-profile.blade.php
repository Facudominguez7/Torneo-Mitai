<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-[#0e4b5e] px-6 py-3 text-sm font-bold uppercase tracking-widest text-white shadow-lg transition hover:bg-[#093240] focus:outline-none focus:ring-2 focus:ring-[#0e4b5e]/50']) }}>
    {{ $slot }}
</button>
