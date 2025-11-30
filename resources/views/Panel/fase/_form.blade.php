@csrf
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Nombre de la Fase
    </label>
    <input type="text" name="nombre" id="nombre" value="{{old('nombre', $fase->nombre)}}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
         />
</div>
<div class="hidden">
    <label for="idEdicion" class="mb-3 block text-base font-medium text-white">
        Edicion
    </label>
    <input type="number" name="idEdicion" id="idEdicion" value="{{ $EdicionSeleccionada->id }}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md" />
</div>


