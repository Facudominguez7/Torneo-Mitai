@extends('Panel.admin')

@section('contenido')
    <div class="mb-5">
        <div class="mb-6">
            <label for="buscarEquipoGlobal" class="block text-base font-medium text-white mb-2">Buscar equipo (todas las ediciones)</label>
            <input id="buscarEquipoGlobal" type="text" placeholder="Escribe el nombre del equipo..."
                   class="w-full rounded-md border border-gray-200 bg-white py-3 px-4 text-base font-medium text-gray-700 outline-none focus:border-indigo-500 focus:shadow-md" />
            <div id="resultadoBusqueda" class="mt-2 bg-white rounded-md shadow divide-y max-h-72 overflow-auto hidden"></div>
            <p class="text-xs text-indigo-200 mt-1">Sugerencias ordenadas por edición (más reciente primero).</p>
        </div>
        <form action="{{ route($tipo . '.create') }}" method="GET" id="formSeleccion">
            <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada->id }}">
            <label class="block text-base font-medium text-white mb-1">Edición destino</label>
            <p class="text-sm text-indigo-200 mb-4">{{ $EdicionSeleccionada->nombre }} (ID {{ $EdicionSeleccionada->id }})</p>

            <label for="edicionfiltro" class="block text-base font-medium text-white mb-2">Edición origen</label>
            <select name="edicionfiltro" id="edicionfiltro"
                class="w-full rounded-md border border-gray-200 bg-white py-3 px-6 text-base font-medium text-gray-600 outline-none focus:border-indigo-500 focus:shadow-md mb-4">
                <option value="">-- Seleccionar edición origen --</option>
                @foreach ($ediciones as $e)
                    <option value="{{ $e->id }}" {{ request('edicionfiltro') == $e->id ? 'selected' : '' }}>
                        {{ $e->nombre }}
                    </option>
                @endforeach
            </select>

            <label for="idCategoria" class="block text-base font-medium text-white mb-2">Categoría origen</label>
            <select name="idCategoria" id="idCategoria"
                class="w-full rounded-md border border-gray-200 bg-white py-3 px-6 text-base font-medium text-gray-600 outline-none focus:border-indigo-500 focus:shadow-md mb-4" {{ request('edicionfiltro') ? '' : 'disabled' }}>
                <option value="">-- Seleccionar categoría --</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" {{ request('idCategoria') == $categoria->id ? 'selected' : '' }}>
                        {{ $categoria->nombreCategoria }}
                    </option>
                @endforeach
            </select>
            <div class="flex justify-center mt-4">
                <button type="submit" id="btnContinuar"
                    class="bg-gray-800 hover:bg-gray-900 disabled:opacity-40 disabled:cursor-not-allowed text-white py-2 px-6 rounded-full transition-all duration-300">
                    Continuar
                </button>
            </div>
        </form>
    </div>
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
                    row.className = 'w-full text-left px-3 py-2 hover:bg-indigo-50';
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
