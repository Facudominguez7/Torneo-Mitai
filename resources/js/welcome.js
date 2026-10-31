// Welcome.js - Menús dropdown
(function () {
    const button = document.querySelector('[data-collapse-toggle="navbar-dropdown"]');
    const menu = document.getElementById('navbar-dropdown');
    const botonEdiciones = document.querySelector('[data-dropdown-toggle="dropdownEdiciones"]');
    const menuEdiciones = document.getElementById('dropdownEdiciones');
    const botonUser = document.querySelector('[data-dropdown-toggle="dropdownUser"]');
    const menuUser = document.getElementById('dropdownUser');

    if (button && menu) {
        button.addEventListener('click', () => menu.classList.toggle('hidden'));
    }

    if (botonEdiciones && menuEdiciones) {
        botonEdiciones.addEventListener('click', function () {
            const rect = botonEdiciones.getBoundingClientRect();
            if (window.innerWidth < 768) {
                menuEdiciones.style.cssText = `top:${rect.bottom}px;left:0;width:100%`;
            } else {
                menuEdiciones.style.cssText = `top:${rect.bottom}px;left:${rect.left}px`;
            }
            menuEdiciones.classList.toggle('hidden');
        });
    }

    document.addEventListener('click', function (e) {
        if (menuEdiciones && botonEdiciones && !menuEdiciones.contains(e.target) && e.target !== botonEdiciones) {
            menuEdiciones.classList.add('hidden');
        }
        if (menuUser && botonUser && !menuUser.contains(e.target) && e.target !== botonUser) {
            menuUser.classList.add('hidden');
        }
    });
})();

