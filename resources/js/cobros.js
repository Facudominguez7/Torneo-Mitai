(() => {
    const modal = document.querySelector('#cobros-modal');
    if (!modal) return;
    const form = document.querySelector('#cobros-form');
    const titulo = document.querySelector('#cobros-titulo');
    const contexto = document.querySelector('#cobros-contexto');
    const detalle = document.querySelector('#cobros-detalle');
    const error = document.querySelector('#cobros-error');
    const enviar = document.querySelector('#cobros-enviar');
    const aviso = document.querySelector('#cobros-aviso');
    const total = document.querySelector('#cobros-total');
    const seccion = document.querySelector('[data-cobros-seccion]');
    const importes = ['importe_efectivo_ars', 'importe_mercado_pago_ars'];
    const medios = { efectivo: 'Efectivo', mercado_pago: 'Mercado Pago', transferencia: 'Transferencia' };
    const configuraciones = {
        pago: ['Registrar pago', 'pagos', ['importes']],
        exencion: ['Eximir deuda residual', 'exenciones', ['motivo']],
        politica: ['Exención para cargos futuros', 'politicas', ['motivo']],
        tarifa: ['Guardar tarifa global', 'tarifa', ['tarifa_ars']],
        activar: ['Activar cobros globalmente', 'activar', ['confirmar']],
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
    const recibo = (pagos) => {
        const ultimo = pagos[pagos.length - 1];
        const detalle = pagos.map((pago) => `#${pago.id} ${medios[pago.medio] ?? pago.medio} ${ars(pago.importe_ars)}`).join(' + ');
        return `${pagos.length > 1 ? 'Pagos' : 'Pago'} ${detalle}: recibido ${ars(sumar(pagos, 'importe_ars'))}. Aplicado ${ars(sumar(pagos, 'aplicado_ars'))}, de los cuales ${ars(sumar(pagos, 'aplicado_origen_ars'))} al cargo elegido. Deuda restante del cargo: ${ars(ultimo.deuda_cargo_ars)}. Crédito disponible del equipo en esta edición: ${ars(ultimo.credito_equipo_ars)} (de este ingreso: ${ars(sumar(pagos, 'credito_pago_ars'))}).`;
    };
    const actualizarTotal = () => {
        const suma = importes.reduce((acumulado, nombre) => acumulado + (Number.parseInt(form.elements.namedItem(nombre).value, 10) || 0), 0);
        total.textContent = `Total recibido: ${ars(suma)}`;
    };
    form.addEventListener('input', (evento) => {
        if (importes.includes(evento.target.name)) actualizarTotal();
    });
    const firma = (raiz) => [...raiz.querySelectorAll('[data-planilla-cobro]')]
        .map((fila) => `${fila.dataset.planillaCobro}:${fila.dataset.deuda}:${fila.dataset.estado}`).join('|');
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
            seccion.replaceChildren(plantilla.content);
            if (filaEnfocada) seccion.querySelector(`[data-planilla-cobro="${filaEnfocada}"] button`)?.focus({ preventScroll: true });
            const fila = cargo ? seccion.querySelector(`[data-planilla-cobro="${cargo.id}"]`) : null;
            if (modal.open && operacion === 'pago' && fila && !pendiente && !sinConfirmar && Number(fila.dataset.deuda) !== cargo.deuda_ars) {
                cargo = { ...cargo, deuda_ars: Number(fila.dataset.deuda) };
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
        }, 10000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refrescarSeccion();
        });
    }
    const restaurar = leer('cobros-actualizado');
    if (restaurar?.url === location.href) {
        sessionStorage.removeItem('cobros-actualizado');
        aviso.textContent = restaurar.mensaje;
        requestAnimationFrame(() => window.scrollTo(0, restaurar.scroll));
    }
    if (sinConfirmar) {
        aviso.textContent = 'Hay un pago sin confirmar. Recuperalo antes de registrar otra operación.';
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
            aviso.textContent = 'Primero recuperá el pago sin confirmar desde Registrar pago.';
            return;
        }
        if (sinConfirmar && operacion === 'pago' && sinConfirmar.datos.idEdicion !== modal.dataset.edicion) {
            aviso.textContent = `Volvé a la edición #${sinConfirmar.datos.idEdicion} para recuperar el pago sin confirmar.`;
            return;
        }
        cargo = sinConfirmar && operacion === 'pago' ? sinConfirmar.cargo : (boton.dataset.cargo ? JSON.parse(boton.dataset.cargo) : null);
        clave = sinConfirmar && operacion === 'pago' ? sinConfirmar.datos.clave_idempotencia : uuid();
        const [nombre, , campos] = configuraciones[operacion];
        form.reset();
        desbloquearPago();
        error.textContent = '';
        form.querySelectorAll('[data-error]').forEach((elemento) => { elemento.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach((elemento) => elemento.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-campo]').forEach((elemento) => {
            const activo = campos.includes(elemento.dataset.campo);
            elemento.hidden = !activo;
            elemento.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !activo; });
        });
        titulo.textContent = nombre;
        contexto.textContent = cargo ? `${cargo.equipo} · Cargo #${cargo.id} · Deuda $ ${cargo.deuda_ars.toLocaleString('es-AR')}. ` : '';
        contexto.textContent += ({
            exencion: 'Exime solo el saldo pendiente de este cargo. No devuelve pagos ni genera crédito.',
            politica: 'Solo alcanza cargos futuros de este equipo en esta edición. No elimina deuda actual.',
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
        if (pendiente) return;
        const recuperando = operacion === 'pago' && sinConfirmar !== null;
        const token = form.elements.namedItem('_token').value;
        const datos = operacion === 'pago' && sinConfirmar ? { ...sinConfirmar.datos } : Object.fromEntries(new FormData(form));
        if (!(operacion === 'pago' && sinConfirmar)) {
            Object.assign(datos, { idEdicion: modal.dataset.edicion, idEquipo: cargo?.idEquipo, cargo_id: cargo?.id, clave_idempotencia: clave });
        }
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
            const mensaje = pagos ? recibo(pagos) : 'Operación registrada. Saldos actualizados.';
            if (operacion === 'pago') {
                sinConfirmar = null;
                sessionStorage.removeItem('cobros-pago-sin-confirmar');
            }
            if (seccion) {
                document.querySelector('[data-cobros-recuperar]')?.remove();
                pendiente = false;
                cerrar();
                await refrescarSeccion(true);
                aviso.textContent = mensaje;
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
            enviar.textContent = 'Confirmar';
        }
    });
})();
