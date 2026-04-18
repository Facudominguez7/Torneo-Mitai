@extends('Panel.admin')

@section('contenido')
    <div class="mx-auto max-w-3xl px-4 py-6">
        <div class="mb-4 text-center">
            <h1 class="text-2xl font-bold text-gray-900">Editar Posición</h1>
            <p class="text-sm text-gray-600">{{ $EdicionSeleccionada?->nombre ?? 'Sin edición seleccionada' }}</p>
        </div>

        @include('Panel.tabla_posicion._form', [
            'accion' => route('tabla_posicion.update', $tablaPosicion),
            'metodo' => 'PUT',
            'tablaPosicion' => $tablaPosicion,
            'parametrosRegreso' => $parametrosRegreso,
        ])
    </div>
@endsection