function descargarPDF() {
    const a = document.createElement('a');
    a.href = '';
    a.download = 'ReglamentoMitaiCup.pdf';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Seguir mi equipo (#99): selector client-side + panel con próximo partido,
// último resultado y accesos (sin frameworks y sin requests para filtrar).
// El favorito persiste en localStorage y los datos vienen de /equipo-seguido.
// El panel replica el artboard design/inicio-primer-pantalla/06 (tema dual):
// superficies con tokens tema.*, velo de foco y tilt 3D con puntero fino.
(function () {
    const CLAVE_FAVORITO = 'mitai_equipo_seguido';

    const bloque = document.getElementById('seguir-equipo-bloque');
    if (!bloque) {
        return;
    }

    const datosScript = document.getElementById('seguir-equipo-datos');
    const idEdicion = bloque.dataset.idEdicion;
    const equipos = datosScript ? JSON.parse(datosScript.textContent) : [];

    const selector = document.getElementById('seguir-equipo-selector');
    const card = document.getElementById('seguir-equipo-card');
    const buscador = document.getElementById('seguir-equipo-buscador');
    const lista = document.getElementById('seguir-equipo-lista');
    const sinResultados = document.getElementById('seguir-equipo-sin-resultados');
    const plantillaSuscripcion = document.getElementById('seguir-equipo-suscripcion');
    const main = document.querySelector('main.tm-velo-host');

    function normalizar(texto) {
        return (texto || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function leerFavorito() {
        try {
            const guardado = JSON.parse(localStorage.getItem(CLAVE_FAVORITO) || 'null');
            // Enmienda #100: el favorito persiste {nombre, idCategoria}. Los
            // guardados viejos solo tienen nombre y siguen funcionando.
            return guardado && guardado.nombre ? guardado : null;
        } catch {
            return null;
        }
    }

    function guardarFavorito(nombre, idCategoria) {
        localStorage.setItem(CLAVE_FAVORITO, JSON.stringify({ nombre: nombre, idCategoria: idCategoria ?? null }));
    }

    function iniciales(nombre) {
        return (nombre || '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(function (palabra) {
                return palabra.charAt(0).toUpperCase();
            })
            .join('');
    }

    function nombreCategoriaDe(idCategoria) {
        const equipo = equipos.find(function (item) {
            return String(item.idCategoria ?? '') === String(idCategoria ?? '');
        });
        return equipo && equipo.nombreCategoria ? equipo.nombreCategoria : null;
    }

    function crearElemento(etiqueta, clases, texto) {
        const elemento = document.createElement(etiqueta);
        elemento.className = clases;
        if (texto !== undefined) {
            elemento.textContent = texto;
        }
        return elemento;
    }

    function crearBotonAccion(texto, accion) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = texto;
        boton.dataset.accion = accion;
        boton.className = 'inline-flex items-center rounded-full border border-tema-linea/30 px-4 py-2 text-sm font-semibold text-tema-dim/80 transition hover:bg-[var(--tm-fila-hover-bg)] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light';
        return boton;
    }

    function crearEnlace(href, texto) {
        const enlace = document.createElement('a');
        enlace.href = href;
        enlace.textContent = texto;
        enlace.className = 'inline-flex items-center justify-between gap-2 rounded-xl border border-tema-linea/15 bg-[var(--tm-fila-bg)] px-4 py-3 text-sm font-semibold transition hover:bg-[var(--tm-fila-hover-bg)] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-teal-light';
        const flecha = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        flecha.setAttribute('viewBox', '0 0 14 14');
        flecha.setAttribute('width', '14');
        flecha.setAttribute('height', '14');
        flecha.setAttribute('aria-hidden', 'true');
        flecha.classList.add('text-brand-teal-light');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'M3 7h8M7.5 3.5L11 7l-3.5 3.5');
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', 'currentColor');
        path.setAttribute('stroke-width', '1.8');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        flecha.appendChild(path);
        enlace.appendChild(flecha);
        return enlace;
    }

    function crearStat(rotulo, valor, detalle) {
        const stat = document.createElement('div');
        stat.className = 'tm-stat rounded-2xl border border-tema-linea/15 bg-[var(--tm-superficie-bg)] px-4 py-3.5';
        stat.appendChild(crearElemento('p', 'text-[10.5px] font-bold uppercase tracking-[0.12em] text-tema-acento', rotulo));
        stat.appendChild(crearElemento('p', 'mt-2 text-sm font-bold leading-snug', valor));
        if (detalle) {
            stat.appendChild(crearElemento('p', 'mt-1 text-xs text-tema-dim/70', detalle));
        }
        return stat;
    }

    // Tilt 3D del panel (artboard 06): solo con puntero fino y sin
    // prefers-reduced-motion; el brillo y la perspectiva viven en app.css.
    function iniciarTilt(panel) {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const punteroFino = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (reduce || !punteroFino) {
            return;
        }
        const activar = function () {
            panel.classList.remove('tm-anim-in');
            panel.classList.add('tm-tilt-ready');
        };
        panel.addEventListener('animationend', activar, { once: true });
        window.setTimeout(activar, 1500);

        let raf = 0;
        panel.addEventListener('pointermove', function (evento) {
            if (!panel.classList.contains('tm-tilt-ready')) {
                return;
            }
            const rect = panel.getBoundingClientRect();
            const px = (evento.clientX - rect.left) / rect.width;
            const py = (evento.clientY - rect.top) / rect.height;
            window.cancelAnimationFrame(raf);
            raf = window.requestAnimationFrame(function () {
                panel.style.setProperty('--tm-ry', ((px - 0.5) * 10).toFixed(2) + 'deg');
                panel.style.setProperty('--tm-rx', ((0.5 - py) * 8).toFixed(2) + 'deg');
                panel.style.setProperty('--tm-gx', (px * 100).toFixed(1) + '%');
                panel.style.setProperty('--tm-gy', (py * 100).toFixed(1) + '%');
            });
        });
        panel.addEventListener('pointerleave', function () {
            window.cancelAnimationFrame(raf);
            panel.style.setProperty('--tm-rx', '0deg');
            panel.style.setProperty('--tm-ry', '0deg');
        });
    }

    function escudoEquipo(nombre, foto) {
        // Logo del equipo con fallback al logo Mitai (misma convención de
        // onerror que layouts/campeones y layouts/tabla-vallas).
        const escudo = document.createElement('div');
        escudo.className = 'tm-escudo flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-oro-400/40 bg-brand-navy';
        if (foto) {
            const imagen = document.createElement('img');
            imagen.src = '/fotos/equipos/' + foto;
            imagen.alt = '';
            imagen.loading = 'lazy';
            imagen.className = 'h-full w-full object-cover';
            imagen.onerror = function () {
                this.onerror = null;
                this.src = '/fotos/Logo_Mitai_SinFondo.png';
            };
            escudo.appendChild(imagen);
        } else {
            escudo.setAttribute('aria-hidden', 'true');
            escudo.appendChild(crearElemento('span', 'text-base font-extrabold text-oro-400', iniciales(nombre)));
        }
        return escudo;
    }

    // Formulario "Avisame por correo": los avisos son los mismos para todos, sin depender del equipo seguido.
    function crearSuscripcion() {
        return plantillaSuscripcion.content.cloneNode(true);
    }

    function renderCard(nombre, datos) {
        card.textContent = '';
        buscador.value = nombre;

        if (!datos.disponible) {
            const panel = document.createElement('div');
            panel.className = 'rounded-3xl border border-tema-panel-borde/40 bg-[var(--tm-panel-bg)] p-6 text-left';
            panel.dataset.rol = 'no-disponible';
            panel.appendChild(crearElemento('p', 'text-sm text-yellow-600 dark:text-yellow-300', nombre + ' no participa en esta edición.'));
            const acciones = document.createElement('div');
            acciones.className = 'mt-4 flex flex-wrap gap-2';
            acciones.appendChild(crearBotonAccion('Cambiar equipo', 'cambiar'));
            acciones.appendChild(crearBotonAccion('Quitar', 'quitar'));
            panel.appendChild(acciones);
            card.appendChild(panel);
            card.classList.remove('hidden');
            return;
        }

        const panel = document.createElement('div');
        panel.className = 'tm-panel tm-anim-in rounded-3xl border border-tema-panel-borde/40 bg-[var(--tm-panel-bg)] p-6 text-left sm:p-7';

        const encabezado = document.createElement('div');
        encabezado.className = 'flex items-center gap-4';
        encabezado.appendChild(escudoEquipo(nombre, datos.equipo ? datos.equipo.foto : null));
        const titulo = document.createElement('div');
        titulo.appendChild(crearElemento('h2', 'tm-titulo-equipo text-2xl font-extrabold tracking-tight', nombre));
        const metaCategoria = nombreCategoriaDe(datos.equipo ? datos.equipo.idCategoria : null);
        titulo.appendChild(crearElemento('p', 'tm-meta-equipo mt-1 text-[13px] text-tema-dim/70', metaCategoria ? metaCategoria + ' · Apertura' : 'Equipo seguido'));
        encabezado.appendChild(titulo);
        encabezado.appendChild(crearElemento('span', 'tm-chip-seguir ml-auto flex-shrink-0 rounded-full bg-oro-400 px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-brand-navy-deep', 'Siguiendo'));
        panel.appendChild(encabezado);

        const grilla = document.createElement('div');
        grilla.className = 'mt-6 grid gap-3 sm:grid-cols-3';

        if (datos.proximo) {
            const proximo = crearStat(
                'Próximo partido',
                (datos.proximo.esLocal ? 'vs ' : 'de visita ante ') + datos.proximo.rival,
                [datos.proximo.fecha_nombre, datos.proximo.horario, datos.proximo.cancha].filter(Boolean).join(' · '),
            );
            proximo.dataset.rol = 'proximo';
            grilla.appendChild(proximo);
        }

        if (datos.ultimo) {
            const ultimo = crearStat(
                'Último resultado',
                datos.ultimo.goles_favor + ' – ' + datos.ultimo.goles_contra,
                (datos.ultimo.esLocal ? 'Local' : 'Visitante') + ' · vs ' + datos.ultimo.rival,
            );
            ultimo.dataset.rol = 'ultimo';
            grilla.appendChild(ultimo);
        }

        if (datos.posicion) {
            const diferencia = datos.posicion.diferencia;
            const posicion = crearStat(
                'Posición',
                datos.posicion.posicion + '° de ' + datos.posicion.total,
                datos.posicion.puntos + ' pts · ' + (diferencia >= 0 ? '+' : '') + diferencia + ' de diferencia de gol',
            );
            posicion.dataset.rol = 'posicion';
            grilla.appendChild(posicion);
        }

        if (datos.forma && datos.forma.length) {
            const forma = document.createElement('div');
            forma.className = 'tm-stat rounded-2xl border border-tema-linea/15 bg-[var(--tm-superficie-bg)] px-4 py-3.5';
            forma.dataset.rol = 'forma';
            forma.appendChild(crearElemento('p', 'text-[10.5px] font-bold uppercase tracking-[0.12em] text-tema-acento', 'Forma'));
            const fila = document.createElement('div');
            fila.className = 'tm-forma mt-2';
            fila.setAttribute('aria-label', 'Últimos ' + datos.forma.length + ' partidos: ' + datos.forma.join(', '));
            datos.forma.forEach(function (letra) {
                fila.appendChild(crearElemento('span', letra.toLowerCase(), letra));
            });
            forma.appendChild(fila);
            forma.appendChild(crearElemento('p', 'mt-2 text-xs text-tema-dim/70', 'Últimos ' + datos.forma.length + ' partidos'));
            grilla.appendChild(forma);
        }

        panel.appendChild(grilla);

        const accesos = document.createElement('div');
        accesos.className = 'tm-stat mt-3 rounded-2xl border border-tema-linea/15 bg-[var(--tm-superficie-bg)] px-4 py-3';
        accesos.appendChild(crearElemento('p', 'text-[10.5px] font-bold uppercase tracking-[0.12em] text-tema-acento', 'Accesos'));
        const accesosFila = document.createElement('div');
        accesosFila.className = 'mt-2 grid gap-2 sm:grid-cols-2';
        if (datos.links && datos.links.fixture) {
            accesosFila.appendChild(crearEnlace(datos.links.fixture, 'Fixture del equipo'));
        }
        if (datos.links && datos.links.tabla) {
            accesosFila.appendChild(crearEnlace(datos.links.tabla, 'Tabla de posiciones'));
        }
        accesos.appendChild(accesosFila);
        panel.appendChild(accesos);

        if (!datos.proximo && !datos.ultimo) {
            panel.appendChild(crearElemento('p', 'mt-4 text-sm text-tema-dim/70', 'Todavía no hay partidos cargados para tu equipo.'));
        }

        const acciones = document.createElement('div');
        acciones.className = 'mt-5 flex flex-wrap gap-2';
        acciones.appendChild(crearBotonAccion('Cambiar equipo', 'cambiar'));
        acciones.appendChild(crearBotonAccion('Quitar', 'quitar'));
        panel.appendChild(acciones);

        if (plantillaSuscripcion) {
            panel.appendChild(crearSuscripcion());
        }

        card.appendChild(panel);
        card.classList.remove('hidden');
        iniciarTilt(panel);
    }

    function abrirBuscador() {
        selector.classList.add('abierto');
        if (main) {
            main.classList.add('abierto');
        }
        buscador.setAttribute('aria-expanded', 'true');
        lista.classList.remove('hidden');
        renderLista(buscador.value);
    }

    function cerrarBuscador() {
        selector.classList.remove('abierto');
        if (main && !document.getElementById('selector-edicion')?.classList.contains('abierto')) {
            main.classList.remove('abierto');
        }
        buscador.setAttribute('aria-expanded', 'false');
        lista.classList.add('hidden');
    }

    function mostrarSelector() {
        card.textContent = '';
        card.classList.add('hidden');
        buscador.value = '';
        cerrarBuscador();
    }

    function renderLista(termino) {
        const consulta = normalizar(termino.trim());
        const coincidencias = consulta
            ? equipos.filter(function (equipo) {
                return normalizar(equipo.nombre).includes(consulta);
            })
            : equipos;

        lista.textContent = '';
        coincidencias.forEach(function (equipo) {
            const item = document.createElement('li');
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.dataset.elegir = equipo.nombre;
            // Enmienda #100: cada opción distingue equipos homónimos por categoría.
            boton.dataset.idCategoria = equipo.idCategoria ?? '';
            boton.className = 'tm-sugerencia flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold text-slate-900 transition hover:bg-brand-teal/10 focus:outline-none focus-visible:bg-brand-teal/10';

            const escudo = document.createElement('span');
            escudo.className = 'flex h-8 w-8 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-brand-navy';
            escudo.setAttribute('aria-hidden', 'true');
            const imagen = document.createElement('img');
            const logoMitai = '/fotos/Logo_Mitai_SinFondo.png';
            imagen.src = equipo.foto ? '/fotos/equipos/' + equipo.foto : logoMitai;
            imagen.alt = '';
            imagen.loading = 'lazy';
            imagen.className = 'h-full w-full object-cover';
            imagen.onerror = function () {
                this.onerror = null;
                this.src = logoMitai;
            };
            escudo.appendChild(imagen);
            boton.appendChild(escudo);
            boton.appendChild(document.createTextNode(equipo.nombre));
            if (equipo.nombreCategoria) {
                boton.appendChild(crearElemento('span', 'ml-auto flex-shrink-0 rounded-full bg-brand-teal-light px-2.5 py-1 font-mono text-[10px] font-bold text-brand-navy-deep', equipo.nombreCategoria));
            }

            boton.addEventListener('click', function () {
                guardarFavorito(equipo.nombre, equipo.idCategoria);
                cargarCard();
            });
            item.appendChild(boton);
            lista.appendChild(item);
        });

        sinResultados.classList.toggle('hidden', coincidencias.length > 0);
    }

    async function cargarCard() {
        const favorito = leerFavorito();
        if (!favorito) {
            mostrarSelector();
            return;
        }

        cerrarBuscador();
        card.textContent = '';
        card.classList.add('hidden');

        try {
            const parametros = new URLSearchParams({ nombre: favorito.nombre, idEdicion: idEdicion });
            if (favorito.idCategoria) {
                parametros.set('idCategoria', favorito.idCategoria);
            }
            const respuesta = await fetch('/equipo-seguido?' + parametros.toString());
            if (!respuesta.ok) {
                mostrarSelector();
                return;
            }
            renderCard(favorito.nombre, await respuesta.json());
        } catch {
            mostrarSelector();
        }
    }

    card.addEventListener('click', function (evento) {
        const accion = evento.target.closest('[data-accion]');
        if (!accion) {
            return;
        }
        if (accion.dataset.accion === 'quitar') {
            localStorage.removeItem(CLAVE_FAVORITO);
            mostrarSelector();
        }
        if (accion.dataset.accion === 'cambiar') {
            card.textContent = '';
            card.classList.add('hidden');
            buscador.focus();
        }
    });

    // El desplegable permanece cerrado: se abre con foco o clic en el campo
    // y se cierra con Esc, un clic afuera o al elegir una sugerencia.
    let evitarReabrir = false;
    buscador.addEventListener('focus', function () {
        if (evitarReabrir) {
            evitarReabrir = false;
            return;
        }
        abrirBuscador();
    });
    buscador.addEventListener('click', abrirBuscador);
    buscador.addEventListener('input', function () {
        renderLista(buscador.value);
    });
    buscador.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            cerrarBuscador();
        }
    });
    document.addEventListener('click', function (evento) {
        if (!selector.contains(evento.target)) {
            cerrarBuscador();
        }
    });

    if (leerFavorito()) {
        cargarCard();
    } else {
        mostrarSelector();
    }
})();

