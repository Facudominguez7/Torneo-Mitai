<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, follow">
    <title>Gestionar suscripción por email</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-black text-white">
    <main class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute inset-0 -z-10">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Fondo cancha"
                class="h-full w-full object-cover object-center" fetchpriority="high" decoding="async">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/95"></div>
        </div>

        <x-marca-agua-portal />
        <div class="relative z-0 mx-auto flex min-h-screen w-full max-w-xl items-center px-4 py-10">
            <section class="w-full rounded-3xl border border-white/15 bg-white/10 p-6 shadow-xl backdrop-blur sm:p-8">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="Logo Torneo Mita'i"
                        class="h-14 w-14 object-contain" width="56" height="56">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-yellow-300">Torneo Mita'i</p>
                        <h1 class="text-xl font-black text-white">Gestionar suscripción por email</h1>
                    </div>
                </div>

                <p class="mt-4 text-sm text-white/80">
                    Ingresá tu correo para dejar de recibir resúmenes automáticos del torneo.
                </p>

                @if(session('success'))
                    <div class="mt-4 rounded-xl border border-emerald-400/40 bg-emerald-500/10 p-3 text-sm text-emerald-200">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mt-4 rounded-xl border border-red-400/40 bg-red-500/10 p-3 text-sm text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('email.baja-general.procesar') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-semibold text-white/90">Correo electrónico</label>
                        <input id="email" name="email" type="email" required value="{{ old('email') }}"
                            class="mt-1 w-full rounded-xl border border-white/20 bg-black/50 px-3 py-2.5 text-sm text-white placeholder:text-white/40 focus:border-yellow-300 focus:outline-none focus:ring-2 focus:ring-yellow-300/40">
                    </div>

                    <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-yellow-300 px-4 py-2.5 text-sm font-bold text-black shadow-lg shadow-yellow-500/30 transition hover:-translate-y-0.5 hover:bg-yellow-200">
                        Darme de baja
                    </button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
