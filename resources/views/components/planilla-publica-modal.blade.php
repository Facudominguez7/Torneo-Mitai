<template x-teleport="body">
<div x-show="planillaAbierta" x-on:keydown.escape.window="cerrarPlanilla()" class="planilla-publica" style="display: none;">
    <div class="planilla-publica__velo" x-on:click="cerrarPlanilla()"></div>
    <div class="planilla-publica__posicion" x-on:click.self="cerrarPlanilla()">
        <div class="planilla-publica__hoja" role="dialog" aria-modal="true" aria-labelledby="planilla-publica-titulo" x-on:keydown.tab="atraparFocoPlanilla($event)">
            <header class="planilla-publica__cabecera">
                <div>
                    <p class="planilla-publica__rotulo">Planilla pública</p>
                    <h2 id="planilla-publica-titulo" x-text="planillaPayload?.data?.partido?.categoria || 'Ver planilla'"></h2>
                    <template x-if="planillaPayload?.data?.partido">
                        <p><span x-text="planillaPayload.data.partido.equipo_local || 'Equipo local'"></span> vs <span x-text="planillaPayload.data.partido.equipo_visitante || 'Equipo visitante'"></span></p>
                    </template>
                </div>
                <button type="button" x-ref="cerrarPlanilla" x-on:click="cerrarPlanilla()" aria-label="Cerrar planilla pública">✕</button>
            </header>
            <div class="planilla-publica__contenido">
                <template x-if="planillaCargando">
                    <div class="planilla-publica__estado" role="status"><h3>Cargando planilla...</h3><p>Estamos buscando la información más reciente del partido.</p></div>
                </template>
                <template x-if="!planillaCargando && planillaError">
                    <div class="planilla-publica__estado" role="alert"><h3>No pudimos cargar la planilla.</h3><p x-text="planillaError"></p></div>
                </template>
                <template x-if="!planillaCargando && !planillaError && planillaPayload">
                    <div>
                        <div class="planilla-publica__meta">
                            <strong x-text="planillaPayload.data.goles_confirmados ? 'Goles confirmados' : 'Goles pendientes de carga'"></strong>
                            <template x-if="planillaPayload.data.goles_confirmados_at"><span>Confirmados el <span x-text="planillaPayload.data.goles_confirmados_at"></span></span></template>
                            <span>Última actualización: <span x-text="planillaPayload.data.ultima_actualizacion || 'Sin registros todavía'"></span></span>
                            <span class="planilla-publica__rotulo">Solo lectura</span>
                        </div>
                        <template x-if="planillaPayload.data.goles_arbitros?.hay_inconsistencias">
                            <div class="planilla-publica__aviso"><strong>Inconsistencia en el reporte arbitral</strong><p>Una inconsistencia no significa que el jugador haya faltado al partido: el número de camiseta que anotaron los árbitros no se pudo vincular con la planilla (por ejemplo, se cargó mal el número o falta marcar la asistencia). Es responsabilidad del profesor o delegado corroborar los goles anotados por los árbitros una vez terminado el partido.</p></div>
                        </template>
                        <template x-if="planillaPayload.meta?.sin_planilla">
                            <div class="planilla-publica__estado"><h3>La planilla aún no fue cargada.</h3><p>Volvé más tarde para consultar los datos del partido.</p></div>
                        </template>
                        <div class="planilla-publica__equipos">
                            <template x-for="equipo in [{ clave: 'local', nombre: planillaPayload.data.partido.equipo_local || 'Equipo local', etiqueta: 'Local' }, { clave: 'visitante', nombre: planillaPayload.data.partido.equipo_visitante || 'Equipo visitante', etiqueta: 'Visitante' }]" :key="equipo.clave">
                                <section>
                                    <div class="planilla-publica__equipo"><div><p class="planilla-publica__rotulo" x-text="equipo.etiqueta"></p><h3 x-text="equipo.nombre"></h3></div><span x-text="`${planillaPayload.data[equipo.clave].length} presentes`"></span></div>
                                    <p class="planilla-publica__subtitulo">Dato oficial · jugadores presentes</p>
                                    <table><thead><tr><th scope="col">Nº</th><th scope="col">Jugador</th><th scope="col">Goles</th></tr></thead><tbody>
                                        <template x-for="(jugador, index) in planillaPayload.data[equipo.clave]" :key="index"><tr><td x-text="jugador.numero_camiseta ?? '-'"></td><td x-text="`${jugador.apellido}, ${jugador.nombre}`"></td><td x-text="jugador.goles"></td></tr></template>
                                    </tbody></table>
                                    <template x-if="planillaPayload.data[equipo.clave].length === 0"><p class="planilla-publica__vacio">Todavía no hay jugadores presentes para este equipo.</p></template>
                                    <p class="planilla-publica__subtitulo">Reportado por árbitros</p>
                                    <template x-if="!(planillaPayload.data.goles_arbitros?.[equipo.clave]?.length)"><p class="planilla-publica__vacio">Todavía no hay datos arbitrales para este equipo.</p></template>
                                    <template x-for="(reporte, index) in (planillaPayload.data.goles_arbitros?.[equipo.clave] || [])" :key="index">
                                        <article class="planilla-publica__reporte">
                                            <strong x-text="`Camiseta ${reporte.numero_camiseta ?? '-'} · ${reporte.goles_reportados} gol(es)`"></strong>
                                            <span x-show="reporte.es_inconsistencia"> · Inconsistencia</span>
                                            <template x-if="reporte.jugador_oficial"><p>Vinculado a <span x-text="reporte.jugador_oficial"></span></p></template>
                                            <template x-if="reporte.es_inconsistencia"><p x-text="reporte.motivo_inconsistencia"></p></template>
                                        </article>
                                    </template>
                                </section>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
