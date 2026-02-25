@extends('Panel.admin')

@section('detalle')
    <div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6">
        <div class="mb-5 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-white sm:text-3xl">Detalle de Equipo</h1>
            <a href="{{ route('equipo.index', ['idEdicion' => $EdicionSeleccionada?->id]) }}"
                class="rounded-lg bg-white/90 px-4 py-2 text-sm font-semibold text-gray-800 shadow hover:bg-white">
                Volver
            </a>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-lg">
            <div class="border-b border-gray-100 p-5">
                <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:text-left">
                    <img
                        class="h-20 w-20 rounded-full border border-gray-200 object-cover"
                        src="{{ asset('fotos/equipos/' . $equipo->foto) }}"
                        alt="Escudo {{ $equipo->nombre }}"
                    >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Equipo</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $equipo->nombre }}</p>
                        <p class="text-sm text-gray-500">{{ $equipo->categoria->nombreCategoria }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-0 sm:grid-cols-3">
                <div class="border-b border-gray-100 p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">ID</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $equipo->id }}</p>
                </div>
                <div class="border-b border-gray-100 p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $equipo->nombre }}</p>
                </div>
                <div class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Categoría</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $equipo->categoria->nombreCategoria }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
