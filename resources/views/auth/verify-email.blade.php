<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-1 text-center">
            <h2 class="text-2xl font-bold text-white">Verificá tu email</h2>
            <p class="text-sm text-white/60">Para recibir notificaciones del torneo necesitás verificar tu correo.</p>
        </div>

        <div class="text-sm text-white/80 space-y-3">
            <p>
                ¡Gracias por registrarte! Antes de continuar, verificá tu email haciendo clic en el enlace que te enviamos.
            </p>
            <p class="text-white/60">
                📧 Si no recibiste el email, revisá tu carpeta de <strong>spam</strong> o solicitá uno nuevo.
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="rounded-lg bg-green-500/20 border border-green-500/30 p-4">
                <p class="text-sm text-green-300 flex items-center gap-2">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    ¡Listo! Te enviamos un nuevo enlace de verificación.
                </p>
                <p class="text-xs text-green-200/70 mt-2 ml-7">
                    📬 Revisá tu bandeja de entrada y también la <strong>carpeta de spam</strong> si no lo encontrás.
                </p>
            </div>
        @endif

        @if (session('email-error'))
            <div class="rounded-lg bg-red-500/20 border border-red-500/30 p-4">
                <p class="text-sm text-red-300 flex items-center gap-2">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    {{ session('email-error') }}
                </p>
            </div>
        @endif

        <div class="space-y-4">
            <form method="POST" action="{{ route('verification.send') }}" id="verify-form">
                @csrf
                <button type="submit" id="verify-btn"
                    class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-yellow-300 via-amber-300 to-yellow-400 px-6 py-3 text-sm font-semibold text-black shadow-lg shadow-yellow-500/40 transition-transform duration-300 hover:-translate-y-0.5 hover:shadow-yellow-500/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-300 active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed">
                    {{-- Spinner --}}
                    <svg id="verify-spinner" class="hidden animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span id="verify-text">Reenviar email de verificación</span>
                </button>
            </form>

            <a class="block text-center text-sm font-semibold text-white/60 transition hover:text-white"
                href="{{ route('home') }}">
                ← Volver al inicio
            </a>
        </div>

        {{-- Información adicional --}}
        <div class="mt-6 p-4 rounded-lg bg-white/5 border border-white/10">
            <h4 class="text-sm font-semibold text-white mb-2">¿Por qué verificar?</h4>
            <ul class="text-xs text-white/60 space-y-1">
                <li>✉️ Recibir resultados de partidos por email</li>
                <li>📅 Enterarte de nuevos partidos programados</li>
                <li>📢 Recibir anuncios importantes del torneo</li>
            </ul>
            <p class="text-xs text-white/40 mt-2">
                Podés darte de baja en cualquier momento desde el email.
            </p>
        </div>
    </div>

    {{-- Script para loader --}}
    <script>
        document.getElementById('verify-form').addEventListener('submit', function() {
            const btn = document.getElementById('verify-btn');
            const spinner = document.getElementById('verify-spinner');
            const text = document.getElementById('verify-text');
            
            btn.disabled = true;
            spinner.classList.remove('hidden');
            text.textContent = 'Enviando...';
        });
    </script>
</x-guest-layout>
