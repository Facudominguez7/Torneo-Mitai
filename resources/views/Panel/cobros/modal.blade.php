<p id="cobros-aviso" role="status" tabindex="-1" data-tono="ok" class="mt-4 empty:hidden rounded-lg border px-4 py-3 text-sm font-medium data-[tono=ok]:border-emerald-200 data-[tono=ok]:bg-emerald-50 data-[tono=ok]:text-emerald-800 data-[tono=error]:border-red-200 data-[tono=error]:bg-red-50 data-[tono=error]:text-pa-peligro"></p>
<style>#cobros-modal::backdrop { background: rgb(0 0 0 / .5); }</style>
<dialog id="cobros-modal" aria-labelledby="cobros-titulo" style="width:calc(100% - 2rem);max-height:90vh" class="m-auto max-w-2xl overflow-y-auto rounded-lg border border-pa-campo-borde bg-pa-superficie p-5 text-pa-tinta"
    data-edicion="{{ $EdicionSeleccionada->id }}" data-base="{{ url('/Panel/cobros') }}">
    <div class="flex items-start justify-between gap-4">
        <h2 id="cobros-titulo" class="text-xl font-semibold" tabindex="-1"></h2>
        <button type="button" data-cobros-cerrar class="min-h-11 rounded border px-3 py-2">Cerrar</button>
    </div>
    <p id="cobros-contexto" class="mt-3 break-words text-sm empty:hidden"></p>
    <div id="cobros-detalle" class="mt-4" hidden></div>
    <form id="cobros-form" class="mt-4 space-y-4">
        @csrf
        <fieldset data-campo="importes" hidden class="space-y-3">
            <legend class="font-medium">Importe recibido (ARS enteros)</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="cobros-efectivo" class="block text-sm font-medium">Efectivo</label>
                    <input id="cobros-efectivo" name="importe_efectivo_ars" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="mt-1 min-h-11 w-full rounded border-pa-campo-borde bg-pa-campo-fondo text-pa-tinta" aria-describedby="error-importe_efectivo_ars cobros-total">
                    <p id="error-importe_efectivo_ars" data-error="importe_efectivo_ars" class="text-sm text-pa-peligro"></p>
                </div>
                <div>
                    <label for="cobros-mercado-pago" class="block text-sm font-medium">Mercado Pago</label>
                    <input id="cobros-mercado-pago" name="importe_mercado_pago_ars" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="mt-1 min-h-11 w-full rounded border-pa-campo-borde bg-pa-campo-fondo text-pa-tinta" aria-describedby="error-importe_mercado_pago_ars cobros-total">
                    <p id="error-importe_mercado_pago_ars" data-error="importe_mercado_pago_ars" class="text-sm text-pa-peligro"></p>
                </div>
            </div>
            <p id="cobros-total" class="text-sm font-semibold text-pa-tinta">Total recibido: $ 0</p>
            <p class="text-sm text-pa-tinta-cuerpo">Completá uno o ambos medios. Se aplica primero al cargo elegido; el excedente cubre otras deudas de este equipo en esta edición por orden de fecha y conserva el resto como crédito.</p>
        </fieldset>
        <div data-campo="editar_pago" hidden class="space-y-4">
            <dl id="cobros-editar-resumen" class="grid grid-cols-1 gap-3 sm:grid-cols-2"></dl>
            <p class="text-sm text-pa-tinta-cuerpo">Se corrige este mismo pago: no se anula ni se crea otro. Conserva la fecha de recepción y quién lo registró.</p>
            <div>
                <label for="cobros-editar-importe" class="block text-sm font-medium">Importe en pesos</label>
                <input id="cobros-editar-importe" name="importe_ars" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="mt-1 min-h-11 w-full rounded border-pa-campo-borde bg-pa-campo-fondo text-pa-tinta" aria-describedby="ayuda-importe_ars error-importe_ars cobros-editar-aviso">
                <p id="ayuda-importe_ars" class="mt-1 text-sm text-pa-tinta-cuerpo">Solo números, sin puntos ni decimales.</p>
                <p id="error-importe_ars" data-error="importe_ars" class="text-sm text-pa-peligro"></p>
            </div>
            <fieldset>
                <legend class="text-sm font-medium">Medio de pago</legend>
                <div class="mt-1 flex flex-wrap gap-4">
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input id="cobros-editar-efectivo" name="medio" type="radio" value="efectivo" class="border-pa-campo-borde text-pa-resaltado focus:ring-pa-acento">Efectivo</label>
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input id="cobros-editar-mercado-pago" name="medio" type="radio" value="mercado_pago" class="border-pa-campo-borde text-pa-resaltado focus:ring-pa-acento">Mercado Pago</label>
                </div>
                <p id="error-medio" data-error="medio" class="text-sm text-pa-peligro"></p>
            </fieldset>
            <div class="rounded-md border border-pa-linea bg-pa-fila-bg p-3">
                <p class="text-sm font-semibold text-pa-tinta">Al guardar</p>
                <div id="cobros-editar-aviso" role="status" aria-live="polite" class="mt-1 space-y-1 text-sm text-pa-tinta-media"></div>
            </div>
        </div>
        <div data-campo="motivo" hidden>
            <label id="cobros-motivo-etiqueta" for="cobros-motivo" class="block font-medium">Motivo (opcional, se conserva literalmente)</label>
            <textarea id="cobros-motivo" name="motivo" maxlength="2000" class="mt-1 w-full rounded border-pa-campo-borde" aria-describedby="ayuda-motivo error-motivo"></textarea>
            <p id="ayuda-motivo" class="mt-1 text-sm text-pa-tinta-cuerpo empty:hidden"></p>
            <p id="error-motivo" data-error="motivo" class="text-sm text-pa-peligro"></p>
        </div>
        <div data-campo="tarifa_ars" hidden>
            <label for="cobros-tarifa" class="block font-medium">Tarifa global (ARS enteros)</label>
            <input id="cobros-tarifa" name="tarifa_ars" type="text" inputmode="numeric" pattern="[0-9]+" value="{{ $configuracion?->tarifa_ars }}" class="mt-1 min-h-11 w-full rounded border-pa-campo-borde bg-pa-campo-fondo text-pa-tinta" aria-describedby="error-tarifa_ars">
            <p id="error-tarifa_ars" data-error="tarifa_ars" class="text-sm text-pa-peligro"></p>
        </div>
        <div data-campo="confirmar" hidden>
            <label class="flex items-start gap-3 py-3"><input name="confirmar" type="checkbox" value="1" class="mt-1 rounded"><span>Confirmo activar cobros globalmente para altas futuras. No genera cargos retroactivos ni modifica los cargos existentes. No hay desactivación disponible aquí.</span></label>
        </div>
        <p id="cobros-error" role="alert" tabindex="-1" class="break-words text-sm text-pa-peligro"></p>
        <button id="cobros-enviar" type="submit" class="min-h-11 rounded bg-blue-900 px-4 py-2 font-medium text-white disabled:opacity-50">Confirmar</button>
    </form>
</dialog>
