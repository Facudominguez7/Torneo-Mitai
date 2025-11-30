@csrf
<div class="mb-5">
    <label for="nombre" class="mb-3 block text-base font-medium text-white">Nombre de la Edición</label>
    <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $edicion->nombre) }}"
        class="w-full rounded-md border border-gray-300 bg-white py-2 px-4 text-base font-medium text-gray-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" />
</div>

@if (isset($equipos) && isset($categorias))
    <div class="mb-5">
        <label for="idEdicion" class="mb-3 block text-base font-medium text-white">Seleccione una edición</label>
        <select name="idEdicion" id="idEdicion"
            class="w-full rounded-md border border-gray-300 bg-white py-2 px-4 text-base font-medium text-gray-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <option value="">Seleccione una edición</option>
            @foreach ($ediciones as $edicion)
                <option value="{{ $edicion->id }}" {{ old('idEdicion', $idEdicion) == $edicion->id ? 'selected' : '' }}>
                    {{ $edicion->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-5" id="categorias-container" style="{{ $categorias->isEmpty() ? 'display:none;' : '' }}">
        <label for="idCategoria" class="mb-3 block text-base font-medium text-white">Seleccione una categoría</label>
        <select name="idCategoria" id="idCategoria"
            class="w-full rounded-md border border-gray-300 bg-white py-2 px-4 text-base font-medium text-gray-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <option value="">Seleccione una categoría</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" {{ old('idCategoria', $idCategoria) == $categoria->id ? 'selected' : '' }}>
                    {{ $categoria->nombreCategoria }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-5" id="equipos-container" style="{{ $equipos->isEmpty() ? 'display:none;' : '' }}">
        @foreach ($equipos as $equipo)
            <div class="flex items-center mb-2 p-4 bg-gray-800 rounded-lg shadow-md equipo"
                data-categoria-id="{{ $equipo->idCategoria }}">
                <input type="checkbox" name="equipos[]" value="{{ $equipo->id }}" id="equipo-{{ $equipo->id }}"
                    class="mr-2 h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" {{ in_array($equipo->id, old('equipos', [])) ? 'checked' : '' }}>
                <label for="equipo-{{ $equipo->id }}" class="text-white text-sm">{{ $equipo->nombre }}</label>
            </div>
        @endforeach
    </div>

    <!-- Sección para mostrar equipos seleccionados -->
    <div class="mb-5" id="equipos-seleccionados-container" style="display:none;">
        <label class="mb-3 block text-base font-medium text-white">Equipos Seleccionados</label>
        <div id="equipos-seleccionados-lista" class="bg-gray-900 rounded-lg p-4 max-h-72 overflow-y-auto text-sm">
            <!-- Dinámico -->
        </div>
        <div class="mt-2 flex gap-2">
            <button type="button" id="limpiar-seleccion"
                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm">Limpiar Selección</button>
        </div>
    </div>

    <script>
        // Estado
        let currentEdicionId = null;
        let currentEdicionNombre = null; // nombre de la edición origen actualmente seleccionada en el select
        const equiposData = {}; // info temporal de equipos cargados en la UI actual (por categoria)

        const STORAGE_KEY = 'selectedEquiposDetalle'; // Array de objetos {id, nombre, categoria, edicionId, edicionNombre}

        // Utilidades localStorage
        function getSeleccion() {
            try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch (e) { return []; }
        }
        function saveSeleccion(arr) { localStorage.setItem(STORAGE_KEY, JSON.stringify(arr)); }

        // Obtener nombre dinámico de la nueva edición a crear (input superior)
        function getNombreNuevaEdicion() {
            const v = (document.getElementById('nombre')?.value || '').trim();
            return v.length ? v : 'Nueva Edición';
        }

        // Renderizado de lista agrupada por CATEGORÍA
        function updateEquiposSeleccionados() {
            const lista = document.getElementById('equipos-seleccionados-lista');
            const cont = document.getElementById('equipos-seleccionados-container');
            const seleccion = getSeleccion();
            if (!seleccion.length) { cont.style.display = 'none'; lista.innerHTML = ''; return; }
            cont.style.display = 'block';
            lista.innerHTML = '';

            // Encabezado principal con nombre que se va escribiendo
            const header = document.createElement('div');
            header.className = 'mb-4 p-3 bg-indigo-600 rounded-lg';
            header.innerHTML = `<h4 class='text-white font-bold text-sm'>Agregando equipos a: "${getNombreNuevaEdicion()}"</h4><p class='text-indigo-200 text-xs'>Total equipos: ${seleccion.length}</p>`;
            lista.appendChild(header);

            // Agrupar por CATEGORIA
            const grupos = seleccion.reduce((acc, eq) => { (acc[eq.categoria] = acc[eq.categoria] || []).push(eq); return acc; }, {});

            Object.keys(grupos).sort().forEach(catNombre => {
                const catContainer = document.createElement('div');
                catContainer.className = 'mb-4 bg-gray-800 rounded p-2';

                // Título Categoría
                const titulo = document.createElement('div');
                titulo.className = 'mb-2 font-bold text-indigo-300 border-b border-gray-600 pb-1';
                titulo.textContent = catNombre;
                catContainer.appendChild(titulo);

                // Input para definir grupos
                const groupInputDiv = document.createElement('div');
                groupInputDiv.className = 'mb-2';
                // Usamos el nombre de la categoría como clave para el array de grupos
                groupInputDiv.innerHTML = `
                        <label class="text-xs text-gray-400 block mb-1">Crear Grupos (separados por coma):</label>
                        <input type="text" name="grupos[${catNombre}]" placeholder="Ej: Zona A, Zona B" class="w-full text-sm bg-gray-700 text-white border border-gray-600 rounded px-2 py-1 focus:outline-none focus:border-indigo-500">
                    `;
                catContainer.appendChild(groupInputDiv);

                // Lista de equipos
                grupos[catNombre].forEach(eq => {
                    const row = document.createElement('div');
                    row.className = 'flex items-center justify-between mb-1 p-1 pl-2 hover:bg-gray-700 rounded';
                    row.innerHTML = `
                            <div class="text-sm">
                                <span class='text-white'>${eq.nombre}</span> 
                                <span class='text-gray-500 text-xs'>(${eq.edicionNombre})</span>
                            </div>
                            <button type='button' class='text-red-400 hover:text-red-300 text-xs' data-remove='${eq.id}'>✕ Quitar</button>
                        `;
                    catContainer.appendChild(row);
                });

                lista.appendChild(catContainer);
            });
        }

        // Añadir / quitar selección al click checkbox (con validación: misma categoría solo bloquea si mismo nombre en otra edición)
        document.addEventListener('change', e => {
            if (e.target.name === 'equipos[]') {
                const id = e.target.value;
                let seleccion = getSeleccion();
                if (e.target.checked) {
                    if (!seleccion.find(x => x.id == id)) {
                        const info = equiposData[id];
                        if (info) {
                            // Nuevo criterio: solo conflicto si misma categoría + mismo nombre (case-insensitive) en distinta edición
                            const conflicto = seleccion.find(x => x.categoria === info.categoria && x.edicionId !== info.edicionId && x.nombre.toLowerCase() === info.nombre.toLowerCase());
                            if (conflicto) {
                                alert(`Ya seleccionaste un equipo con nombre "${conflicto.nombre}" en la categoría "${info.categoria}" (edición: ${conflicto.edicionNombre}). No puedes repetir mismo nombre en la misma categoría aunque sea de otra edición.`);
                                e.target.checked = false;
                                return;
                            }
                            seleccion.push(info);
                            saveSeleccion(seleccion);
                        }
                    }
                } else {
                    seleccion = seleccion.filter(x => x.id != id);
                    saveSeleccion(seleccion);
                }
                updateEquiposSeleccionados();
            }
        });

        // Quitar desde lista (delegación)
        document.addEventListener('click', e => {
            if (e.target.matches('[data-remove]')) {
                const id = e.target.getAttribute('data-remove');
                let seleccion = getSeleccion().filter(x => x.id != id);
                saveSeleccion(seleccion);
                // Desmarcar checkbox si está en DOM
                const cb = document.getElementById('equipo-' + id);
                if (cb) cb.checked = false;
                updateEquiposSeleccionados();
            }
        });

        // Botón limpiar
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('limpiar-seleccion')?.addEventListener('click', () => {
                if (confirm('¿Limpiar toda la selección?')) {
                    localStorage.removeItem(STORAGE_KEY);
                    document.querySelectorAll('input[name="equipos[]"]').forEach(cb => cb.checked = false);
                    updateEquiposSeleccionados();
                }
            });
        });

        // Actualizar encabezado en vivo al escribir nombre nueva edición
        document.addEventListener('input', e => {
            if (e.target.id === 'nombre') updateEquiposSeleccionados();
        });

        // Cambio de edición origen
        document.getElementById('idEdicion').addEventListener('change', function () {
            currentEdicionId = this.value || null;
            currentEdicionNombre = this.value ? this.options[this.selectedIndex].text.trim() : null;
            if (currentEdicionId) {
                fetch(`/Panel/categorias-por-edicion/${currentEdicionId}`)
                    .then(r => { if (!r.ok) throw new Error('Error al cargar las categorías'); return r.json(); })
                    .then(data => {
                        const sel = document.getElementById('idCategoria');
                        sel.innerHTML = '<option value="">Seleccione una categoría</option>';
                        if (data.categorias?.length) {
                            data.categorias.forEach(cat => {
                                const opt = document.createElement('option');
                                opt.value = cat.id; opt.textContent = cat.nombreCategoria; sel.appendChild(opt);
                            });
                            document.getElementById('categorias-container').style.display = 'block';
                        } else {
                            alert('No se encontraron categorías para esta edición');
                        }
                    })
                    .catch(err => { console.error(err); alert('Error al cargar las categorías'); });
            } else {
                document.getElementById('categorias-container').style.display = 'none';
                document.getElementById('equipos-container').style.display = 'none';
            }
            updateEquiposSeleccionados();
        });

        // Cambio de categoría (carga equipos)
        document.getElementById('idCategoria').addEventListener('change', function () {
            const categoriaId = this.value;
            if (categoriaId) {
                fetch(`/Panel/equipos-por-categoria/${categoriaId}`)
                    .then(r => { if (!r.ok) throw new Error('Error al cargar los equipos'); return r.json(); })
                    .then(data => {
                        const cont = document.getElementById('equipos-container');
                        cont.innerHTML = '';
                        if (data.equipos?.length) {
                            data.equipos.forEach(eq => {
                                // Guardar info extendida
                                equiposData[eq.id] = {
                                    id: eq.id,
                                    nombre: eq.nombre,
                                    categoria: data.categoria || 'Categoría',
                                    edicionId: currentEdicionId,
                                    edicionNombre: currentEdicionNombre || 'Edición Origen'
                                };
                                const div = document.createElement('div');
                                div.className = 'flex items-center mb-2 p-4 bg-gray-800 rounded-lg shadow-md';
                                div.innerHTML = `<input type=\"checkbox\" name=\"equipos[]\" value=\"${eq.id}\" id=\"equipo-${eq.id}\" class=\"mr-2 h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500\"> <label for=\"equipo-${eq.id}\" class=\"text-white text-sm\">${eq.nombre}</label>`;
                                cont.appendChild(div);
                            });
                            // Restaurar checks ya seleccionados
                            const seleccion = getSeleccion();
                            seleccion.forEach(s => {
                                const cb = document.getElementById('equipo-' + s.id);
                                if (cb) cb.checked = true;
                            });
                            cont.style.display = 'block';
                        } else {
                            alert('No se encontraron equipos para esta categoría');
                        }
                    })
                    .catch(err => { console.error(err); alert('Error al cargar los equipos'); });
            } else {
                document.getElementById('equipos-container').style.display = 'none';
            }
        });

        // Restaurar al cargar
        document.addEventListener('DOMContentLoaded', () => {
            // Restaurar selección (no depende ya de edición origen)
            updateEquiposSeleccionados();
        });

        // Envío formulario: agregar inputs hidden con todos los IDs seleccionados
        document.querySelector('form').addEventListener('submit', function () {
            const seleccion = getSeleccion();
            // Evitar duplicados de hidden
            this.querySelectorAll('input[name="equipos[]"][type="hidden"]').forEach(n => n.remove());
            seleccion.forEach(eq => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'equipos[]';
                hidden.value = eq.id;
                this.appendChild(hidden);
            });
            // Limpiar storage tras enviar
            localStorage.removeItem(STORAGE_KEY);
        });
    </script>
@endif