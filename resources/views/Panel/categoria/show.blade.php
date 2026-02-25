@extends('Panel.admin')

@section('detalle')
    <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6">
        <div class="mb-5 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white sm:text-3xl">Detalle de Categoría</h1>
            <a href="{{ route('categoria.index', ['idEdicion' => $EdicionSeleccionada?->id]) }}"
                class="rounded-lg bg-white/90 px-4 py-2 text-sm font-semibold text-gray-800 shadow hover:bg-white">
                Volver
            </a>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-lg">
            <div class="grid gap-0 sm:grid-cols-2">
                <div class="border-b border-gray-100 p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">ID</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $categoria->id }}</p>
                </div>
                <div class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $categoria->nombreCategoria }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
