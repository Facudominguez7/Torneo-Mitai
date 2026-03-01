@extends('Panel.admin')

@section('contenido')
    <div class="container mx-auto px-4 py-6">
        <h1 class="text-3xl font-bold mb-6 text-white">📋 Gestión de Lista de Buena Fe</h1>

        @if(isset($equiposExcedidos) && count($equiposExcedidos) > 0)
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p class="font-bold">Advertencia</p>
                <p>Los siguientes equipos tienen más de 18 jugadores en la lista de buena fe:</p>
                <ul class="list-disc list-inside">
                    @foreach($equiposExcedidos as $equipoExcedido)
                        <li>
                            <strong>{{ $equipoExcedido->equipo->nombre }}</strong> 
                            <span class="text-gray-600">({{ $equipoExcedido->categoria->nombreCategoria }})</span>: 
                            {{ $equipoExcedido->total }} jugadores
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Selectores de Edición y Categoría --}}
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <form method="GET" action="{{ route('lista-buena-fe.index') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                {{-- Selector de Edición --}}
                <div>
                    <label for="idEdicion" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Edición:</label>
                    <select name="idEdicion" id="idEdicion" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="">-- Seleccione una edición --</option>
                        @foreach ($ediciones as $edicion)
                            <option value="{{ $edicion->id }}" 
                                    {{ $EdicionSeleccionada && $EdicionSeleccionada->id == $edicion->id ? 'selected' : '' }}>
                                {{ $edicion->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Selector de Categoría --}}
                <div>
                    <label for="idCategoria" class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Categoría:</label>
                    <select name="idCategoria" id="idCategoria" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()"
                            {{ !$EdicionSeleccionada ? 'disabled' : '' }}>
                        <option value="">-- Todas las categorías --</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}" 
                                    {{ $idCategoria == $categoria->id ? 'selected' : '' }}>
                                {{ $categoria->nombreCategoria }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        {{-- Buscador de Equipos y Botón de Migración --}}
        @if ($EdicionSeleccionada)
            <div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
                <form action="{{ route('lista-buena-fe.index') }}" method="GET" class="flex justify-center w-full md:w-auto">
                    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                    <input type="hidden" name="idCategoria" value="{{ $idCategoria ?? '' }}">
                    <div class="relative w-full max-w-md">
                        <input type="text" name="search" value="{{ request('search') }}" 
                            placeholder="Buscar equipo por nombre..." 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <button type="submit" class="absolute right-0 top-0 mt-2 mr-3 text-gray-500 hover:text-blue-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('lista-buena-fe.index', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria ?? '']) }}" 
                           class="ml-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 flex items-center shadow-sm">
                            Limpiar
                        </a>
                    @endif
                </form>

                @php
                    $urlImpresionMasiva = route('lista-buena-fe.imprimir-planillas', [
                        'idEdicion' => $EdicionSeleccionada->id,
                        'idCategoria' => $idCategoria,
                        'search' => request('search'),
                    ]);
                @endphp
                <button type="button"
                        data-print-url="{{ $urlImpresionMasiva }}"
                        class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow flex items-center justify-center"
                        onclick="printExternal(this.dataset.printUrl)">
                    <span class="mr-2">🖨️</span> Imprimir Todas las Planillas
                </button>

                {{-- Botón de Migración Masiva --}}
                <form method="POST" action="{{ route('lista-buena-fe.migrar-todos') }}" class="w-full md:w-auto">
                    @csrf
                    <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
                    <button type="submit" 
                            class="w-full md:w-auto bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow flex items-center justify-center"
                            onclick="return confirm('¿Estás seguro de querer migrar los jugadores de TODOS los equipos de esta edición? Esto puede tomar unos momentos.')">
                        <span class="mr-2">🔄</span> Migrar Todos los Equipos
                    </button>
                </form>

                <a href="{{ route('lista-buena-fe.conflictivos') }}" 
                   class="w-full md:w-auto bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded shadow flex items-center justify-center">
                    <span class="mr-2">📋</span> Revisión de Jugadores
                </a>
            </div>
        @endif

        @if ($EdicionSeleccionada)
            {{-- Grid de Equipos --}}
            @if ($equipos->isEmpty())
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8 text-center">
                    <p class="text-yellow-700 text-lg">
                        ⚠️ No se encontraron equipos para esta edición y categoría.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach ($equipos as $equipo)
                        <a href="{{ route('lista-buena-fe.show', ['idEquipo' => $equipo->idEquipo, 'idEdicion' => $EdicionSeleccionada->id]) }}" 
                           class="block bg-white shadow-md rounded-lg overflow-hidden hover:shadow-lg transition transform hover:-translate-y-1">
                            <div class="p-4 text-center">
                                <div class="w-20 h-20 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center overflow-hidden">
                                    @if($equipo->equipo->foto)
                                        <img src="{{ asset('fotos/equipos/' . $equipo->equipo->foto) }}" alt="{{ $equipo->equipo->nombre }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-2xl">🛡️</span>
                                    @endif
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-1">{{ $equipo->equipo->nombre }}</h3>
                                <p class="text-sm text-blue-600 font-medium">{{ $equipo->categoria->nombreCategoria }}</p>
                            </div>
                            <div class="bg-gray-50 px-4 py-2 border-t text-center">
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Ver Lista de Buena Fe</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        @else
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
                <p class="text-blue-600 text-lg">
                    👆 Selecciona una edición para ver los equipos.
                </p>
            </div>
        @endif
    </div>

    {{-- Modal de Resultado de Migración --}}
    @if (session('migration_result'))
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full 
                                {{ session('migration_result')['status'] == 'success' ? 'bg-green-100' : (session('migration_result')['status'] == 'warning' ? 'bg-yellow-100' : 'bg-red-100') }} 
                                sm:mx-0 sm:h-10 sm:w-10">
                                @if(session('migration_result')['status'] == 'success')
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                @elseif(session('migration_result')['status'] == 'warning')
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                @else
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                @endif
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Resultado de Migración
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        {{ session('migration_result')['message'] }}
                                    </p>
                                    @if(isset(session('migration_result')['errors']) && count(session('migration_result')['errors']) > 0)
                                        <div class="mt-3 bg-red-50 p-2 rounded max-h-40 overflow-y-auto text-left">
                                            <p class="text-xs font-bold text-red-700 mb-1">Errores:</p>
                                            <ul class="list-disc list-inside text-xs text-red-600">
                                                @foreach(session('migration_result')['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" onclick="document.querySelector('.fixed.inset-0').remove()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        function printExternal(url) {
            let printFrame = document.getElementById('printFrame');
            if (!printFrame) {
                printFrame = document.createElement('iframe');
                printFrame.id = 'printFrame';
                printFrame.style.display = 'none';
                document.body.appendChild(printFrame);
            }

            printFrame.src = url;
        }
    </script>
@endsection
