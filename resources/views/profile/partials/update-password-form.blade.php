<form method="post" action="{{ route('password.update') }}" class="perfil-formulario">
    @csrf
    @method('put')

    <div class="perfil-ancho">
        <x-acceso.campo id="update_password_current_password" name="current_password" label="Contraseña actual" revelable
            :errores="$errors->updatePassword->get('current_password')" autocomplete="current-password" />
    </div>

    <x-acceso.campo id="update_password_password" name="password" label="Nueva contraseña" revelable
        :errores="$errors->updatePassword->get('password')" autocomplete="new-password" />

    <x-acceso.campo id="update_password_password_confirmation" name="password_confirmation" label="Confirmar contraseña" revelable
        :errores="$errors->updatePassword->get('password_confirmation')" autocomplete="new-password" />

    <div class="perfil-acciones">
        <x-acceso.boton class="sm:w-auto">Guardar nueva contraseña</x-acceso.boton>

        @if (session('status') === 'password-updated')
            <p role="status" class="perfil-ok">Contraseña actualizada</p>
        @endif
    </div>
</form>
