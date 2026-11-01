{{-- Match-sheet table in lista de buena fe order. Attendance and shirt number come first because they are the operator's main task. --}}
<div class="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white" data-tabla-planilla>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 max-sm:hidden">
            <tr class="text-xs font-semibold text-gray-700">
                <th scope="col" class="w-24 border-b border-gray-200 px-2 py-2.5 text-center">Asistencia</th>
                <th scope="col" class="w-24 border-b border-gray-200 px-2 py-2.5 text-center">Número</th>
                <th scope="col" class="border-b border-gray-200 px-3 py-2.5 text-left">Jugador</th>
                <th scope="col" class="w-24 border-b border-gray-200 px-2 py-2.5 text-center">Goles</th>
                <th scope="col" class="w-20 border-b border-gray-200 px-2 py-2.5 text-center">Roja</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jugadores as $jugador)
                @php
                    $nombreCompleto = trim($jugador->nombre.' '.$jugador->apellido);
                    $campo = 'jugadores['.$jugador->dni_jugador.']';
                    $dniVisible = ctype_digit((string) $jugador->dni_jugador)
                        ? number_format((int) $jugador->dni_jugador, 0, ',', '.')
                        : $jugador->dni_jugador;
                @endphp
                <tr data-player-item="{{ $scope }}"
                    data-player-key="{{ $jugador->dni_jugador }}"
                    data-player-row="{{ $jugador->dni_jugador }}"
                    data-player-updated-at="{{ $jugador->updated_at?->toIso8601String() }}"
                    data-player-name="{{ $jugador->nombre }}"
                    data-player-lastname="{{ $jugador->apellido }}"
                    data-player-dni="{{ $jugador->dni_jugador }}"
                    data-player-number="{{ $jugador->numero_camiseta }}"
                    class="border-b border-gray-100 transition-[background-color,opacity] duration-200 last:border-b-0 has-[[data-asistencia]:checked]:bg-brand-teal/10 motion-reduce:transition-none max-sm:grid max-sm:grid-cols-[2.75rem_4.25rem_minmax(0,1fr)_auto] max-sm:items-center max-sm:gap-x-2 max-sm:px-2 max-sm:py-2 [&[data-save-error]]:!bg-red-50 [&[data-search-oculto]]:!hidden [&[data-search-match]]:!bg-brand-gold/40">
                    <td class="px-2 py-1.5 text-center max-sm:row-span-2 max-sm:p-0">
                        <label class="flex min-h-[3rem] cursor-pointer items-center justify-center">
                            <span class="sr-only">Asistencia de {{ $nombreCompleto }}</span>
                            <input type="checkbox" name="{{ $campo }}[asistencia]" data-asistencia {{ $jugador->asistio ? 'checked' : '' }}
                                class="h-6 w-6 cursor-pointer appearance-auto accent-brand-teal">
                        </label>
                    </td>
                    <td class="px-2 py-1.5 max-sm:row-span-2 max-sm:p-0">
                        <input type="number" name="{{ $campo }}[numero_camiseta]" value="{{ $jugador->numero_camiseta }}"
                            min="0" inputmode="numeric" aria-label="Número de camiseta de {{ $nombreCompleto }}" onfocus="this.select()"
                            class="mx-auto block h-11 w-16 rounded-md border-gray-300 px-1 text-center text-lg font-bold tabular-nums text-gray-900 [appearance:textfield] focus:border-brand-teal focus:ring-brand-teal data-[numero-repetido]:border-amber-500 data-[numero-repetido]:bg-amber-50 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    </td>
                    <th scope="row" class="min-w-0 px-3 py-1.5 text-left font-normal max-sm:col-span-2 max-sm:p-0">
                        <input type="hidden" name="{{ $campo }}[nombre]" value="{{ $jugador->nombre }}">
                        <input type="hidden" name="{{ $campo }}[apellido]" value="{{ $jugador->apellido }}">
                        <input type="hidden" name="{{ $campo }}[fecha_nacimiento]"
                            value="{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('Y-m-d') }}">
                        <input type="hidden" name="{{ $campo }}[dni]" value="{{ $jugador->dni_jugador }}">
                        <input type="hidden" name="{{ $campo }}[dni_actual]" value="{{ $jugador->dni_jugador }}">
                        <span class="block truncate font-semibold text-gray-900" title="{{ $nombreCompleto }}">{{ $nombreCompleto }}</span>
                        <span class="text-xs tabular-nums text-gray-500">DNI {{ $dniVisible }} · Nac.&nbsp;{{ \Carbon\Carbon::parse($jugador->fecha_nacimiento)->format('d/m/Y') }}</span>
                        <span class="text-xs font-semibold text-amber-700" data-player-repeated-note></span>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500 data-[player-save-state=conflict]:text-red-700 data-[player-save-state=error]:text-red-700 data-[player-save-state=ok]:text-brand-teal [&[hidden]]:hidden"
                            data-player-save-status="{{ $jugador->dni_jugador }}"
                            hidden>
                            <span data-player-save-message="{{ $jugador->dni_jugador }}"></span>
                            <button type="button" data-player-retry-save="{{ $jugador->dni_jugador }}"
                                hidden class="rounded-md bg-red-700 px-3 py-1 text-white transition hover:bg-red-800 [&[hidden]]:hidden">
                                Reintentar
                            </button>
                            <button type="button" data-player-reload-row="{{ $jugador->dni_jugador }}"
                                hidden class="rounded-md bg-brand-navy px-3 py-1 text-white transition hover:bg-brand-navy-deep [&[hidden]]:hidden">
                                Recargar
                            </button>
                        </div>
                    </th>
                    <td class="px-2 py-1.5 max-sm:flex max-sm:items-center max-sm:gap-2 max-sm:p-0 max-sm:pt-1">
                        <span class="text-xs font-semibold text-gray-500 sm:hidden" aria-hidden="true">Goles</span>
                        <input type="number" name="{{ $campo }}[goles]" value="{{ $jugador->goles }}"
                            min="0" inputmode="numeric" aria-label="Goles de {{ $nombreCompleto }}" onfocus="this.select()"
                            class="mx-auto block h-11 w-16 rounded-md border-gray-300 px-1 text-center tabular-nums text-gray-900 [appearance:textfield] focus:border-brand-teal focus:ring-brand-teal max-sm:mx-0 max-sm:h-10 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    </td>
                    <td class="px-2 py-1.5 has-[:checked]:bg-red-50 max-sm:p-0 max-sm:pt-1">
                        <label class="flex min-h-[3rem] cursor-pointer items-center justify-center gap-2 max-sm:min-h-10 max-sm:justify-end">
                            <span class="text-xs font-semibold text-gray-500 sm:hidden" aria-hidden="true">Roja</span>
                            <input type="checkbox" name="{{ $campo }}[tarjeta_roja]" {{ $jugador->tarjeta_roja ? 'checked' : '' }}
                                aria-label="Tarjeta roja de {{ $nombreCompleto }}"
                                class="h-6 w-6 cursor-pointer appearance-auto accent-red-600">
                        </label>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
