<div x-data="{ resending: false }">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}" x-on:submit="resending = true">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="perfil-formulario">
        @csrf
        @method('patch')

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="perfil-ancho perfil-papel">
                <x-acceso.aviso>
                    <strong>Tu correo todavía no está verificado.</strong>
                    <span>
                        Verificalo para recibir los avisos del torneo.
                        <button form="send-verification" x-bind:disabled="resending"
                            x-text="resending ? 'Enviando…' : 'Reenviar código'"
                            class="perfil-enlace">Reenviar código</button>
                    </span>
                </x-acceso.aviso>
            </div>
        @endif

        @if (session('status') === 'verification-code-sent')
            <div class="perfil-ancho perfil-papel">
                <x-acceso.aviso tipo="exito">
                    <strong>Te enviamos un nuevo código de verificación.</strong>
                    <span>Revisá tu bandeja de entrada y también la carpeta de spam.</span>
                </x-acceso.aviso>
            </div>
        @endif

        @if (session('email-error'))
            <div class="perfil-ancho perfil-papel">
                <x-acceso.aviso tipo="error">{{ session('email-error') }}</x-acceso.aviso>
            </div>
        @endif

        <x-acceso.campo id="name" name="name" label="Nombre completo" :value="old('name', $user->name)"
            :errores="$errors->get('name')" required autocomplete="name" />

        <x-acceso.campo id="email" name="email" type="email" label="Correo electrónico" :value="old('email', $user->email)"
            :errores="$errors->get('email')" required autocomplete="username" />

        <div class="perfil-acciones">
            <x-acceso.boton class="sm:w-auto">Guardar cambios</x-acceso.boton>

            @if (session('status') === 'profile-updated')
                <p role="status" class="perfil-ok">Datos guardados</p>
            @endif
        </div>
    </form>
</div>
