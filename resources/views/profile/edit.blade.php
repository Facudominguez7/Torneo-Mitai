<x-app-layout :idEdicion="$EdicionSeleccionada ? $EdicionSeleccionada->id : null">
    {{-- Perfil con el diseño "Reglamento": comparte cabecera, índice y tema con las páginas legales. --}}
    @php
        $usuario = auth()->user();
        $nombre = $usuario->name ?: $usuario->email;
        $fechaRegistro = $usuario->created_at?->copy()->locale(app()->getLocale())->translatedFormat('d \de F \de Y');
        $rol = match ($usuario->rol) {
            'admin' => 'Administrador',
            'editor' => 'Editor',
            default => 'Usuario',
        };
        $secciones = array_filter([
            'datos' => 'Tus datos',
            'contrasena' => $usuario->google_id ? null : 'Contraseña',
            'eliminar' => 'Eliminar cuenta',
        ]);
        $numeros = array_flip(array_keys($secciones));
        $numero = fn (string $ancla): string => str_pad((string) ($numeros[$ancla] + 1), 2, '0', STR_PAD_LEFT);
    @endphp

    <div class="legal perfil">
        <header class="legal-cabecera">
            <svg aria-hidden="true" class="legal-lineas" viewBox="0 0 620 340" fill="none">
                <rect x="20" y="20" width="700" height="300" rx="4" />
                <circle cx="320" cy="170" r="90" />
                <line x1="320" y1="20" x2="320" y2="320" />
            </svg>

            <div class="legal-cabecera-in perfil-cabecera">
                <div class="perfil-avatar" aria-hidden="true">
                    @if ($usuario->google_avatar)
                        <img src="{{ $usuario->google_avatar }}" alt="" referrerpolicy="no-referrer"
                            onerror="this.hidden = true; this.nextElementSibling.hidden = false">
                        <span hidden>{{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}</span>
                    @else
                        <span>{{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}</span>
                    @endif
                </div>

                <div class="min-w-0">
                    <span class="legal-sobre">Tu cuenta</span>
                    <h1 class="legal-titulo perfil-nombre">{{ $nombre }}</h1>
                    <p class="legal-meta">
                        <span class="perfil-correo">{{ $usuario->email }}</span>
                        @if ($fechaRegistro)
                            <span>Desde {{ $fechaRegistro }}</span>
                        @endif
                        <span>{{ $rol }}</span>
                        @if ($usuario->google_id)
                            <span class="perfil-google">Con Google</span>
                        @endif
                    </p>
                </div>
            </div>
        </header>

        <div class="legal-cuerpo">
            <details class="legal-indice-movil">
                <summary>Secciones <span>{{ count($secciones) }} secciones</span></summary>
                <ol>
                    @foreach ($secciones as $ancla => $titulo)
                        <li><a href="#{{ $ancla }}"><span>{{ $numero($ancla) }}</span>{{ $titulo }}</a></li>
                    @endforeach
                </ol>
            </details>

            <nav class="legal-indice" aria-label="Secciones del perfil">
                <p>En esta página</p>
                <ol>
                    @foreach ($secciones as $ancla => $titulo)
                        <li><a href="#{{ $ancla }}" @class(['perfil-indice-peligro' => $ancla === 'eliminar'])><span>{{ $numero($ancla) }}</span>{{ $titulo }}</a></li>
                    @endforeach
                </ol>
            </nav>

            <div class="perfil-secciones">
                <section id="datos" class="perfil-seccion">
                    <h2><span>{{ $numero('datos') }}</span>Tus datos</h2>
                    <p class="perfil-descripcion">El nombre aparece en el menú y en los correos del torneo.</p>
                    @include('profile.partials.update-profile-information-form')
                </section>

                @isset($secciones['contrasena'])
                    <section id="contrasena" class="perfil-seccion">
                        <h2><span>{{ $numero('contrasena') }}</span>Contraseña</h2>
                        <p class="perfil-descripcion">Usá al menos ocho caracteres.</p>
                        @include('profile.partials.update-password-form')
                    </section>
                @endisset

                <section id="eliminar" class="perfil-seccion perfil-seccion--peligro">
                    <h2><span>{{ $numero('eliminar') }}</span>Eliminar cuenta</h2>
                    @include('profile.partials.delete-user-form')
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
