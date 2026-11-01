{{-- Desktop match-sheet table. Identity columns are read-only text; only the match data is editable. --}}
<div class="mb-6 hidden overflow-x-auto rounded-lg border border-gray-200 sm:block" data-tabla-planilla>
    <table class="w-full min-w-[640px] bg-white text-sm">
        <colgroup>
            <col style="width: 30%;">
            <col style="width: 13%;">
            <col style="width: 13%;">
            <col style="width: 11%;">
            <col style="width: 11%;">
            <col style="width: 11%;">
            <col style="width: 11%;">
        </colgroup>
        <thead class="bg-gray-50">
            <tr class="text-xs font-semibold text-gray-700">
                <th scope="col" class="border-b border-gray-200 px-3 py-2.5 text-left">Nombre y Apellido</th>
                <th scope="col" class="border-b border-gray-200 px-3 py-2.5 text-left">F. Nac.</th>
                <th scope="col" class="border-b border-gray-200 px-3 py-2.5 text-left">DNI</th>
                <th scope="col" class="border-b border-l border-gray-200 px-2 py-2.5 text-center">N°</th>
                <th scope="col" class="border-b border-gray-200 px-2 py-2.5 text-center">Asist.</th>
                <th scope="col" class="border-b border-gray-200 px-2 py-2.5 text-center">Goles</th>
                <th scope="col" class="border-b border-gray-200 px-2 py-2.5 text-center">Roja</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jugadores as $jugador)
                @php
                    $nombreCompleto = trim($jugador->nombre.' '.$jugador->apellido);
                    $campo = 'jugadores['.$jugador->dni_jugador.']';
                @endphp
                <tr data-player-item="{{ $scope }}"
                    data-player-key="{{ $jugador->dni_jugador }}"
                    data-player-row="{{ $jugador->dni_jugador }}"
                    data-player-updated-at="{{ $jugador->updated_at?->toIso8601String() }}"
                    data-player-name="{{ $jugador->nombre }}"
                    data-player-lastname="{{ $jugador->apellido }}"
                    data-player-dni="{{ $jugador->dni_jugador }}"
                    data-player-number="{{ $jugador->numero_camiseta }}"
                    class="transition-colors hover:bg-gray-50 has-[[data-asistencia]:checked]:bg-brand-teal/10 motion-reduce:transition-none">
                    <th scope="row" class="border-b border-gray-200 px-3 py-1.5 text-left font-semibold text-gray-900">
                        <input type="hidden" name="{{ $campo }}[nombre]" value="{{ $jugador->nombre }}">
                        <input type="hidden" name="{{ $campo }}[apellido]" value="{{ $jugador->apellido }}">
                        <span class="block truncate" title="{{ $nombreCompleto }}">{{ $nombreCompleto }}</span>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-600 [&[hidden]]:hidden"
                            data-player-save-status="{{ $jugador->dni_jugador }}"
                            hidden>
                            <span data-player-save-message="{{ $jugador->dni_jugador }}"></span>
                            <button type="button" data-player-retry-save="{{ $jugador->dni_jugador }}"
                                class="hidden rounded-md bg-amber-600 px-3 py-1 text-white transition hover:bg-amber-700">
                                Reintentar
                            </button>
                            <button type="button" data-player-reload-row="{{ $jugador->dni_jugador }}"
                                class="hidden rounded-md bg-slate-700 px-3 py-1 text-white transition hover:bg-slate-800">
                                Recargar
                            </button>
                        </div>
                    </th>
                    <td class="border-b border-gray-200 px-3 py-1.5 tabular-nums text-gray-600">
                        <input type="hidden" name="{{ $campo }}[fecha_nacimiento]"
                            value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}">
                        {{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') }}
                    </td>
                    <td class="border-b border-gray-200 px-3 py-1.5 tabular-nums text-gray-600">
                        <input type="hidden" name="{{ $campo }}[dni]" value="{{ $jugador->dni_jugador }}">
                        <input type="hidden" name="{{ $campo }}[dni_actual]" value="{{ $jugador->dni_jugador }}">
                        {{ $jugador->dni_jugador }}
                    </td>
                    <td class="border-b border-l border-gray-200 px-2 py-1.5">
                        <input type="number" name="{{ $campo }}[numero_camiseta]" value="{{ $jugador->numero_camiseta }}"
                            min="0" inputmode="numeric" aria-label="Número de camiseta de {{ $nombreCompleto }}" onfocus="this.select()"
                            class="mx-auto block h-9 w-14 rounded-md border-gray-300 px-1 text-center font-semibold tabular-nums text-gray-900 [appearance:textfield] focus:border-brand-teal focus:ring-brand-teal [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    </td>
                    <td class="border-b border-gray-200 p-0">
                        <label class="flex min-h-11 cursor-pointer items-center justify-center">
                            <input type="checkbox" name="{{ $campo }}[asistencia]" data-asistencia {{ $jugador->asistio ? 'checked' : '' }}
                                aria-label="Asistió {{ $nombreCompleto }}"
                                class="h-5 w-5 cursor-pointer appearance-auto accent-brand-teal">
                        </label>
                    </td>
                    <td class="border-b border-gray-200 px-2 py-1.5">
                        <input type="number" name="{{ $campo }}[goles]" value="{{ $jugador->goles }}"
                            min="0" inputmode="numeric" aria-label="Goles de {{ $nombreCompleto }}" onfocus="this.select()"
                            class="mx-auto block h-9 w-14 rounded-md border-gray-300 px-1 text-center tabular-nums text-gray-900 [appearance:textfield] focus:border-brand-teal focus:ring-brand-teal [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    </td>
                    <td class="border-b border-gray-200 p-0 has-[:checked]:bg-red-50">
                        <label class="flex min-h-11 cursor-pointer items-center justify-center">
                            <input type="checkbox" name="{{ $campo }}[tarjeta_roja]" {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                aria-label="Tarjeta roja de {{ $nombreCompleto }}"
                                class="h-5 w-5 cursor-pointer appearance-auto accent-red-600">
                        </label>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
