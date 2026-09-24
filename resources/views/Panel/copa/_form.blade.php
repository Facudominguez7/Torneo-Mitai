@csrf
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Nombre de la Copa
    </label>
    <input type="text" name="nombre" id="nombre" value="{{old('nombre', $copa->nombre)}}"
        class="panel-form-input"
         />
</div>
<div class="hidden">
    <label for="idEdicion" class="mb-3 block text-base font-medium text-white">
        Edicion
    </label>
    <input type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}"
        class="panel-form-input" />
</div>



