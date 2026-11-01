{{-- Enlaces legales al pie de las pantallas públicas: línea propia, discreta,
    sin crédito ni redes (el footer con crédito se eliminó por decisión del
    usuario). Reutilizable en cualquier layout con los tokens de tema. --}}
<nav aria-label="Legales"
    class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 pb-4 pt-6 text-xs text-tema-dim/60 sm:px-6">
    <a href="{{ route('terminos') }}"
        class="underline-offset-4 transition hover:text-tema-tinta hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light/60">
        Términos y condiciones
    </a>
    <span aria-hidden="true" class="text-tema-dim/40">·</span>
    <a href="{{ route('privacidad') }}"
        class="underline-offset-4 transition hover:text-tema-tinta hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light/60">
        Política de privacidad
    </a>
    <span aria-hidden="true" class="text-tema-dim/40">·</span>
    <a href="{{ route('suscripcion.create') }}"
        class="underline-offset-4 transition hover:text-tema-tinta hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light/60">
        Avisos por correo
    </a>
</nav>