// Selector de edición (#fixture artboard 06): dropdown diseñado en lugar del
// <select> nativo. Mismo patrón de apertura que el buscador (velo de foco,
// cascada escalonada); elegir una opción navega a su data-url.
(function () {
    const raiz = document.getElementById('selector-edicion');
    const disparador = document.getElementById('idEdicion');
    if (!raiz || !disparador) {
        return;
    }
    const main = document.querySelector('main.tm-velo-host');
    const opciones = Array.prototype.slice.call(raiz.querySelectorAll('[role="option"]'));
    if (!opciones.length) {
        return;
    }
    let indiceResaltado = Math.max(0, opciones.findIndex(function (o) { return o.getAttribute('aria-selected') === 'true'; }));

    function estaAbierto() {
        return raiz.classList.contains('abierto');
    }

    function resaltar(indice) {
        indiceResaltado = (indice + opciones.length) % opciones.length;
        opciones.forEach(function (opcion, i) {
            opcion.classList.toggle('tm-resaltada', i === indiceResaltado);
        });
        disparador.setAttribute('aria-activedescendant', opciones[indiceResaltado].id);
    }

    function abrir() {
        raiz.classList.add('abierto');
        if (main) {
            main.classList.add('abierto');
        }
        disparador.setAttribute('aria-expanded', 'true');
        resaltar(indiceResaltado);
    }

    function cerrar() {
        raiz.classList.remove('abierto');
        if (main && !document.getElementById('seguir-equipo-selector')?.classList.contains('abierto')) {
            main.classList.remove('abierto');
        }
        disparador.setAttribute('aria-expanded', 'false');
    }

    function elegir(indice) {
        const url = opciones[indice].dataset.url;
        if (url) {
            window.location.href = url;
        }
    }

    disparador.addEventListener('click', function () {
        if (estaAbierto()) {
            cerrar();
        } else {
            abrir();
        }
    });
    disparador.addEventListener('keydown', function (evento) {
        if (evento.key === 'ArrowDown') {
            evento.preventDefault();
            if (estaAbierto()) { resaltar(indiceResaltado + 1); } else { abrir(); }
        } else if (evento.key === 'ArrowUp') {
            evento.preventDefault();
            if (estaAbierto()) { resaltar(indiceResaltado - 1); } else { abrir(); }
        } else if (evento.key === 'Enter' || evento.key === ' ') {
            if (estaAbierto()) {
                evento.preventDefault();
                elegir(indiceResaltado);
            }
        } else if (evento.key === 'Escape') {
            cerrar();
        }
    });
    opciones.forEach(function (opcion, i) {
        opcion.addEventListener('click', function () { elegir(i); });
        opcion.addEventListener('mousemove', function () { resaltar(i); });
    });
    document.addEventListener('click', function (evento) {
        if (!raiz.contains(evento.target)) {
            cerrar();
        }
    });
})();
