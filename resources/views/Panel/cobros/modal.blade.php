<p id="cobros-aviso" role="status" class="mt-4 text-sm font-medium text-green-800"></p>
<style>#cobros-modal::backdrop { background: rgb(0 0 0 / .5); }</style>
<dialog id="cobros-modal" aria-labelledby="cobros-titulo" style="width:calc(100% - 2rem);max-height:90vh" class="m-auto max-w-2xl overflow-y-auto rounded-lg border border-gray-300 bg-white p-5 text-gray-900"
    data-edicion="{{ $EdicionSeleccionada->id }}" data-base="{{ url('/Panel/cobros') }}">
    <div class="flex items-start justify-between gap-4">
        <h2 id="cobros-titulo" class="text-xl font-semibold" tabindex="-1"></h2>
        <button type="button" data-cobros-cerrar class="min-h-11 rounded border px-3 py-2">Cerrar</button>
    </div>
    <p id="cobros-contexto" class="mt-3 break-words text-sm"></p>
    <div id="cobros-detalle" class="mt-4" hidden></div>
    <form id="cobros-form" class="mt-4 space-y-4">
        @csrf
        <div data-campo="importe_ars" hidden>
            <label for="cobros-importe" class="block font-medium">Importe recibido (ARS enteros)</label>
            <input id="cobros-importe" name="importe_ars" type="text" inputmode="numeric" pattern="[0-9]+" class="mt-1 min-h-11 w-full rounded border-gray-300" aria-describedby="error-importe_ars">
            <p class="text-sm text-gray-600">Se aplica primero al cargo elegido; el excedente cubre otras deudas de este equipo en esta edición por orden de fecha y conserva el resto como crédito.</p>
            <p id="error-importe_ars" data-error="importe_ars" class="text-sm text-red-700"></p>
        </div>
        <div data-campo="medio" hidden>
            <label for="cobros-medio" class="block font-medium">Medio de pago</label>
            <select id="cobros-medio" name="medio" class="mt-1 min-h-11 w-full rounded border-gray-300" aria-describedby="error-medio">
                <option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="mercado_pago">Mercado Pago</option>
            </select>
            <p id="error-medio" data-error="medio" class="text-sm text-red-700"></p>
        </div>
        <div data-campo="motivo" hidden>
            <label for="cobros-motivo" class="block font-medium">Motivo (opcional, se conserva literalmente)</label>
            <textarea id="cobros-motivo" name="motivo" maxlength="2000" class="mt-1 w-full rounded border-gray-300" aria-describedby="error-motivo"></textarea>
            <p id="error-motivo" data-error="motivo" class="text-sm text-red-700"></p>
        </div>
        <div data-campo="tarifa_ars" hidden>
            <label for="cobros-tarifa" class="block font-medium">Tarifa global (ARS enteros)</label>
            <input id="cobros-tarifa" name="tarifa_ars" type="text" inputmode="numeric" pattern="[0-9]+" value="{{ $configuracion?->tarifa_ars }}" class="mt-1 min-h-11 w-full rounded border-gray-300" aria-describedby="error-tarifa_ars">
            <p id="error-tarifa_ars" data-error="tarifa_ars" class="text-sm text-red-700"></p>
        </div>
        <div data-campo="confirmar" hidden>
            <label class="flex items-start gap-3 py-3"><input name="confirmar" type="checkbox" value="1" class="mt-1 rounded"><span>Confirmo activar cobros globalmente para altas futuras. No genera cargos retroactivos ni modifica los cargos existentes. No hay desactivación disponible aquí.</span></label>
        </div>
        <p id="cobros-error" role="alert" tabindex="-1" class="break-words text-sm text-red-700"></p>
        <button id="cobros-enviar" type="submit" class="min-h-11 rounded bg-blue-900 px-4 py-2 font-medium text-white disabled:opacity-50">Confirmar</button>
    </form>
</dialog>
