@extends('Panel.admin')

@section('contenido')
    <div class="mb-5">
        <label for="idCategoria" class="block text-base font-medium text-white mb-3">
            Selecciona la categoría
        </label>
        <form action="{{ route($tipo . '.create') }}" method="GET" id="seleccionar-categoria-form">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <select name="idCategoria" id="idCategoria"
                class="w-full rounded-md border border-gray-200 bg-white py-3 px-6 text-base font-medium text-gray-600 outline-none focus:border-indigo-500 focus:shadow-md mb-3">
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" {{ request('idCategoria') == $categoria->id ? 'selected' : '' }}>
                        {{ $categoria->nombreCategoria }}
                    </option>
                @endforeach
            </select>
            @if ($tipo === 'equipogrupo' || $tipo === 'partido')
                <select name="idGrupo" id="idGrupo"
                    class="w-full rounded-md border border-gray-200 bg-white py-3 px-6 text-base font-medium text-gray-600 outline-none focus:border-indigo-500 focus:shadow-md mb-3">
                    @foreach ($grupos as $grupo)
                        <option value="{{ $grupo->id }}">{{ $grupo->nombre }}</option>
                    @endforeach
                </select>
            @endif
            <div class="flex justify-center">
                <button type="submit"
                    class="bg-gray-800 hover:bg-gray-900 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg">
                    Continuar
                </button>
            </div>
        </form>
    </div>
    <script>
        document.getElementById('idCategoria').addEventListener('change', function() {
            var idCategoria = this.value;
            var idEdicion = document.querySelector('input[name="idEdicion"]').value;
            var url = "{{ route('seleccionar-categoria') }}";
            window.location.href =
                `${url}?idCategoria=${idCategoria}&idEdicion=${idEdicion}&tipo={{ $tipo }}`;
        });
    </script>
@endsection
