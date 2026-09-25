@extends('Panel.admin')

@section('contenido')
    @include('Panel.fragment._errores-formulario')
    @php
        $parametrosRegreso = $parametrosRegreso ?? ['idEdicion' => $partido->idEdicion];
    @endphp

    <div class="mb-4 px-4 md:px-0">
        <a href="{{ route('instancia_final.index', $parametrosRegreso) }}"
            class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <div class="flex items-center justify-center">
        <div class="mx-auto w-full max-w-[550px]">
            <form action="{{ route('instancia_final.update', $partido) }}" method="POST">
                @method('PUT')
                @include('Panel.instancia_final._form')
                <input type="hidden" name="retorno_idCategoria" value="{{ $parametrosRegreso['idCategoria'] ?? '' }}">
                <input type="hidden" name="retorno_idFase" value="{{ $parametrosRegreso['idFase'] ?? '' }}">
                <input type="hidden" name="retorno_idCopa" value="{{ $parametrosRegreso['idCopa'] ?? '' }}">
                <input type="hidden" name="retorno_estado" value="{{ $parametrosRegreso['estado'] ?? '' }}">
                <input type="hidden" name="retorno_busqueda" value="{{ $parametrosRegreso['busqueda'] ?? '' }}">
                <input type="hidden" name="retorno_fecha" value="{{ $parametrosRegreso['fecha'] ?? '' }}">
                <input type="hidden" name="retorno_horario" value="{{ $parametrosRegreso['horario'] ?? '' }}">
                <div>
                    <button type="submit"
                        class="panel-form-submit">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
