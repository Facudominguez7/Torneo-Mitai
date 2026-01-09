@include('head')
<body class="font-sans antialiased ">
    <div class="min-h-screen bg-[--color-primary] flex flex-col">
        @include('layouts.navigation')
        
        {{-- Alerta de email verificado exitosamente --}}
        @if(request()->query('verified') == '1')
            <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed top-20 left-1/2 -translate-x-1/2 z-50 max-w-md w-full mx-4">
                <div class="rounded-2xl bg-gradient-to-r from-emerald-500/90 to-green-600/90 backdrop-blur-sm border border-emerald-400/30 shadow-xl shadow-emerald-900/30 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-white">¡Email verificado correctamente! 🎉</p>
                            <p class="text-xs text-white/80 mt-1">Ahora recibirás notificaciones sobre las novedades del torneo.</p>
                        </div>
                        <button @click="show = false" class="flex-shrink-0 text-white/70 hover:text-white transition">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <script>
                // Auto-cerrar después de 8 segundos
                setTimeout(() => {
                    const alert = document.querySelector('[x-data="{ show: true }"]');
                    if (alert) alert.__x.$data.show = false;
                }, 8000);
            </script>
        @endif

        <!-- Page Content -->
        <main class="flex-grow">
            {{ $slot }}
        </main>
        @include('layouts.footer')
    </div>
</body>