</template>

@once
    <script>
        function crearPlanillaPublica() {
            return {
                planillaAbierta: false,
                planillaCargando: false,
                planillaError: null,
                planillaPayload: null,
                planillasCacheadas: {},
                planillaOrigen: null,
                async abrirPlanilla(url) {
                    this.planillaOrigen = document.activeElement;
                    this.planillaAbierta = true;
                    this.planillaError = null;
                    document.body.classList.add('overflow-hidden');
                    this.$nextTick(() => this.$refs.cerrarPlanilla?.focus());

                    if (this.planillasCacheadas[url]) {
                        this.planillaPayload = this.planillasCacheadas[url];
                        this.planillaCargando = false;
                        return;
                    }

                    this.planillaPayload = null;
                    this.planillaCargando = true;
                    try {
                        const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const body = await response.text();
                        const payload = body ? JSON.parse(body) : {};
                        if (!response.ok) throw new Error(payload.message || 'No se pudo consultar la planilla.');
                        this.planillasCacheadas[url] = payload;
                        this.planillaPayload = payload;
                    } catch (error) {
                        this.planillaError = error.message || 'No se pudo consultar la planilla.';
                    } finally {
                        this.planillaCargando = false;
                    }
                },
                atraparFocoPlanilla(event) {
                    const elementos = [...event.currentTarget.querySelectorAll('a[href], button:not([disabled]), select:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter(el => el.getClientRects().length);
                    if (!elementos.length) return;
                    const primero = elementos[0], ultimo = elementos[elementos.length - 1];
                    if (event.shiftKey && document.activeElement === primero) { event.preventDefault(); ultimo.focus(); }
                    else if (!event.shiftKey && document.activeElement === ultimo) { event.preventDefault(); primero.focus(); }
                },
                cerrarPlanilla() {
                    if (!this.planillaAbierta) return;
                    this.planillaAbierta = false;
                    document.body.classList.remove('overflow-hidden');
                    this.planillaOrigen?.focus();
                },
            };
        }
    </script>
@endonce
