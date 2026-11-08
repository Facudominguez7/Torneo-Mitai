@extends('Panel.admin')

@section('detalle')
    <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6">
        <x-ui.page-header title="Detalle de edición" class="mb-5">
            <x-slot name="actions">
                <x-ui.button href="{{ route('edicion.index', ['idEdicion' => $edicion->id]) }}" variant="secondary">
                    Volver
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>

        <div class="overflow-hidden rounded-xl bg-pa-superficie shadow-lg">
            <div class="grid gap-0 sm:grid-cols-3">
                <div class="border-b border-pa-linea-suave p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-pa-tinta-suave">ID</p>
                    <p class="mt-2 text-lg font-semibold text-pa-tinta">{{ $edicion->id }}</p>
                </div>
                <div class="border-b border-pa-linea-suave p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-pa-tinta-suave">Nombre</p>
                    <p class="mt-2 text-lg font-semibold text-pa-tinta">{{ $edicion->nombre }}</p>
                </div>
                <div class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-pa-tinta-suave">Jugadores por Planilla</p>
                    <p class="mt-2 text-lg font-semibold text-pa-tinta">{{ $edicion->jugadores_por_planilla ?? 18 }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
