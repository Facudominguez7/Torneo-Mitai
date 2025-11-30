@csrf
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Categoria
    </label>
    <input type="text" name="nombreCategoria" id="nombreCategoria" value="{{old('nombre', $categoria->nombreCategoria)}}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
         />
    <input class="hidden" type="number" name="idEdicion" id="idEdicion" value="{{$EdicionSeleccionada->id}}">
</div>


