@php
    $pideContrasena = ! auth()->user()->google_id;
@endphp

<p class="perfil-descripcion">
    Se borran tu cuenta y tus preferencias. Los datos del torneo, como resultados y planillas, no cambian.
    @if ($pideContrasena)
        Te vamos a pedir tu contraseña para confirmar.
    @endif
</p>

<button type="button" class="perfil-boton-peligro" x-data=""
    x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
    Eliminar cuenta
</button>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" maxWidth="lg" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" class="perfil-confirmacion">
        @csrf
        @method('delete')

        <h2>¿Eliminar tu cuenta?</h2>
        <p>No se puede deshacer. Vas a salir de la sesión y tendrás que registrarte de nuevo para volver a entrar.</p>

        @if ($pideContrasena)
            <x-acceso.campo id="password" name="password" label="Contraseña" revelable
                :errores="$errors->userDeletion->get('password')" autocomplete="current-password" />
        @endif

        <div class="perfil-confirmacion-acciones">
            <x-acceso.boton type="button" variante="secundario" x-on:click="$dispatch('close')">Cancelar</x-acceso.boton>
            <button type="submit" class="perfil-boton-peligro perfil-boton-peligro--lleno">Eliminar definitivamente</button>
        </div>
    </form>
</x-modal>
