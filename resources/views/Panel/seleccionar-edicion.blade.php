@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Agregar equipo de una edición anterior"
        :subtitle="'Edición destino: ' . $EdicionSeleccionada->nombre . ' (ID ' . $EdicionSeleccionada->id . ')'" />

    <x-ui.form-card class="mt-6">
        <div>
            <label for="buscarEquipoGlobal" class="mb-1 block text-sm font-medium text-gray-700">Buscar equipo (todas las ediciones)</label>
            <div class="relative">
                <input id="buscarEquipoGlobal" type="text" placeholder="Escribe el nombre del equipo..." autocomplete="off"
                       class="block w-full rounded-md border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm focus:border-brand-navy focus:outline-none focus:ring-2 focus:ring-brand-navy/30" />
                <div id="resultadoBusqueda" class="absolute inset-x-0 z-10 mt-1 hidden max-h-72 divide-y divide-gray-100 overflow-auto rounded-md border border-gray-200 bg-white shadow-lg"></div>
            </div>
            <p class="mt-1 text-xs text-gray-500">Sugerencias ordenadas por edición (más reciente primero). Al elegir un equipo se completan la edición y la categoría de origen.</p>
        </div>

        <form action="{{ route($tipo . '.create') }}" method="GET" id="formSeleccion" class="space-y-5">
            <x-ui.select label="Edición origen" name="edicionfiltro" id="edicionfiltro">
                <option value="">-- Seleccionar edición origen --</option>
                @foreach ($ediciones as $e)
                    <option value="{{ $e->id }}" {{ request('edicionfiltro') == $e->id ? 'selected' : '' }}>
                        {{ $e->nombre }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Categoría origen" name="idCategoria" id="idCategoria" :disabled="! request('edicionfiltro')">
                <option value="">-- Seleccionar categoría --</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" {{ request('idCategoria') == $categoria->id ? 'selected' : '' }}>
                        {{ $categoria->nombreCategoria }}
                    </option>
                @endforeach
            </x-ui.select>

            <div class="flex justify-end">
                <x-ui.button type="submit" id="btnContinuar">Continuar</x-ui.button>
            </div>
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
        </form>
    </x-ui.form-card>
    <script>
        // --- Búsqueda global de equipos ---
        (function(){
            const input = document.getElementById('buscarEquipoGlobal');
            const cont = document.getElementById('resultadoBusqueda');
            const edicionSelect = document.getElementById('edicionfiltro');
            const categoriaSelect = document.getElementById('idCategoria');
            const form = document.getElementById('formSeleccion');
            const normalize = (s) => (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g,' ').trim();

            let timer;
            function limpiar(){ cont.innerHTML=''; cont.classList.add('hidden'); }
            function render(items){
                if(!items.length){ limpiar(); return; }
                cont.innerHTML = '';
                items.forEach(it => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'w-full text-left px-3 py-2 hover:bg-brand-teal/10 focus:bg-brand-teal/10 focus:outline-none';
                    row.innerHTML = `<div class="font-medium text-gray-900">${it.nombreEquipo}</div>
                                     <div class="text-xs text-gray-600">Edición: ${it.nombreEdicion} (ID ${it.idEdicion}) • Categoría: ${it.nombreCategoria}</div>`;
                    row.addEventListener('click', () => {
                        // Completar selects con la edición y categoría origen
                        edicionSelect.value = it.idEdicion;
                        edicionSelect.dispatchEvent(new Event('change'));
                        // Esperar a que carguen categorías y seleccionar
                        const pickCategoria = () => {
                            const opt = Array.from(categoriaSelect.options).find(o => o.value == it.idCategoria);
                            if(opt){
                                categoriaSelect.value = it.idCategoria;
                                categoriaSelect.dispatchEvent(new Event('change'));
                                // Enviar directo a la creación con preselección del equipo
                                const url = new URL(form.action, window.location.origin);
                                const params = new URLSearchParams(new FormData(form));
                                params.set('idEquipo', it.idEquipo);
                                window.location.href = url.toString() + '?' + params.toString();
                            } else {
                                // reintentar un poco si aún no cargó
                                setTimeout(pickCategoria, 120);
                            }
                        };
                        setTimeout(pickCategoria, 150);
                        limpiar();
                    });
                    cont.appendChild(row);
                });
                cont.classList.remove('hidden');
            }
            function buscar(q){
                if(normalize(q).length < 2){ limpiar(); return; }
                fetch(`/Panel/buscar-equipos?q=${encodeURIComponent(q)}&limit=20`)
                    .then(r => { if(!r.ok) throw new Error('error'); return r.json(); })
                    .then(data => render(data.items || []))
                    .catch(() => limpiar());
            }
            input.addEventListener('input', function(){
                clearTimeout(timer);
                const v = this.value;
                timer = setTimeout(() => buscar(v), 200);
            });
            document.addEventListener('click', (e)=>{
                if(!cont.contains(e.target) && e.target !== input){ limpiar(); }
            });
        })();

        const edicionSelect = document.getElementById('edicionfiltro');
        const categoriaSelect = document.getElementById('idCategoria');
        const btn = document.getElementById('btnContinuar');

        function evaluarEstado(){
            btn.disabled = !(edicionSelect.value && categoriaSelect.value);
        }

        edicionSelect.addEventListener('change', function(){
            const edicionId = this.value;
            categoriaSelect.innerHTML = '<option value="">Cargando...</option>';
            categoriaSelect.disabled = true;
            if(!edicionId){
                categoriaSelect.innerHTML = '<option value="">-- Seleccionar categoría --</option>';
                evaluarEstado();
                return;
            }
            fetch(`/Panel/categorias-por-edicion/${edicionId}`)
                .then(r => { if(!r.ok) throw new Error('Error'); return r.json(); })
                .then(data => {
                    categoriaSelect.innerHTML = '<option value="">-- Seleccionar categoría --</option>';
                    if(data.categorias?.length){
                        data.categorias.forEach(cat => {
                            const opt = document.createElement('option');
                            opt.value = cat.id; opt.textContent = cat.nombreCategoria; categoriaSelect.appendChild(opt);
                        });
                        categoriaSelect.disabled = false;
                    } else {
                        categoriaSelect.innerHTML = '<option value="">(Sin categorías)</option>';
                    }
                    evaluarEstado();
                })
                .catch(()=>{
                    categoriaSelect.innerHTML = '<option value="">Error al cargar</option>';
                    evaluarEstado();
                });
        });

        categoriaSelect.addEventListener('change', evaluarEstado);
        evaluarEstado();
    </script>
@endsection
