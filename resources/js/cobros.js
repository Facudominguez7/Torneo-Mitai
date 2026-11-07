(() => {
    const modal = document.querySelector('#cobros-modal');
    if (!modal) {
        const seccion = document.querySelector('[data-cobros-seccion]');
        if (!seccion) return;
        let consultando = false;
        const refrescar = async () => {
            if (consultando || document.visibilityState !== 'visible') return;
            consultando = true;
            try {
                const respuesta = await fetch(seccion.dataset.url, { headers: { Accept: 'text/html' }, cache: 'no-store' });
                if (!respuesta.ok || respuesta.redirected) return;
                const plantilla = document.createElement('template');
                plantilla.innerHTML = (await respuesta.text()).trim();
                const grilla = plantilla.content.querySelector('[data-cobros-grilla]');
                if (!grilla || grilla.dataset.firma === seccion.querySelector('[data-cobros-grilla]')?.dataset.firma) return;
                const selecciones = [...seccion.querySelectorAll('[data-equipo-franja]')].map((fila) => [fila.dataset.equipoFranja, fila.querySelector('[data-deuda-selector]')?.value]);
                seccion.replaceChildren(plantilla.content);
                requestAnimationFrame(() => selecciones.forEach(([equipo, valor]) => {
                    const selector = seccion.querySelector(`[data-equipo-franja="${equipo}"] [data-deuda-selector]`);
                    if (selector && [...selector.options].some((opcion) => opcion.value === valor)) {
                        selector.value = valor;
                        selector.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }));
            } catch {
                // Se reintenta en el próximo intervalo.
            } finally {
                consultando = false;
            }
        };
        setInterval(refrescar, 1000);
        document.addEventListener('visibilitychange', refrescar);
        return;
    }
    const form = document.querySelector('#cobros-form');
    const titulo = document.querySelector('#cobros-titulo');
    const contexto = document.querySelector('#cobros-contexto');
    const detalle = document.querySelector('#cobros-detalle');
    const error = document.querySelector('#cobros-error');
    const enviar = document.querySelector('#cobros-enviar');
    const aviso = document.querySelector('#cobros-aviso');
    const ponerAviso = (texto, tono = 'ok') => {
        aviso.textContent = texto;
        aviso.dataset.tono = tono;
    };
    const total = document.querySelector('#cobros-total');
    const resumenEdicion = document.querySelector('#cobros-editar-resumen');
    const avisoEdicion = document.querySelector('#cobros-editar-aviso');
    const etiquetaMotivo = document.querySelector('#cobros-motivo-etiqueta');
    const ayudaMotivo = document.querySelector('#ayuda-motivo');
    const seccion = document.querySelector('[data-cobros-seccion]');
    const importes = ['importe_efectivo_ars', 'importe_mercado_pago_ars'];
    const medios = { efectivo: 'Efectivo', mercado_pago: 'Mercado Pago', transferencia: 'Transferencia' };
    const configuraciones = {
        pago: ['Registrar pago', 'pagos', ['importes']],
        exencion: ['Eximir deuda residual', 'exenciones', ['motivo']],
        exencion_edicion: ['Exento de planilla en la edición', 'exenciones-edicion', ['motivo']],
        politica: ['Exención para cargos futuros', 'politicas', ['motivo']],
        no_vino: ['El equipo no vino', 'no-vino', ['motivo']],
        deshacer_no_vino: ['Deshacer no vino', 'no-vino-deshacer', []],
        tarifa: ['Guardar tarifa global', 'tarifa', ['tarifa_ars']],
        activar: ['Activar cobros globalmente', 'activar', ['confirmar']],
        editar_pago: ['Editar pago', 'pagos-editar', ['editar_pago', 'motivo']],
        detalle: ['Detalle e historial del equipo', 'detalle', []],
    };
    let operacion;
    let cargo;
    let clave;
    let pendiente = false;
    let origen;
    let controlador;
    let desbordamiento;
    let refrescando = false;
    let ultimaConsulta = 0;
    const leer = (nombre) => {
        try { return JSON.parse(sessionStorage.getItem(nombre)); } catch { return null; }
    };
    let sinConfirmar = leer('cobros-pago-sin-confirmar');
    const uuid = () => {
        if (crypto.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };
    const ars = (importe) => `$ ${Number(importe).toLocaleString('es-AR')} ARS`;
    const sumar = (pagos, campo) => pagos.reduce((suma, pago) => suma + pago[campo], 0);
    const medio = (nombre) => medios[nombre] ?? nombre;
    const pesos = (importe) => `$ ${Number(importe).toLocaleString('es-AR')}`;
    const fraseMedio = (nombre) => ({ efectivo: 'en efectivo', mercado_pago: 'por Mercado Pago' })[nombre] ?? `por ${medio(nombre)}`;
    const TEXTO_EDITAR = 'Guardar cambios';
    const TEXTO_CONFIRMAR = 'Confirmar';
    const MOTIVO = 'Motivo (opcional, se conserva literalmente)';
    const MOTIVO_EDICION = 'Motivo (opcional)';
    const AYUDA_MOTIVO_EDICION = 'Se guarda en el historial tal como lo escribas.';
    // El comprobante de edición combina el evento (antes) con el estado vigente releído (después), nunca el snapshot.
    const reciboEdicion = (vigente, evento) => {
        const deuda = Number(vigente.deuda_cargo_ars) > 0
            ? `La planilla debe ${pesos(vigente.deuda_cargo_ars)}.`
            : 'La planilla queda paga.';
        const credito = Number(vigente.credito_equipo_ars) > 0
            ? ` Crédito disponible del equipo en esta edición: ${pesos(vigente.credito_equipo_ars)}.`
            : '';
        return `Pago #${vigente.id} corregido: ${pesos(evento.importe_antes)} ${fraseMedio(evento.medio_antes)} → ${pesos(vigente.importe_ars)} ${fraseMedio(vigente.medio)}. ${deuda}${credito}`;
    };
    const recibo = (pagos) => {
        const ultimo = pagos[pagos.length - 1];
        const detalle = pagos.map((pago) => `#${pago.id} ${medios[pago.medio] ?? pago.medio} ${ars(pago.importe_ars)}`).join(' + ');
        return `${pagos.length > 1 ? 'Pagos' : 'Pago'} ${detalle}: recibido ${ars(sumar(pagos, 'importe_ars'))}. Aplicado ${ars(sumar(pagos, 'aplicado_ars'))}, de los cuales ${ars(sumar(pagos, 'aplicado_origen_ars'))} al cargo elegido. Deuda restante del cargo: ${ars(ultimo.deuda_cargo_ars)}. Crédito disponible del equipo en esta edición: ${ars(ultimo.credito_equipo_ars)} (de este ingreso: ${ars(sumar(pagos, 'credito_pago_ars'))}).`;
    };
    const actualizarTotal = () => {
        const suma = importes.reduce((acumulado, nombre) => acumulado + (Number.parseInt(form.elements.namedItem(nombre).value, 10) || 0), 0);
        total.textContent = `Total recibido: ${ars(suma)}`;
    };
    const importeEdicion = () => {
        const valor = (form.elements.namedItem('importe_ars')?.value ?? '').trim();
        return /^\d+$/.test(valor) && Number(valor) > 0 ? Number(valor) : null;
    };
    const medioElegido = () => form.querySelector('input[name="medio"]:checked')?.value;
    const sinCambiosEdicion = () => {
        if (operacion !== 'editar_pago' || !cargo) return false;
        const importe = importeEdicion();
        return importe !== null && importe === Number(cargo.importe_ars) && medioElegido() === cargo.medio;
    };
    const puedeGuardarEdicion = () => operacion !== 'editar_pago' || (importeEdicion() !== null && !sinCambiosEdicion());
    // Aviso «Al guardar»: D = deuda actual, A = importe que este pago aplica hoy, N = importe nuevo.
    const lineasEdicion = () => {
        const importe = importeEdicion();
        if (importe === null) return ['Ingresá un importe entero mayor que cero.'];
        if (sinCambiosEdicion()) return ['Todavía no cambiaste nada. Modificá el importe o el medio para poder guardar.'];
        if (importe === Number(cargo.importe_ars)) return ['Solo cambia el medio de pago. La deuda de la planilla no cambia.'];
        const sinEstePago = Number(cargo.deuda_ars) + Number(cargo.aplicado_origen_ars ?? 0);
        const deudaNueva = Math.max(0, sinEstePago - importe);
        const sobrante = Math.max(0, importe - sinEstePago);
        if (deudaNueva > 0) {
            return [`La planilla va a quedar debiendo ${pesos(deudaNueva)}.`, 'Si este partido se ganó 3–0 porque el rival no vino, ese resultado queda suspendido hasta que esta planilla vuelva a estar paga.'];
        }
        if (sobrante > 0) {
            return [`La planilla queda paga. Sobran ${pesos(sobrante)}: se usan para pagar otras planillas que deba el equipo en esta edición, y lo que quede se guarda como crédito.`];
        }
        return ['La planilla queda paga.'];
    };
    const actualizarAvisoEdicion = () => {
        if (operacion !== 'editar_pago' || !cargo) return;
        avisoEdicion.replaceChildren(...lineasEdicion().map((linea, indice) => {
            const parrafo = document.createElement('p');
            parrafo.textContent = linea;
            if (indice > 0) parrafo.className = 'text-gray-600';
            return parrafo;
        }));
        enviar.disabled = !puedeGuardarEdicion();
    };
    const datoResumen = (etiqueta, valor, secundario) => {
        const grupo = document.createElement('div');
        const termino = document.createElement('dt');
        termino.className = 'text-sm text-gray-600';
        termino.textContent = etiqueta;
        const definicion = document.createElement('dd');
        definicion.className = 'text-base font-semibold text-gray-900';
        definicion.textContent = valor;
        if (secundario) {
            const extra = document.createElement('span');
            extra.className = 'ml-2 text-sm font-normal text-gray-600';
            extra.textContent = secundario;
            definicion.append(extra);
        }
        grupo.append(termino, definicion);
        return grupo;
    };
    const pintarResumenEdicion = () => {
        resumenEdicion.replaceChildren(
            datoResumen('Equipo', cargo.equipo ?? ''),
            datoResumen('Registrado', `${pesos(cargo.importe_ars)} ${fraseMedio(cargo.medio)}`),
            datoResumen('Deuda actual de la planilla', pesos(cargo.deuda_ars), `Cargo #${cargo.id}`),
        );
    };
    form.addEventListener('input', (evento) => {
        if (importes.includes(evento.target.name)) actualizarTotal();
        actualizarAvisoEdicion();
    });
    form.addEventListener('change', actualizarAvisoEdicion);
    const firma = (raiz) => raiz.querySelector('[data-cobros-grilla]')?.dataset.firma ?? [...raiz.querySelectorAll('[data-planilla-cobro]')]
        .map((fila) => `${fila.dataset.planillaCobro}:${fila.dataset.deuda}:${fila.dataset.estado}:${fila.dataset.pagos ?? ''}`).join('|');
    // Reemplaza solo la grilla de cobros: el resto de la planilla y el modal abierto no se tocan.
    const refrescarSeccion = async (forzar = false) => {
        if (!seccion || (refrescando && !forzar)) return;
        refrescando = true;
        const consulta = ++ultimaConsulta;
        try {
            const respuesta = await fetch(seccion.dataset.url, { headers: { Accept: 'text/html' }, cache: 'no-store' });
            // Una sesión vencida redirige al login: nunca se inserta otra página en la planilla.
            if (!respuesta.ok || respuesta.redirected) return;
            const plantilla = document.createElement('template');
            plantilla.innerHTML = (await respuesta.text()).trim();
            if (consulta !== ultimaConsulta || !plantilla.content.querySelector('[data-cobros-grilla]')) return;
            if (firma(plantilla.content) === firma(seccion)) return;
            const filaEnfocada = seccion.contains(document.activeElement) ? document.activeElement.closest('[data-planilla-cobro]')?.dataset.planillaCobro : null;
            const selecciones = [...seccion.querySelectorAll('[data-equipo-franja]')].map((fila) => [fila.dataset.equipoFranja, fila.querySelector('[data-deuda-selector]')?.value]);
            seccion.replaceChildren(plantilla.content);
            requestAnimationFrame(() => selecciones.forEach(([equipo, valor]) => {
                const selector = seccion.querySelector(`[data-equipo-franja="${equipo}"] [data-deuda-selector]`);
                if (selector && [...selector.options].some((opcion) => opcion.value === valor)) {
                    selector.value = valor;
                    selector.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }));
            if (filaEnfocada) seccion.querySelector(`[data-planilla-cobro="${filaEnfocada}"] button`)?.focus({ preventScroll: true });
            const fila = cargo ? seccion.querySelector(`[data-planilla-cobro="${cargo.id}"]`) : null;
            const deudaActual = fila ? Number(fila.dataset.deuda) : 0;
            if (modal.open && operacion === 'pago' && cargo && !pendiente && !sinConfirmar && deudaActual !== cargo.deuda_ars) {
                cargo = { ...cargo, deuda_ars: deudaActual };
                contexto.textContent = `Otro usuario actualizó este cobro mientras lo cargabas. Deuda actual: ${ars(cargo.deuda_ars)}.${cargo.deuda_ars === 0 ? ' Ya no queda deuda: cerrá sin registrar.' : ''}`;
            }
        } catch {
            // Sin conexión: se reintenta en la próxima consulta periódica.
        } finally {
            refrescando = false;
        }
    };
    if (seccion) {
        setInterval(() => {
            if (document.visibilityState === 'visible') refrescarSeccion();
        }, 1000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refrescarSeccion();
        });
    }
    const restaurar = leer('cobros-actualizado');
    if (restaurar?.url === location.href) {
        sessionStorage.removeItem('cobros-actualizado');
        ponerAviso(restaurar.mensaje);
        requestAnimationFrame(() => window.scrollTo(0, restaurar.scroll));
    }
    if (sinConfirmar) {
        ponerAviso('Hay un pago sin confirmar. Recuperalo antes de registrar otra operación.', 'error');
        const recuperar = document.createElement('button');
        recuperar.type = 'button';
        recuperar.className = 'min-h-11 rounded border border-gray-300 px-3 py-2 text-sm';
        recuperar.textContent = 'Recuperar pago sin confirmar';
        recuperar.dataset.cobro = 'pago';
        recuperar.dataset.cobrosRecuperar = '';
        recuperar.dataset.cargo = JSON.stringify(sinConfirmar.cargo);
        aviso.after(recuperar);
    }
    const cerrar = () => {
        if (pendiente) return;
        controlador?.abort();
        modal.close();
        document.body.style.overflow = desbordamiento;
        const destino = origen?.isConnected ? origen : (cargo && seccion?.querySelector(`[data-planilla-cobro="${cargo.id}"] button`)) || aviso;
        destino?.focus({ preventScroll: true });
    };
    modal.querySelector('[data-cobros-cerrar]').addEventListener('click', cerrar);
    modal.addEventListener('cancel', (evento) => {
        evento.preventDefault();
        cerrar();
    });
    const bloquearPago = () => {
        importes.forEach((nombre) => { form.elements.namedItem(nombre).readOnly = true; });
    };
    const desbloquearPago = () => {
        importes.forEach((nombre) => { form.elements.namedItem(nombre).readOnly = false; });
    };
    document.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('[data-cobro]');
        const otroModal = [...document.querySelectorAll('dialog[open], [aria-modal="true"]')].some((elemento) => elemento.getClientRects().length > 0);
        if (!boton || pendiente || modal.open || otroModal) return;
        origen = boton;
        operacion = boton.dataset.cobro;
        if (sinConfirmar && operacion !== 'detalle' && operacion !== 'pago') {
            ponerAviso('Primero recuperá el pago sin confirmar desde Registrar pago.', 'error');
            return;
        }
        if (sinConfirmar && operacion === 'pago' && sinConfirmar.datos.idEdicion !== modal.dataset.edicion) {
            ponerAviso(`Volvé a la edición #${sinConfirmar.datos.idEdicion} para recuperar el pago sin confirmar.`, 'error');
            return;
        }
        cargo = sinConfirmar && operacion === 'pago' ? sinConfirmar.cargo : (boton.dataset.pago ? JSON.parse(boton.dataset.pago) : (boton.dataset.cargo ? JSON.parse(boton.dataset.cargo) : null));
        clave = sinConfirmar && operacion === 'pago' ? sinConfirmar.datos.clave_idempotencia : uuid();
        const [nombre, , campos] = configuraciones[operacion];
        form.reset();
        desbloquearPago();
        enviar.disabled = false;
        enviar.textContent = operacion === 'editar_pago' ? TEXTO_EDITAR : TEXTO_CONFIRMAR;
        etiquetaMotivo.textContent = operacion === 'editar_pago' ? MOTIVO_EDICION : MOTIVO;
        ayudaMotivo.textContent = operacion === 'editar_pago' ? AYUDA_MOTIVO_EDICION : '';
        aviso.textContent = '';
        delete aviso.dataset.tono;
        error.textContent = '';
        form.querySelectorAll('[data-error]').forEach((elemento) => { elemento.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach((elemento) => elemento.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-campo]').forEach((elemento) => {
            const activo = campos.includes(elemento.dataset.campo);
            elemento.hidden = !activo;
            elemento.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !activo; });
        });
        titulo.textContent = operacion === 'editar_pago' && cargo ? `Editar pago #${cargo.pago_id}` : nombre;
        contexto.textContent = '';
        if (cargo) {
            contexto.textContent = operacion === 'editar_pago'
                ? ''
                : `${cargo.equipo}${cargo.id ? ` · Cargo #${cargo.id}` : ''} · Deuda $ ${cargo.deuda_ars.toLocaleString('es-AR')}. `;
        }
        contexto.textContent += ({
            exencion: 'Exime solo el saldo pendiente de este cargo. No devuelve pagos ni genera crédito.',
            politica: 'Solo alcanza cargos futuros de este equipo en esta edición. No elimina deuda actual.',
            no_vino: 'El equipo no se presentó y no queda debiendo esta planilla. En fase regular el rival gana 3 a 0 si cubrió su planilla y se presentó. La exención existente se conserva y la ausencia se puede deshacer.',
            deshacer_no_vino: `Se revierte la ausencia. La deuda será ${ars(Math.max(0, (cargo?.precio_ars ?? 0) - (cargo?.eximido_ars ?? 0) - (cargo?.aplicado_ars ?? 0)))}; cualquier exención se conserva. Si el partido tenía el 3 a 0 automático, se quita.`,
            exencion_edicion: `${cargo?.exento ? 'El equipo ya es exento para los partidos futuros.' : 'No pagará planilla en los partidos futuros de esta edición.'} Además se exime todo lo que debe hoy. Los pagos ya registrados se conservan y no se devuelven.`,
            tarifa: 'Afecta futuras altas de todas las ediciones. No cambia cargos históricos ni activa cobros.',
            activar: 'Acción global explícita. Revisá la tarifa antes de confirmar.',
        })[operacion] ?? '';
        if (sinConfirmar && operacion === 'pago') {
            const anteriores = sinConfirmar.datos;
            if ('importe_ars' in anteriores) {
                form.elements.namedItem(anteriores.medio === 'mercado_pago' ? 'importe_mercado_pago_ars' : 'importe_efectivo_ars').value = anteriores.importe_ars;
            } else {
                importes.forEach((nombre) => { form.elements.namedItem(nombre).value = anteriores[nombre] ?? ''; });
            }
            bloquearPago();
            contexto.textContent += ' Recuperación: se reenviará exactamente el pago anterior con la misma clave, sin duplicar el ingreso. Confirmalo antes de iniciar otro pago.';
        }
        actualizarTotal();
        if (operacion === 'editar_pago' && cargo) {
            form.elements.namedItem('importe_ars').value = String(cargo.importe_ars);
            const elegido = form.querySelector(`input[name="medio"][value="${cargo.medio}"]`);
            if (elegido) elegido.checked = true;
            pintarResumenEdicion();
            actualizarAvisoEdicion();
        }
        form.hidden = operacion === 'detalle';
        detalle.hidden = operacion !== 'detalle';
        detalle.replaceChildren();
        desbordamiento = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.showModal();
        titulo.focus();
        if (operacion !== 'detalle') return;
        detalle.textContent = 'Cargando historial…';
        controlador = new AbortController();
        try {
            const parametros = new URLSearchParams({ idEdicion: modal.dataset.edicion, idEquipo: cargo.idEquipo });
            const respuesta = await fetch(`${modal.dataset.base}/detalle?${parametros}`, { headers: { Accept: 'text/html' }, signal: controlador.signal });
            if (!respuesta.ok) throw new Error('detalle');
            const html = await respuesta.text();
            if (modal.open) detalle.innerHTML = html;
        } catch (excepcion) {
            if (excepcion.name !== 'AbortError') detalle.textContent = 'No se pudo cargar el historial. Cerrá e intentá nuevamente.';
        }
    });
    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (pendiente || !puedeGuardarEdicion()) return;
        const recuperando = operacion === 'pago' && sinConfirmar !== null;
        const token = form.elements.namedItem('_token').value;
        const datos = operacion === 'pago' && sinConfirmar ? { ...sinConfirmar.datos } : Object.fromEntries(new FormData(form));
        if (!(operacion === 'pago' && sinConfirmar)) {
            Object.assign(datos, { idEdicion: modal.dataset.edicion, idEquipo: cargo?.idEquipo, cargo_id: cargo?.id, clave_idempotencia: clave });
        }
        if (operacion === 'editar_pago') datos.pago_id = cargo?.pago_id;
        delete datos._token;
        pendiente = true;
        enviar.disabled = true;
        enviar.textContent = 'Guardando…';
        error.textContent = '';
        form.querySelectorAll('[data-error]').forEach((elemento) => { elemento.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach((elemento) => elemento.removeAttribute('aria-invalid'));
        try {
            if (operacion === 'pago') {
                sinConfirmar = { cargo, datos };
                sessionStorage.setItem('cobros-pago-sin-confirmar', JSON.stringify(sinConfirmar));
                bloquearPago();
            }
            const respuesta = await fetch(`${modal.dataset.base}/${configuraciones[operacion][1]}`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(datos),
            });
            const resultado = await respuesta.json();
            if (!respuesta.ok) {
                if (respuesta.status === 422 && operacion === 'pago' && !recuperando) {
                    sinConfirmar = null;
                    sessionStorage.removeItem('cobros-pago-sin-confirmar');
                    desbloquearPago();
                    clave = uuid();
                }
                error.textContent = respuesta.status === 422 ? 'Revisá los datos indicados.' : (respuesta.status === 419 ? 'La sesión venció. Recargá la página antes de continuar.' : 'No se pudo completar la operación. Intentá nuevamente.');
                for (const [campo, mensajes] of Object.entries(resultado.errors ?? {})) {
                    const destino = form.querySelector(`[data-error="${campo}"]`);
                    if (destino) {
                        destino.textContent = mensajes.join(' ');
                        form.elements.namedItem(campo)?.setAttribute('aria-invalid', 'true');
                    } else error.textContent += ` ${mensajes.join(' ')}`;
                }
                error.focus();
                return;
            }
            const pagos = resultado.pagos ?? (resultado.pago ? [resultado.pago] : null);
            const exento = resultado.exencion_edicion;
            const mensaje = operacion === 'editar_pago' && resultado.evento
                ? reciboEdicion(resultado.pago, resultado.evento)
                : pagos ? recibo(pagos) : (exento
                ? `${cargo.equipo} quedó exento de planilla en esta edición. Se eximieron ${exento.cargos_eximidos} cargos por ${ars(exento.eximido_ars)}.`
                : ({
                    no_vino: `${cargo.equipo} figura como que no vino: no debe la planilla de este partido.`,
                    deshacer_no_vino: `Se deshizo "No vino": ${cargo.equipo} ${cargo.eximido_ars >= cargo.precio_ars ? 'mantiene la planilla exenta' : `vuelve a deber ${ars(Math.max(0, cargo.precio_ars - cargo.eximido_ars - cargo.aplicado_ars))} por este partido`}.`,
                })[operacion] ?? 'Operación registrada. Saldos actualizados.');
            if (operacion === 'pago') {
                sinConfirmar = null;
                sessionStorage.removeItem('cobros-pago-sin-confirmar');
            }
            // "No vino" also changes the match result, so the whole planilla must reload.
            if (seccion && !['no_vino', 'deshacer_no_vino'].includes(operacion)) {
                document.querySelector('[data-cobros-recuperar]')?.remove();
                pendiente = false;
                cerrar();
                await refrescarSeccion(true);
                ponerAviso(mensaje);
                aviso.focus({ preventScroll: true });
                return;
            }
            sessionStorage.setItem('cobros-actualizado', JSON.stringify({ url: location.href, scroll: window.scrollY, mensaje }));
            location.reload();
        } catch {
            error.textContent = 'No se pudo confirmar el resultado. Reintentá el pago recuperado sin cambiar los datos para evitar duplicar el ingreso.';
            error.focus();
        } finally {
            pendiente = false;
            enviar.disabled = false;
            enviar.textContent = operacion === 'editar_pago' ? TEXTO_EDITAR : TEXTO_CONFIRMAR;
            actualizarAvisoEdicion();
        }
    });
})();
