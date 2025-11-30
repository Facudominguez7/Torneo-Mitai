<button {{ $attributes->merge(['type' => 'submit', 'class' => 'group inline-flex items-center justify-center rounded-full bg-gradient-to-r from-yellow-300 via-amber-300 to-yellow-400 px-6 py-3 text-sm font-semibold text-black shadow-lg shadow-yellow-500/40 transition-transform duration-300 hover:-translate-y-0.5 hover:shadow-yellow-500/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-300 active:scale-95']) }}>
    {{ $slot }}
</button>
