@extends('Panel.admin')

@section('contenido')
    @php
        $parametrosRegreso = $parametrosRegreso ?? ['idEdicion' => $partido->idEdicion];
        $puedeEditarEquipos = $puedeEditarEquipos ?? true;
    @endphp

    <x-ui.page-header title="Editar partido" />

    @include('Panel.fragment._errores-formulario')

    @if (!$puedeEditarEquipos)
        <p class="mb-4 rounded-md bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-800">
            Los equipos no pueden modificarse porque el partido ya tiene resultado, planillas o goles confirmados.
        </p>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                ['idEquipoLocal', 'idEquipoVisitante'].forEach(function (idCampo) {
                    var selector = document.getElementById(idCampo);
                    if (selector) {
                        selector.disabled = true;
                    }
                });
            });
        </script>
    @endif

    <div class="mb-4">
        <a href="{{ route('partido.index', $parametrosRegreso) }}"
            class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <form action="{{ route('partido.update', $partido->id) }}" method="POST" class="mt-6">
        @method('PATCH')
        <x-ui.form-card>
            <div>
                @include('Panel.partido._form')

                <input type="hidden" name="retorno_idCategoria" value="{{ $parametrosRegreso['idCategoria'] ?? '' }}">
                <input type="hidden" name="retorno_idGrupo" value="{{ $parametrosRegreso['idGrupo'] ?? '' }}">
                <input type="hidden" name="retorno_idFecha" value="{{ $parametrosRegreso['idFecha'] ?? '' }}">
                <input type="hidden" name="retorno_horario" value="{{ $parametrosRegreso['horario'] ?? '' }}">
                <input type="hidden" name="retorno_busqueda" value="{{ $parametrosRegreso['busqueda'] ?? '' }}">
            </div>

            <x-slot name="actions">
                <x-ui.button type="submit" variant="primary">
                    Guardar cambios
                </x-ui.button>
            </x-slot>
        </x-ui.form-card>
    </form>
@endsection
