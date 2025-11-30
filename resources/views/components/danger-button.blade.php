<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border border-rose-200/60 bg-gradient-to-r from-rose-600 via-red-500 to-rose-700 px-5 py-2 text-sm font-semibold uppercase tracking-[0.3em] text-white shadow-[0_18px_36px_-18px_rgba(244,63,94,0.7)] transition hover:brightness-115 focus:outline-none focus:ring-2 focus:ring-rose-300/60']) }}>
    {{ $slot }}
</button>
