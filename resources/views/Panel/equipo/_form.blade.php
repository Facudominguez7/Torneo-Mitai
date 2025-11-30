@csrf
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Nombre del Equipo
    </label>
    <input type="text" name="nombre" id="nombre" value="{{old('nombre', $equipo->nombre)}}"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
         />
</div>
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Edicion
    </label>
    <select name="idEdicion" id="idEdicion"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
        >
        
            <option value="{{ $EdicionSeleccionada->id }}" selected>{{$EdicionSeleccionada->nombre}}</option>
       
    </select>
</div>
<div class="mb-5">
    <label for="category" class="mb-3 block text-base font-medium text-white">
        Categoría del Equipo
    </label>
    <select name="idCategoria" id="idCategoria"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
        >
        @foreach ($categorias as $id => $nombreCategoria)
            <option {{old('idCategoria', $equipo->idCategoria) == $id ? 'selected' : ''}} value="{{$id}}">{{$nombreCategoria}}</option>
        @endforeach
    </select>
</div>
<div class="mb-5">
    <label for="foto" class="mb-3 block text-base font-medium text-white">
        Agregar Logo
    </label>
    <input type="file"
        class="w-full rounded-md border border-[#e0e0e0] bg-white py-3 px-6 text-base font-medium text-[#6B7280] outline-none focus:border-[#6A64F1] focus:shadow-md"
        id="foto" name="foto">
</div>