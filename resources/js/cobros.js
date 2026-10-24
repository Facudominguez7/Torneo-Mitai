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
    const configuraciones = {
        pago: ['Registrar pago', 'pagos', ['importe_ars', 'medio']],
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
    const recibo = (pago) => `Pago #${pago.id}: recibido ${ars(pago.importe_ars)} (un único ingreso). Aplicado ${ars(pago.aplicado_ars)}, de los cuales ${ars(pago.aplicado_origen_ars)} al cargo elegido. Deuda restante del cargo: ${ars(pago.deuda_cargo_ars)}. Crédito disponible del equipo en esta edición: ${ars(pago.credito_equipo_ars)} (de este pago: ${ars(pago.credito_pago_ars)}).`;
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
        recuperar.dataset.cargo = JSON.stringify(sinConfirmar.cargo);
        aviso.after(recuperar);
    }
    const cerrar = () => {
        if (pendiente) return;
        controlador?.abort();
        modal.close();
        document.body.style.overflow = desbordamiento;
        origen?.focus({ preventScroll: true });
    };
    modal.querySelector('[data-cobros-cerrar]').addEventListener('click', cerrar);
    modal.addEventListener('cancel', (evento) => {
        evento.preventDefault();
        cerrar();
    });
    const bloquearPago = () => {
        form.elements.namedItem('importe_ars').readOnly = true;
        form.elements.namedItem('medio').disabled = true;
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
        form.elements.namedItem('importe_ars').readOnly = false;
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
            form.elements.namedItem('importe_ars').value = sinConfirmar.datos.importe_ars;
            form.elements.namedItem('medio').value = sinConfirmar.datos.medio;
            bloquearPago();
            contexto.textContent += ' Recuperación: se reenviará exactamente el pago anterior con la misma clave, sin duplicar el ingreso. Confirmalo antes de iniciar otro pago.';
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
                    form.elements.namedItem('importe_ars').readOnly = false;
                    form.elements.namedItem('medio').disabled = false;
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
            const mensaje = resultado.pago ? recibo(resultado.pago) : 'Operación registrada. Saldos actualizados.';
            if (operacion === 'pago') {
                sinConfirmar = null;
                sessionStorage.removeItem('cobros-pago-sin-confirmar');
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
