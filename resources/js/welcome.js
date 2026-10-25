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

// Seguir mi equipo (#99): selector client-side + card con próximo partido y
// último resultado. Sin frameworks y sin requests para filtrar.
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

    function crearBotonAccion(texto, accion) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = texto;
        boton.dataset.accion = accion;
        boton.className = 'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition';
        if (accion === 'quitar') {
            boton.classList.add('border', 'border-white/30', 'text-white/80', 'hover:border-white', 'hover:bg-white/10');
        } else {
            boton.classList.add('bg-emerald-400/90', 'text-black', 'hover:bg-emerald-300');
        }
        return boton;
    }

    function crearEnlace(href, texto) {
        const enlace = document.createElement('a');
        enlace.href = href;
        enlace.textContent = texto;
        enlace.className = 'inline-flex items-center gap-2 rounded-full border border-emerald-300/50 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-400/20';
        return enlace;
    }

    function crearParrafo(clases, texto) {
        const parrafo = document.createElement('p');
        parrafo.className = clases;
        parrafo.textContent = texto;
        return parrafo;
    }

    function crearSeccionPartido(titulo, lineas) {
        const seccion = document.createElement('div');
        seccion.className = 'rounded-2xl border border-white/10 bg-black/45 px-4 py-3';
        seccion.appendChild(crearParrafo('text-xs uppercase tracking-widest text-white/50', titulo));
        lineas.forEach(function (linea) {
            seccion.appendChild(crearParrafo('mt-1 text-sm text-white/90', linea));
        });
        return seccion;
    }

    // Formulario "Avisame por correo" con los resultados del equipo seguido. Sin id conocido, se ofrecen solo los demás avisos.
    function crearSuscripcion(favorito) {
        const fragmento = plantillaSuscripcion.content.cloneNode(true);
        const equipo = equipos.find(function (candidato) {
            return candidato.nombre === favorito.nombre
                && (!favorito.idCategoria || String(candidato.idCategoria) === String(favorito.idCategoria));
        });
        const casilla = fragmento.querySelector('[data-rol="equipo-id"]');

        if (equipo) {
            casilla.value = equipo.id;
            fragmento.querySelector('[data-rol="equipo-nombre"]').textContent = equipo.nombre;
        } else {
            casilla.closest('label').remove();
        }

        return fragmento;
    }

    function renderCard(nombre, datos) {
        card.textContent = '';
        selector.classList.add('hidden');

        if (!datos.disponible) {
            const estado = document.createElement('div');
            estado.dataset.rol = 'no-disponible';
            estado.className = 'grid gap-4';
            estado.appendChild(crearParrafo('text-sm text-yellow-200/90', nombre + ' no participa en esta edición.'));
            const acciones = document.createElement('div');
            acciones.className = 'flex flex-wrap gap-2';
            acciones.appendChild(crearBotonAccion('Cambiar equipo', 'cambiar'));
            acciones.appendChild(crearBotonAccion('Quitar', 'quitar'));
            estado.appendChild(acciones);
            card.appendChild(estado);
            card.classList.remove('hidden');
            return;
        }

        const encabezado = document.createElement('div');
        encabezado.className = 'flex items-center gap-3';
        encabezado.appendChild(crearParrafo('text-lg font-bold text-white', nombre));
        card.appendChild(encabezado);

        if (datos.proximo) {
            const proximo = document.createElement('div');
            proximo.dataset.rol = 'proximo';
            proximo.className = 'mt-4';
            proximo.appendChild(crearSeccionPartido('Próximo partido', [
                (datos.proximo.esLocal ? 'Local' : 'Visitante') + ' vs ' + datos.proximo.rival,
                [datos.proximo.fecha_nombre, datos.proximo.horario, datos.proximo.cancha].filter(Boolean).join(' · '),
            ]));
            card.appendChild(proximo);
        }

        if (datos.ultimo) {
            const ultimo = document.createElement('div');
            ultimo.dataset.rol = 'ultimo';
            ultimo.className = 'mt-3';
            ultimo.appendChild(crearSeccionPartido('Último resultado', [
                (datos.ultimo.esLocal ? 'Local' : 'Visitante') + ' vs ' + datos.ultimo.rival,
                datos.ultimo.goles_favor + ' - ' + datos.ultimo.goles_contra,
            ]));
            card.appendChild(ultimo);
        }

        if (!datos.proximo && !datos.ultimo) {
            const vacio = document.createElement('div');
            vacio.dataset.rol = 'sin-partidos';
            vacio.className = 'mt-4';
            vacio.appendChild(crearParrafo('text-sm text-white/70', 'Todavía no hay partidos cargados para tu equipo.'));
            card.appendChild(vacio);
        }

        const links = document.createElement('div');
        links.className = 'mt-4 flex flex-wrap gap-2';
        if (datos.links && datos.links.fixture) {
            links.appendChild(crearEnlace(datos.links.fixture, 'Ver fixture del equipo'));
        }
        if (datos.links && datos.links.tabla) {
            links.appendChild(crearEnlace(datos.links.tabla, 'Tabla de posiciones'));
        }
        card.appendChild(links);

        const acciones = document.createElement('div');
        acciones.className = 'mt-4 flex flex-wrap gap-2';
        acciones.appendChild(crearBotonAccion('Cambiar equipo', 'cambiar'));
        acciones.appendChild(crearBotonAccion('Quitar', 'quitar'));
        card.appendChild(acciones);

        if (plantillaSuscripcion) {
            card.appendChild(crearSuscripcion(leerFavorito()));
        }

        card.classList.remove('hidden');
    }

    function mostrarSelector() {
        card.textContent = '';
        card.classList.add('hidden');
        selector.classList.remove('hidden');
        renderLista(buscador.value);
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
            // Enmienda #100: cada opción muestra "Nombre — Categoría" para
            // distinguir equipos homónimos de la misma edición.
            boton.dataset.idCategoria = equipo.idCategoria ?? '';
            boton.textContent = equipo.nombreCategoria
                ? equipo.nombre + ' — ' + equipo.nombreCategoria
                : equipo.nombre;
            boton.className = 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left text-sm font-medium text-white transition hover:border-emerald-300/70 hover:bg-emerald-400/10';
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

        selector.classList.add('hidden');
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
            buscador.value = '';
            mostrarSelector();
        }
        if (accion.dataset.accion === 'cambiar') {
            mostrarSelector();
            buscador.focus();
        }
    });

    buscador.addEventListener('input', function () {
        renderLista(buscador.value);
    });

    if (leerFavorito()) {
        cargarCard();
    } else {
        mostrarSelector();
    }
})();
