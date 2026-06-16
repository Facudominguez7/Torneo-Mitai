<div
    x-show="planillaAbierta"
    x-on:keydown.escape.window="cerrarPlanilla()"
    class="fixed inset-0 z-50"
    style="display: none;"
>
    <div
        x-show="planillaAbierta"
        x-transition.opacity
        class="absolute inset-0 bg-black/80 backdrop-blur-sm"
        x-on:click="cerrarPlanilla()"
    ></div>

    <div class="relative flex min-h-screen items-end justify-center px-4 py-6 sm:items-center sm:px-6">
        <div
            x-show="planillaAbierta"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative w-full max-w-4xl overflow-hidden rounded-3xl border border-[#f7f280]/25 bg-[#071d24]/95 text-white shadow-[0_30px_90px_-40px_rgba(0,0,0,0.9)]"
            x-on:click.stop
            role="dialog"
            aria-modal="true"
            aria-labelledby="planilla-publica-titulo"
        >
            <div class="border-b border-white/10 bg-white/5 px-5 py-4 sm:px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#f7f280]/80">Planilla publica</p>
                        <h2 id="planilla-publica-titulo" class="text-xl font-bold text-white" x-text="planillaPayload?.data?.partido?.categoria || 'Ver planilla'"></h2>
                        <template x-if="planillaPayload?.data?.partido">
                            <p class="text-sm text-white/65">
                                <span x-text="planillaPayload.data.partido.equipo_local || 'Equipo local'"></span>
                                <span class="mx-1">vs</span>
                                <span x-text="planillaPayload.data.partido.equipo_visitante || 'Equipo visitante'"></span>
                            </p>
                        </template>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-white/5 text-white/75 transition hover:bg-white/10 hover:text-white"
                        x-on:click="cerrarPlanilla()"
                        aria-label="Cerrar planilla publica"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="max-h-[80vh] overflow-y-auto px-5 py-5 sm:px-6 sm:py-6">
                <template x-if="planillaCargando">
                    <div class="flex min-h-56 flex-col items-center justify-center gap-4 text-center text-white/70">
                        <span class="inline-flex h-12 w-12 animate-spin rounded-full border-2 border-[#f7f280]/25 border-t-[#f7f280]"></span>
                        <div>
                            <p class="text-base font-semibold text-white">Cargando planilla...</p>
                            <p class="text-sm text-white/55">Estamos buscando la informacion mas reciente del partido.</p>
                        </div>
                    </div>
                </template>

                <template x-if="!planillaCargando && planillaError">
                    <div class="rounded-3xl border border-red-400/30 bg-red-500/10 px-5 py-8 text-center text-sm text-red-100">
                        <p class="text-base font-semibold">No pudimos cargar la planilla.</p>
                        <p class="mt-2 text-red-100/80" x-text="planillaError"></p>
                    </div>
                </template>

                <template x-if="!planillaCargando && !planillaError && planillaPayload">
                    <div class="space-y-5">
                        <div class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-4 text-sm text-white/75 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-col gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="planillaPayload.data.goles_confirmados ? 'border border-emerald-300/35 bg-emerald-400/10 text-emerald-200' : 'border border-amber-300/35 bg-amber-400/10 text-amber-100'"
                                        x-text="planillaPayload.data.goles_confirmados ? 'Goles confirmados' : 'Goles pendientes de carga'"
                                    ></span>
                                    <template x-if="planillaPayload.data.goles_confirmados_at">
                                        <span class="text-xs text-white/55">
                                            Confirmados el <span x-text="planillaPayload.data.goles_confirmados_at"></span>
                                        </span>
                                    </template>
                                </div>
                                <p class="font-semibold text-white/85">
                                Ultima actualizacion:
                                <span class="text-[#f7f280]" x-text="planillaPayload.data.ultima_actualizacion || 'Sin registros todavia'"></span>
                                </p>
                            </div>
                            <p class="text-xs uppercase tracking-[0.25em] text-white/45">Solo lectura</p>
                        </div>

                        <template x-if="planillaPayload.data.goles_arbitros?.hay_inconsistencias">
                            <div class="rounded-2xl border border-red-400/25 bg-red-500/10 px-4 py-3 text-sm text-red-100">
                                <p class="font-semibold">⚠️ Hay inconsistencias entre los datos arbitrales y la planilla.</p>
                                <p class="mt-1">Una inconsistencia <span class="font-semibold">no significa</span> que el jugador haya faltado al partido: el número de camiseta que anotaron los árbitros no se pudo vincular con la planilla (por ejemplo, se cargó mal el número o falta marcar la asistencia). Es responsabilidad del profesor o delegado corroborar los goles anotados por los árbitros una vez terminado el partido.</p>
                            </div>
                        </template>

                        <template x-if="planillaPayload.meta?.sin_planilla">
                            <div class="rounded-3xl border border-white/10 bg-white/5 px-5 py-10 text-center">
                                <p class="text-lg font-semibold text-white">La planilla aun no fue cargada.</p>
                                <p class="mt-2 text-sm text-white/60">Volvé mas tarde para ver nombre, apellido, goles y asistencia de este partido.</p>
                            </div>
                        </template>

                        <template x-if="!planillaPayload.meta?.sin_planilla">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <section class="rounded-3xl border border-white/10 bg-white/5 p-4 sm:p-5">
                                    <div class="mb-4 flex items-center justify-between gap-3 border-b border-white/10 pb-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/45">Local</p>
                                            <h3 class="text-lg font-bold text-white" x-text="planillaPayload.data.partido.equipo_local || 'Equipo local'"></h3>
                                        </div>
                                        <span class="inline-flex items-center rounded-full border border-emerald-300/35 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200" x-text="`${planillaPayload.data.local.length} jugadores`"></span>
                                    </div>

                                    <div class="space-y-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-white/45">Dato oficial</p>

                                        <template x-if="planillaPayload.data.local.length === 0">
                                            <p class="rounded-2xl border border-dashed border-white/10 px-4 py-5 text-sm text-white/55">Todavia no hay jugadores cargados para el local.</p>
                                        </template>

                                        <template x-for="(jugador, index) in planillaPayload.data.local" :key="`local-${jugador.apellido}-${jugador.nombre}-${index}`">
                                            <article class="rounded-2xl border border-white/10 bg-black/15 px-4 py-3">
                                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p class="font-semibold text-white" x-text="`${jugador.apellido}, ${jugador.nombre}`"></p>
                                                        <p class="text-xs text-white/60">
                                                            Camiseta:
                                                            <span class="font-semibold text-white/85" x-text="jugador.numero_camiseta ?? '-'"></span>
                                                        </p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                                        <span class="inline-flex items-center rounded-full border border-[#f7f280]/30 bg-[#f7f280]/10 px-3 py-1 text-[#f7f280]">
                                                            Goles: <span class="ml-1" x-text="jugador.goles"></span>
                                                        </span>
                                                        <span
                                                            class="inline-flex items-center rounded-full px-3 py-1"
                                                            :class="jugador.asistencia ? 'border border-emerald-300/35 bg-emerald-400/10 text-emerald-200' : 'border border-white/15 bg-white/5 text-white/60'"
                                                            x-text="jugador.asistencia ? 'Asistencia: presente' : 'Asistencia: ausente'"
                                                        ></span>
                                                    </div>
                                                </div>
                                            </article>
                                        </template>
                                    </div>

                                    <div class="mt-4 border-t border-white/10 pt-4">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#f7f280]/75">Reportado por arbitros</p>
                                            <span class="inline-flex items-center rounded-full border border-[#f7f280]/30 bg-[#f7f280]/10 px-3 py-1 text-xs font-semibold text-[#f7f280]" x-text="`${planillaPayload.data.goles_arbitros?.local?.length || 0} registros`"></span>
                                        </div>

                                        <div class="space-y-2">
                                            <template x-if="(planillaPayload.data.goles_arbitros?.local?.length || 0) === 0">
                                                <p class="rounded-2xl border border-dashed border-white/10 px-4 py-4 text-sm text-white/55">Todavia no hay datos arbitrales para el local.</p>
                                            </template>

                                            <template x-for="(reporte, index) in (planillaPayload.data.goles_arbitros?.local || [])" :key="`arbitro-local-${index}`">
                                                <article class="rounded-2xl border px-4 py-3" :class="reporte.es_inconsistencia ? 'border-red-400/30 bg-red-500/10' : 'border-[#f7f280]/20 bg-[#f7f280]/5'">
                                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <div>
                                                            <p class="font-semibold text-white" x-text="`Camiseta ${reporte.numero_camiseta ?? '-'} · ${reporte.goles_reportados} gol(es)`"></p>
                                                            <template x-if="reporte.jugador_oficial">
                                                                <p class="text-xs text-white/60">Vinculado a <span x-text="reporte.jugador_oficial"></span></p>
                                                            </template>
                                                        </div>
                                                        <span class="text-xs font-semibold" :class="reporte.es_inconsistencia ? 'text-red-200' : 'text-emerald-200'" x-text="reporte.es_inconsistencia ? 'Inconsistencia' : 'Dato arbitral visible' "></span>
                                                    </div>

                                                    <template x-if="reporte.es_inconsistencia">
                                                        <p class="mt-2 text-xs text-red-100/90" x-text="reporte.motivo_inconsistencia"></p>
                                                    </template>
                                                </article>
                                            </template>
                                        </div>
                                    </div>
                                </section>

                                <section class="rounded-3xl border border-white/10 bg-white/5 p-4 sm:p-5">
                                    <div class="mb-4 flex items-center justify-between gap-3 border-b border-white/10 pb-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/45">Visitante</p>
                                            <h3 class="text-lg font-bold text-white" x-text="planillaPayload.data.partido.equipo_visitante || 'Equipo visitante'"></h3>
                                        </div>
                                        <span class="inline-flex items-center rounded-full border border-sky-300/35 bg-sky-400/10 px-3 py-1 text-xs font-semibold text-sky-200" x-text="`${planillaPayload.data.visitante.length} jugadores`"></span>
                                    </div>

                                    <div class="space-y-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-white/45">Dato oficial</p>

                                        <template x-if="planillaPayload.data.visitante.length === 0">
                                            <p class="rounded-2xl border border-dashed border-white/10 px-4 py-5 text-sm text-white/55">Todavia no hay jugadores cargados para el visitante.</p>
                                        </template>

                                        <template x-for="(jugador, index) in planillaPayload.data.visitante" :key="`visitante-${jugador.apellido}-${jugador.nombre}-${index}`">
                                            <article class="rounded-2xl border border-white/10 bg-black/15 px-4 py-3">
                                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p class="font-semibold text-white" x-text="`${jugador.apellido}, ${jugador.nombre}`"></p>
                                                        <p class="text-xs text-white/60">
                                                            Camiseta:
                                                            <span class="font-semibold text-white/85" x-text="jugador.numero_camiseta ?? '-'"></span>
                                                        </p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                                        <span class="inline-flex items-center rounded-full border border-[#f7f280]/30 bg-[#f7f280]/10 px-3 py-1 text-[#f7f280]">
                                                            Goles: <span class="ml-1" x-text="jugador.goles"></span>
                                                        </span>
                                                        <span
                                                            class="inline-flex items-center rounded-full px-3 py-1"
                                                            :class="jugador.asistencia ? 'border border-emerald-300/35 bg-emerald-400/10 text-emerald-200' : 'border border-white/15 bg-white/5 text-white/60'"
                                                            x-text="jugador.asistencia ? 'Asistencia: presente' : 'Asistencia: ausente'"
                                                        ></span>
                                                    </div>
                                                </div>
                                            </article>
                                        </template>
                                    </div>

                                    <div class="mt-4 border-t border-white/10 pt-4">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#f7f280]/75">Reportado por arbitros</p>
                                            <span class="inline-flex items-center rounded-full border border-[#f7f280]/30 bg-[#f7f280]/10 px-3 py-1 text-xs font-semibold text-[#f7f280]" x-text="`${planillaPayload.data.goles_arbitros?.visitante?.length || 0} registros`"></span>
                                        </div>

                                        <div class="space-y-2">
                                            <template x-if="(planillaPayload.data.goles_arbitros?.visitante?.length || 0) === 0">
                                                <p class="rounded-2xl border border-dashed border-white/10 px-4 py-4 text-sm text-white/55">Todavia no hay datos arbitrales para el visitante.</p>
                                            </template>

                                            <template x-for="(reporte, index) in (planillaPayload.data.goles_arbitros?.visitante || [])" :key="`arbitro-visitante-${index}`">
                                                <article class="rounded-2xl border px-4 py-3" :class="reporte.es_inconsistencia ? 'border-red-400/30 bg-red-500/10' : 'border-[#f7f280]/20 bg-[#f7f280]/5'">
                                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <div>
                                                            <p class="font-semibold text-white" x-text="`Camiseta ${reporte.numero_camiseta ?? '-'} · ${reporte.goles_reportados} gol(es)`"></p>
                                                            <template x-if="reporte.jugador_oficial">
                                                                <p class="text-xs text-white/60">Vinculado a <span x-text="reporte.jugador_oficial"></span></p>
                                                            </template>
                                                        </div>
                                                        <span class="text-xs font-semibold" :class="reporte.es_inconsistencia ? 'text-red-200' : 'text-emerald-200'" x-text="reporte.es_inconsistencia ? 'Inconsistencia' : 'Dato arbitral visible' "></span>
                                                    </div>

                                                    <template x-if="reporte.es_inconsistencia">
                                                        <p class="mt-2 text-xs text-red-100/90" x-text="reporte.motivo_inconsistencia"></p>
                                                    </template>
                                                </article>
                                            </template>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

@once
    <script>
        function crearPlanillaPublica() {
            return {
                planillaAbierta: false,
                planillaCargando: false,
                planillaError: null,
                planillaPayload: null,
                planillasCacheadas: {},
                async abrirPlanilla(url) {
                    this.planillaAbierta = true;
                    this.planillaError = null;
                    document.body.classList.add('overflow-hidden');

                    if (this.planillasCacheadas[url]) {
                        this.planillaPayload = this.planillasCacheadas[url];
                        this.planillaCargando = false;
                        return;
                    }

                    this.planillaPayload = null;
                    this.planillaCargando = true;

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        const body = await response.text();
                        const payload = body ? JSON.parse(body) : {};

                        if (!response.ok) {
                            throw new Error(payload.message || 'No se pudo consultar la planilla.');
                        }

                        this.planillasCacheadas[url] = payload;
                        this.planillaPayload = payload;
                    } catch (error) {
                        this.planillaError = error.message || 'No se pudo consultar la planilla.';
                    } finally {
                        this.planillaCargando = false;
                    }
                },
                cerrarPlanilla() {
                    this.planillaAbierta = false;
                    document.body.classList.remove('overflow-hidden');
                },
            };
        }
    </script>
@endonce
