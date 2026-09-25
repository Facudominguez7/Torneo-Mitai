@csrf
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">
        Nombre del Equipo
    </label>
    <input type="text" name="nombre" id="nombre" value="{{old('nombre', $equipo->nombre)}}"
        class="panel-form-input"
         />
</div>
<div class="mb-5">
    <label for="idEdicion" class="mb-3 block text-base font-medium text-white">
        Edicion
    </label>
    <select name="idEdicion" id="idEdicion"
        class="panel-form-input"
        >
        
            <option value="{{ $EdicionSeleccionada->id }}" selected>{{$EdicionSeleccionada->nombre}}</option>
       
    </select>
</div>
<div class="mb-5">
    <label for="category" class="mb-3 block text-base font-medium text-white">
        Categoría del Equipo
    </label>
    <select name="idCategoria" id="idCategoria"
        class="panel-form-input"
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
        class="panel-form-input"
        id="foto" name="foto">
</div>
