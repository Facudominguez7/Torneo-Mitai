<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-yellow-200/40 bg-gradient-to-r from-yellow-500/80 via-orange-500/75 to-rose-500/75 px-6 py-2.5 text-sm font-semibold text-white shadow-[0_12px_28px_-18px_rgba(249,115,22,0.7)] transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-yellow-200/40']) }}>
    {{ $slot }}
</button>
