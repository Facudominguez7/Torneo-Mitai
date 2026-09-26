@include('head')
@include('components.loader')

<body class="bg-[--color-primary]  font-[Poppins,sans-serif]">
    @php
        /**
         * Contexto de edición activo para mantener la navegación consistente.
         */
        $parametrosEdicionActual = $idEdicionActual ? ['idEdicion' => $idEdicionActual] : [];
    @endphp
    <div id="sidebar-backdrop" class="pointer-events-none fixed inset-0 z-30 bg-slate-900/50 opacity-0 transition-opacity duration-200 lg:hidden"></div>
    <aside id="sidebar-multi-level-sidebar"
        class="fixed top-0 left-0 z-40 h-screen w-64 -translate-x-full overflow-y-auto shadow-xl transition-transform lg:translate-x-0 lg:shadow-none"
        aria-label="Sidebar">
        <div class="h-full overflow-y-auto bg-gray-50 px-3 py-4 pb-24">
            <ul class="space-y-2 font-medium">
                <li>
                    <a href="{{ route('home') }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <svg class="w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                            viewBox="0 0 22 21">
                            <path
                                d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z" />
                            <path
                                d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z" />
                        </svg>
                        <span class="ms-3">Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <svg class="w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                            viewBox="0 0 22 21">
                            <path
                                d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z" />
                            <path
                                d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z" />
                        </svg>
                        <span class="ms-3">Panel de Administración</span>
                    </a>
                </li>

                {{-- Gestión del Torneo --}}
                <li class="pt-4 mt-4 border-t border-gray-200">
                    <span class="px-2 text-xs font-semibold text-gray-500 uppercase">Gestión</span>
                </li>

                <li>
                    <a href="{{ route('edicion.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Calendario (representa ediciones/años) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                            <path
                                d="M152 24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H64C28.7 64 0 92.7 0 128v16 48V448c0 35.3 28.7 64 64 64H384c35.3 0 64-28.7 64-64V192 144 128c0-35.3-28.7-64-64-64H344V24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H152V24zM48 192H400V448c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Ediciones (ABM)</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('categoria.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Etiquetas (representa categorías/clasificaciones) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                            <path
                                d="M345 39.1L472.8 168.4c52.4 52.4 52.4 137.4 0 189.8L360.6 470.4c-52.4 52.4-137.4 52.4-189.8 0L41.1 340.5c-9.7-9.7-15.9-22.7-17-36.7L8 165.4c-2.8-35.7 25.9-65.7 61.6-65.7H241.3c13.8 0 27 5.1 37 14.3l66.7 66.7c6.2 6.2 6.2 16.4 0 22.6s-16.4 6.2-22.6 0l-66.7-66.7c-3.1-3.1-7.2-4.7-11.6-4.7H69.6c-11.9 0-21.4 10-20.5 21.9l16.1 138.5c.4 4.7 2.4 9 5.6 12.3L199 468.6c33.6 33.6 88.1 33.6 121.7 0L432.9 356.4c33.6-33.6 33.6-88.1 0-121.7L304.6 106.8 345 39.1zM144 208a32 32 0 1 0 -64 0 32 32 0 1 0 64 0z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Categorias</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('equipo.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Usuarios/Grupo (representa equipos) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" fill="currentColor">
                            <path
                                d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192h42.7c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0H21.3C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7h42.7C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3H405.3zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352H378.7C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7H154.7c-14.7 0-26.7-11.9-26.7-26.7z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Equipos</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('instancia_final.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Trofeo (representa instancias finales/playoffs) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                            <path
                                d="M400 0H176c-26.5 0-48.1 21.8-47.1 48.2c.2 5.3 .4 10.6 .7 15.8H24C10.7 64 0 74.7 0 88c0 92.6 33.5 157 78.5 200.7c44.3 43.1 98.3 64.8 138.1 75.8c23.4 6.5 39.4 26 39.4 45.6c0 20.9-17 37.9-37.9 37.9H192c-17.7 0-32 14.3-32 32s14.3 32 32 32H384c17.7 0 32-14.3 32-32s-14.3-32-32-32H357.9C337 448 320 431 320 410.1c0-19.6 15.9-39.2 39.4-45.6c39.9-11 93.9-32.7 138.1-75.8C542.5 245 576 180.6 576 88c0-13.3-10.7-24-24-24H446.4c.3-5.2 .5-10.4 .7-15.8C448.1 21.8 426.5 0 400 0zM48.9 112h84.4c9.1 90.1 29.2 150.3 51.9 190.6c-24.9-11-48.8-27.1-67.5-45.3C76.1 218.1 52 165.1 48.9 112zM464.1 257.3c-18.7 18.2-42.7 34.3-67.5 45.3c22.7-40.3 42.8-100.5 51.9-190.6h84.4c-3.1 53.1-27.2 106.1-68.8 145.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Instancias Finales</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('lista-buena-fe.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                            <path fill="currentColor"
                                d="M192 0c-41.8 0-77.4 26.7-90.5 64L64 64C28.7 64 0 92.7 0 128L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64l-37.5 0C269.4 26.7 233.8 0 192 0zm0 64a32 32 0 1 1 0 64 32 32 0 1 1 0-64zM112 192l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zM96 272c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16zm16 48l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16s7.2-16 16-16z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Lista de Buena Fe</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('lista-buena-fe.conflictivos') }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Portapapeles con check (representa revisión/validación) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-orange-500 transition duration-75 group-hover:text-orange-600"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor">
                            <path
                                d="M192 0c-41.8 0-77.4 26.7-90.5 64L64 64C28.7 64 0 92.7 0 128L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64l-37.5 0C269.4 26.7 233.8 0 192 0zm0 64a32 32 0 1 1 0 64 32 32 0 1 1 0-64zM305 273L177 401c-9.4 9.4-24.6 9.4-33.9 0L79 337c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L271 239c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Revisión de Jugadores</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('jugador.consulta', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path
                                d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Consulta de Jugador</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('institucion.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512">
                            <path fill="currentColor"
                                d="M575.8 255.5c0 18-15 32.1-32 32.1l-32 0 .7 160.2c0 2.7-.2 5.4-.5 8.1l0 16.2c0 22.1-17.9 40-40 40l-16 0c-1.1 0-2.2 0-3.3-.1c-1.4 .1-2.8 .1-4.2 .1L416 512l-24 0c-22.1 0-40-17.9-40-40l0-24 0-64c0-17.7-14.3-32-32-32l-64 0c-17.7 0-32 14.3-32 32l0 64 0 24c0 22.1-17.9 40-40 40l-24 0-31.9 0c-1.5 0-3-.1-4.5-.2c-1.2 .1-2.4 .2-3.6 .2l-16 0c-22.1 0-40-17.9-40-40l0-112c0-.9 0-1.9 .1-2.8l0-69.7-32 0c-18 0-32-14-32-32.1c0-9 3-17 10-24L266.4 8c7-7 15-8 22-8s15 2 21 7L564.8 231.5c8 7 12 15 11 24z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Gestión de Instituciones</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('grupos.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Cuadrícula (representa grupos/zonas) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                            <path
                                d="M0 72C0 49.9 17.9 32 40 32H88c22.1 0 40 17.9 40 40v48c0 22.1-17.9 40-40 40H40c-22.1 0-40-17.9-40-40V72zM0 232c0-22.1 17.9-40 40-40H88c22.1 0 40 17.9 40 40v48c0 22.1-17.9 40-40 40H40c-22.1 0-40-17.9-40-40V232zM128 392v48c0 22.1-17.9 40-40 40H40c-22.1 0-40-17.9-40-40V392c0-22.1 17.9-40 40-40H88c22.1 0 40 17.9 40 40zM160 72c0-22.1 17.9-40 40-40h48c22.1 0 40 17.9 40 40v48c0 22.1-17.9 40-40 40H200c-22.1 0-40-17.9-40-40V72zM288 232v48c0 22.1-17.9 40-40 40H200c-22.1 0-40-17.9-40-40V232c0-22.1 17.9-40 40-40h48c22.1 0 40 17.9 40 40zM160 392c0-22.1 17.9-40 40-40h48c22.1 0 40 17.9 40 40v48c0 22.1-17.9 40-40 40H200c-22.1 0-40-17.9-40-40V392zM360 72c0-22.1 17.9-40 40-40h48c22.1 0 40 17.9 40 40v48c0 22.1-17.9 40-40 40H400c-22.1 0-40-17.9-40-40V72zM448 232v48c0 22.1-17.9 40-40 40H360c-22.1 0-40-17.9-40-40V232c0-22.1 17.9-40 40-40h48c22.1 0 40 17.9 40 40zM320 392c0-22.1 17.9-40 40-40h48c22.1 0 40 17.9 40 40v48c0 22.1-17.9 40-40 40H360c-22.1 0-40-17.9-40-40V392z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Grupos</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('fecha.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Calendario con día (representa fechas de juego) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                            <path
                                d="M128 0c17.7 0 32 14.3 32 32V64H288V32c0-17.7 14.3-32 32-32s32 14.3 32 32V64h48c26.5 0 48 21.5 48 48v48H0V112C0 85.5 21.5 64 48 64H96V32c0-17.7 14.3-32 32-32zM0 192H448V464c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V192zm64 80v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H80c-8.8 0-16 7.2-16 16zm128 0v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H208c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V272c0-8.8-7.2-16-16-16H336zM64 400v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H80c-8.8 0-16 7.2-16 16zm144-16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H208zm112 16v32c0 8.8 7.2 16 16 16h32c8.8 0 16-7.2 16-16V400c0-8.8-7.2-16-16-16H336c-8.8 0-16 7.2-16 16z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Fechas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('copa.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Copa/Trofeo (representa copas del torneo) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                            <path
                                d="M400 0H176c-26.5 0-48.1 21.8-47.1 48.2c.2 5.3 .4 10.6 .7 15.8H24C10.7 64 0 74.7 0 88c0 92.6 33.5 157 78.5 200.7c44.3 43.1 98.3 64.8 138.1 75.8c23.4 6.5 39.4 26 39.4 45.6c0 20.9-17 37.9-37.9 37.9H192c-17.7 0-32 14.3-32 32s14.3 32 32 32H384c17.7 0 32-14.3 32-32s-14.3-32-32-32H357.9C337 448 320 431 320 410.1c0-19.6 15.9-39.2 39.4-45.6c39.9-11 93.9-32.7 138.1-75.8C542.5 245 576 180.6 576 88c0-13.3-10.7-24-24-24H446.4c.3-5.2 .5-10.4 .7-15.8C448.1 21.8 426.5 0 400 0zM48.9 112h84.4c9.1 90.1 29.2 150.3 51.9 190.6c-24.9-11-48.8-27.1-67.5-45.3C76.1 218.1 52 165.1 48.9 112zM464.1 257.3c-18.7 18.2-42.7 34.3-67.5 45.3c22.7-40.3 42.8-100.5 51.9-190.6h84.4c-3.1 53.1-27.2 106.1-68.8 145.3z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Copas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('fase.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Escalera/Niveles (representa fases del torneo) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                            <path
                                d="M384 64c0-17.7 14.3-32 32-32H544c17.7 0 32 14.3 32 32s-14.3 32-32 32H448v64c0 17.7-14.3 32-32 32H320v64c0 17.7-14.3 32-32 32H192v64c0 17.7-14.3 32-32 32H32c-17.7 0-32-14.3-32-32s14.3-32 32-32h96V256c0-17.7 14.3-32 32-32h96V160c0-17.7 14.3-32 32-32h96V64z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Fases</span>
                    </a>
                </li>

                {{-- Estadísticas y Resultados --}}
                <li class="pt-4 mt-4 border-t border-gray-200">
                    <span class="px-2 text-xs font-semibold text-gray-500 uppercase">Estadísticas</span>
                </li>

                <li>
                    <a href="{{ route('tabla_goleador.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Balón de fútbol (representa goleadores) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                            <path
                                d="M417.3 360.1l-71.6-4.8c-5.2-.3-10.3 1.1-14.5 4.2s-7.2 7.4-8.4 12.5l-17.6 69.6C289.5 445.8 273 448 256 448s-33.5-2.2-49.2-6.4l-17.6-69.6c-1.3-5.1-4.4-9.5-8.4-12.5s-9.3-4.5-14.5-4.2l-71.6 4.8c-17.6-27.2-28.5-59.2-30.4-93.6l57.8-43.4c4.2-3.1 7.2-7.4 8.4-12.5s.8-10.5-1.4-15.1L98.1 142.8c18.3-25.5 43.1-45.8 72.3-58.8l58.1 37.9c4.3 2.8 9.4 4.2 14.5 4.2s10.2-1.4 14.5-4.2l58.1-37.9c29.2 13 54 33.3 72.3 58.8l-30.9 54.1c-2.2 4.6-2.7 9.9-1.4 15.1s4.2 9.5 8.4 12.5l57.8 43.4c-1.9 34.4-12.8 66.4-30.4 93.6zM256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zm14.1-325.7c-8.4-6.1-19.8-6.1-28.2 0L194 221c-8.4 6.1-11.9 16.9-8.7 26.8l18.3 56.3c3.2 9.9 12.4 16.6 22.8 16.6h59.2c10.4 0 19.6-6.7 22.8-16.6l18.3-56.3c3.2-9.9-.3-20.7-8.7-26.8l-47.9-34.8z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Tabla de Goleadores</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('tabla_posicion.index', array_filter([
                        'idEdicion' => $idEdicionActual,
                        'idCategoria' => request('idCategoria'),
                        'idGrupo' => request('idGrupo'),
                    ], static fn ($valor) => $valor !== null && $valor !== '')) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        <svg class="flex-shrink-0 w-5 h-5 text-emerald-500 transition duration-75 group-hover:text-emerald-600"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                            <path
                                d="M32 32h128v128H32V32zm160 0h128v128H192V32zm160 0h128v128H352V32zM32 192h128v128H32V192zm160 0h128v128H192V192zm160 0h128v128H352V192zM32 352h128v128H32V352zm160 0h128v128H192V352zm160 0h128v128H352V352z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Tabla de Posiciones</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('estadisticas-jugadores', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Gráfico de barras (representa estadísticas) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-indigo-500 transition duration-75 group-hover:text-indigo-600"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                            <path
                                d="M160 80c0-26.5 21.5-48 48-48l32 0c26.5 0 48 21.5 48 48l0 352c0 26.5-21.5 48-48 48l-32 0c-26.5 0-48-21.5-48-48l0-352zM0 272c0-26.5 21.5-48 48-48l32 0c26.5 0 48 21.5 48 48l0 160c0 26.5-21.5 48-48 48l-32 0c-26.5 0-48-21.5-48-48L0 272zM368 96l32 0c26.5 0 48 21.5 48 48l0 288c0 26.5-21.5 48-48 48l-32 0c-26.5 0-48-21.5-48-48l0-288c0-26.5 21.5-48 48-48z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Estadísticas de Jugadores</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('campeon.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Medalla (representa campeones - color dorado) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-yellow-500 transition duration-75 group-hover:text-yellow-600"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                            <path
                                d="M4.1 38.2C1.4 34.2 0 29.4 0 24.6C0 11 11 0 24.6 0H133.9c11.2 0 21.7 5.9 27.4 15.5l68.5 114.1c-48.2 6.1-91.3 28.6-123.4 61.9L4.1 38.2zm503.7 0L405.6 191.5c-32.1-33.3-75.2-55.8-123.4-61.9L350.7 15.5C356.5 5.9 366.9 0 378.1 0H487.4C501 0 512 11 512 24.6c0 4.8-1.4 9.6-4.1 13.6zM80 336a176 176 0 1 1 352 0A176 176 0 1 1 80 336zm184.4-94.9c-3.4-7-13.3-7-16.8 0l-22.4 45.4c-1.4 2.8-4 4.7-7 5.1L168 299.9c-7.7 1.1-10.7 10.5-5.2 16l36.3 35.4c2.2 2.2 3.2 5.2 2.7 8.3l-8.6 49.9c-1.3 7.6 6.7 13.5 13.6 9.9l44.8-23.6c2.7-1.4 6-1.4 8.7 0l44.8 23.6c6.9 3.6 14.9-2.2 13.6-9.9l-8.6-49.9c-.5-3 .5-6.1 2.7-8.3l36.3-35.4c5.6-5.4 2.5-14.8-5.2-16l-50.1-7.3c-3-.4-5.7-2.4-7-5.1l-22.4-45.4z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Campeones</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('subcampeon.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Medalla (representa subcampeones - color plateado) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400 transition duration-75 group-hover:text-gray-500"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                            <path
                                d="M4.1 38.2C1.4 34.2 0 29.4 0 24.6C0 11 11 0 24.6 0H133.9c11.2 0 21.7 5.9 27.4 15.5l68.5 114.1c-48.2 6.1-91.3 28.6-123.4 61.9L4.1 38.2zm503.7 0L405.6 191.5c-32.1-33.3-75.2-55.8-123.4-61.9L350.7 15.5C356.5 5.9 366.9 0 378.1 0H487.4C501 0 512 11 512 24.6c0 4.8-1.4 9.6-4.1 13.6zM80 336a176 176 0 1 1 352 0A176 176 0 1 1 80 336zm184.4-94.9c-3.4-7-13.3-7-16.8 0l-22.4 45.4c-1.4 2.8-4 4.7-7 5.1L168 299.9c-7.7 1.1-10.7 10.5-5.2 16l36.3 35.4c2.2 2.2 3.2 5.2 2.7 8.3l-8.6 49.9c-1.3 7.6 6.7 13.5 13.6 9.9l44.8-23.6c2.7-1.4 6-1.4 8.7 0l44.8 23.6c6.9 3.6 14.9-2.2 13.6-9.9l-8.6-49.9c-.5-3 .5-6.1 2.7-8.3l36.3-35.4c5.6-5.4 2.5-14.8-5.2-16l-50.1-7.3c-3-.4-5.7-2.4-7-5.1l-22.4-45.4z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> SubCampeones</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('goleador.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Estrella (representa goleadores destacados del torneo) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                            <path
                                d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.4 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.1 150.3 316.9 18z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Goleadores del Torneo</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('valla.index', $parametrosEdicionActual) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Escudo (representa valla menos vencida/defensa) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                            <path
                                d="M256 0c4.6 0 9.2 1 13.4 2.9L457.7 82.8c22 9.3 38.4 31 38.3 57.2c-.5 99.2-41.3 280.7-213.6 363.2c-16.7 8-36.1 8-52.8 0C57.3 420.7 16.5 239.2 16 140c-.1-26.2 16.3-47.9 38.3-57.2L242.7 2.9C246.8 1 251.4 0 256 0zm0 66.8V444.8C394 378 431.1 230.1 432 141.4L256 66.8l0 0z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Vallas Menos Vencidas</span>
                    </a>
                </li>

            </ul>

             <!-- Gestión de notificaciones por email -->
            <div class="px-3 py-4 border-t border-gray-300">
                <div class="mb-2">
                    <span class="px-2 text-xs font-semibold text-gray-500 uppercase">Notificaciones</span>
                </div>
                 <a href="{{ route('notificaciones.index') }}"
                    class="flex items-center w-full px-4 py-2 mb-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors font-medium text-sm">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                    </svg>
                    Ver Suscriptores
                </a>
                <p class="mt-2 text-xs text-gray-500 text-center">
                    Recibe alertas de resultados y partidos
                </p>
            </div>

        </div>
    </aside>
    @php
        /**
         * Header persistente: muestra la edición activa o un estado vacío accesible.
         * El selector navega a `admin?idEdicion` exclusivamente para reiniciar filtros.
         */
        $edicionActivaHeader = $EdicionSeleccionada ?? null;
    @endphp
    <header data-panel-edicion-header
        class="fixed inset-x-0 top-0 z-30 flex flex-col justify-center gap-2 border-b border-slate-200 bg-white/90 px-3 py-2 text-slate-700 shadow-sm backdrop-blur-sm sm:px-4 lg:left-64 lg:h-16 lg:flex-row lg:items-center lg:gap-5 lg:px-6 lg:py-0">
        <div data-panel-header-primary-row class="flex w-full min-w-0 items-center justify-between gap-2 lg:w-auto lg:flex-1">
            <div class="flex min-w-0 items-center gap-2">
                <button data-drawer-target="sidebar-multi-level-sidebar" data-drawer-toggle="sidebar-multi-level-sidebar"
                    aria-controls="sidebar-multi-level-sidebar" type="button" aria-expanded="false"
                    class="inline-flex flex-shrink-0 items-center rounded-lg p-2 text-sm text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300 lg:hidden">
                    <span class="sr-only">Abrir menú</span>
                    <svg class="h-6 w-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" fill-rule="evenodd"
                            d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z">
                        </path>
                    </svg>
                </button>
                <span class="whitespace-nowrap text-sm font-bold tracking-wide text-slate-800 sm:text-base">Mitaí Admin</span>
            </div>
        </div>
        <form data-panel-header-edition-row data-panel-edicion-form action="{{ route('admin') }}" method="GET"
            class="flex w-full min-w-0 items-center gap-2 lg:w-auto lg:flex-shrink-0">
            <label for="panel-edicion-select" class="whitespace-nowrap text-xs font-semibold uppercase tracking-wide text-slate-500">Edición</label>
            <select name="idEdicion" id="panel-edicion-select"
                class="h-10 min-w-0 max-w-full flex-1 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800 shadow-sm outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200 lg:w-72 lg:flex-none">
                @if (! $edicionActivaHeader)
                    <option value="" selected>Sin edición seleccionada</option>
                @endif
                @foreach ($ediciones as $edicionHeader)
                    <option value="{{ $edicionHeader->id }}" {{ $edicionActivaHeader?->id === $edicionHeader->id ? 'selected' : '' }}>
                        {{ $edicionHeader->nombre }}
                    </option>
                @endforeach
            </select>
            <button type="submit"
                class="inline-flex h-10 flex-shrink-0 items-center rounded-lg border border-slate-200 bg-slate-100 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-300 sm:text-sm">
                Cambiar
            </button>
        </form>
    </header>
    <div class="relative flex min-h-screen w-full flex-col overflow-x-hidden pt-32 pb-6 lg:pl-64 lg:pt-20">
        @php
            $nombreRutaActual = request()->route()?->getName() ?? '';
            $rutaActualPath = request()->path();

            $idEdicionContexto = $idEdicionActual;
            $modulosContexto = [
                'admin' => [
                    'label' => 'Panel admin',
                    'route' => route('admin', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'panel',
                ],
                'partido' => [
                    'label' => 'Partidos',
                    'route' => route('partido.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'competencia',
                ],
                'grupos' => [
                    'label' => 'Grupos',
                    'route' => route('grupos.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'competencia',
                ],
                'instancia_final' => [
                    'label' => 'Instancias finales',
                    'route' => route('instancia_final.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'competencia',
                ],
                'fecha' => [
                    'label' => 'Fechas',
                    'route' => route('fecha.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'competencia',
                ],
                'edicion' => [
                    'label' => 'Ediciones',
                    'route' => route('edicion.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'categoria' => [
                    'label' => 'Categorias',
                    'route' => route('categoria.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'equipo' => [
                    'label' => 'Equipos',
                    'route' => route('equipo.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'equipoedicion' => [
                    'label' => 'Equipos por edicion',
                    'route' => route('equipoedicion.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'notificaciones' => [
                    'label' => 'Notificaciones',
                     'route' => route('notificaciones.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'lista_buena_fe' => [
                    'label' => 'Lista de Buena Fe',
                    'route' => route('lista-buena-fe.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'jugador_consulta' => [
                    'label' => 'Consulta de Jugador',
                    'route' => route('jugador.consulta', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'institucion' => [
                    'label' => 'Instituciones',
                    'route' => route('institucion.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'copa' => [
                    'label' => 'Copas',
                    'route' => route('copa.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'fase' => [
                    'label' => 'Fases',
                    'route' => route('fase.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'gestion',
                ],
                'campeon' => [
                    'label' => 'Campeones',
                    'route' => route('campeon.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'estadisticas',
                ],
                'subcampeon' => [
                    'label' => 'Subcampeones',
                    'route' => route('subcampeon.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'estadisticas',
                ],
                'goleador' => [
                    'label' => 'Goleadores',
                    'route' => route('goleador.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'estadisticas',
                ],
                'tabla_posicion' => [
                    'label' => 'Tabla de Posiciones',
                    'route' => route('tabla_posicion.index', array_filter([
                        'idEdicion' => $idEdicionContexto,
                        'idCategoria' => request('idCategoria'),
                        'idGrupo' => request('idGrupo'),
                    ], static fn ($valor) => $valor !== null && $valor !== '')),
                    'grupo' => 'estadisticas',
                ],
                'valla' => [
                    'label' => 'Vallas',
                    'route' => route('valla.index', ['idEdicion' => $idEdicionContexto]),
                    'grupo' => 'estadisticas',
                ],
            ];

            $tipoSolicitado = request('tipo');
            $aliasTipo = [
                'equipogrupo' => 'grupos',
                 'notificaciones.index' => 'notificaciones',
                'lista-buena-fe' => 'lista_buena_fe',
                'jugador.consulta' => 'jugador_consulta',
            ];
            $tipoSolicitado = $aliasTipo[$tipoSolicitado] ?? $tipoSolicitado;

            $tipoContextoActual = null;
            $deteccionesRuta = [
                ['patterns' => ['admin', 'admin.*'], 'modulo' => 'admin'],
                ['patterns' => ['partido.*', 'planilla.*', 'cargar-resultado'], 'modulo' => 'partido'],
                ['patterns' => ['instancia_final', 'instancia_final.*', 'cargar-resultado-instancia'], 'modulo' => 'instancia_final'],
                ['patterns' => ['grupos.*', 'equipogrupo.*'], 'modulo' => 'grupos'],
                ['patterns' => ['fecha.*'], 'modulo' => 'fecha'],
                ['patterns' => ['edicion.*'], 'modulo' => 'edicion'],
                ['patterns' => ['categoria.*'], 'modulo' => 'categoria'],
                ['patterns' => ['equipo.*'], 'modulo' => 'equipo'],
                ['patterns' => ['equipoedicion.*'], 'modulo' => 'equipoedicion'],
                 ['patterns' => ['notificaciones.*'], 'modulo' => 'notificaciones'],
                ['patterns' => ['lista-buena-fe.*'], 'modulo' => 'lista_buena_fe'],
                ['patterns' => ['jugador.*'], 'modulo' => 'jugador_consulta'],
                ['patterns' => ['institucion.*'], 'modulo' => 'institucion'],
                ['patterns' => ['copa.*'], 'modulo' => 'copa'],
                ['patterns' => ['fase.*'], 'modulo' => 'fase'],
                ['patterns' => ['campeon.*'], 'modulo' => 'campeon'],
                ['patterns' => ['subcampeon.*'], 'modulo' => 'subcampeon'],
                ['patterns' => ['goleador.*'], 'modulo' => 'goleador'],
                ['patterns' => ['tabla_posicion.*'], 'modulo' => 'tabla_posicion'],
                ['patterns' => ['valla.*'], 'modulo' => 'valla'],
            ];

            foreach ($deteccionesRuta as $deteccion) {
                foreach ($deteccion['patterns'] as $pattern) {
                    if (request()->routeIs($pattern)) {
                        $tipoContextoActual = $deteccion['modulo'];
                        break 2;
                    }
                }
            }

            if (! $tipoContextoActual && Str::startsWith($rutaActualPath, 'Panel/planilla/')) {
                $tipoContextoActual = 'partido';
            }

            if (! $tipoContextoActual && isset($modulosContexto[$tipoSolicitado])) {
                $tipoContextoActual = $tipoSolicitado;
            }

            if (! $tipoContextoActual || ! isset($modulosContexto[$tipoContextoActual])) {
                $tipoContextoActual = 'admin';
            }

            $opcionesPorGrupo = [
                'panel' => ['admin', 'partido', 'grupos', 'instancia_final', 'notificaciones', 'lista_buena_fe', 'categoria', 'campeon', 'jugador_consulta'],
                'competencia' => ['admin', 'partido', 'grupos', 'instancia_final', 'fecha', 'notificaciones', 'lista_buena_fe', 'jugador_consulta'],
                'gestion' => ['admin', 'edicion', 'categoria', 'equipo', 'equipoedicion', 'notificaciones', 'institucion', 'lista_buena_fe', 'jugador_consulta', 'copa', 'fase'],
                'estadisticas' => ['admin', 'campeon', 'subcampeon', 'goleador', 'tabla_posicion', 'valla', 'notificaciones', 'lista_buena_fe', 'jugador_consulta'],
            ];

            $grupoContextoActual = $modulosContexto[$tipoContextoActual]['grupo'] ?? 'panel';
            $tiposContextoDisponibles = $opcionesPorGrupo[$grupoContextoActual] ?? $opcionesPorGrupo['panel'];

            if (! in_array($tipoContextoActual, $tiposContextoDisponibles, true)) {
                array_unshift($tiposContextoDisponibles, $tipoContextoActual);
            }

            $opcionesContextoSelector = collect($tiposContextoDisponibles)
                ->filter(fn ($modulo) => isset($modulosContexto[$modulo]))
                ->map(fn ($modulo) => array_merge(['tipo' => $modulo], $modulosContexto[$modulo]))
                ->values();

            $labelContextoActual = $modulosContexto[$tipoContextoActual]['label'] ?? 'Panel admin';
            $mostrarSelectorGlobal = ! request()->routeIs('seleccionar-categoria');

            $idCategoriaContexto = request('idCategoria');
            $idGrupoContexto = request('idGrupo');
            $idFechaContexto = request('idFecha');
            $idFaseContexto = request('idFase');
            $idCopaContexto = request('idCopa');

            $nombreCategoriaContexto = null;
            if ($idCategoriaContexto) {
                $nombreCategoriaContexto = $nombreCategoria ?? null;

                if (! $nombreCategoriaContexto && isset($CategoriaSeleccionada)) {
                    $nombreCategoriaContexto = $CategoriaSeleccionada->nombreCategoria ?? null;
                }

                if (! $nombreCategoriaContexto && isset($categorias)) {
                    $categoriaEncontrada = collect($categorias)->firstWhere('id', (int) $idCategoriaContexto);
                    $nombreCategoriaContexto = $categoriaEncontrada->nombreCategoria ?? $categoriaEncontrada->nombre ?? null;
                }

                if (! $nombreCategoriaContexto) {
                    $nombreCategoriaContexto = \App\Models\Categoria::query()
                        ->whereKey((int) $idCategoriaContexto)
                        ->value('nombreCategoria');
                }
            }

            $nombreGrupoContexto = null;
            if ($idGrupoContexto) {
                $nombreGrupoContexto = $nombreGrupo ?? null;

                if (! $nombreGrupoContexto && isset($GrupoSeleccionado)) {
                    $nombreGrupoContexto = $GrupoSeleccionado->nombre ?? null;
                }

                if (! $nombreGrupoContexto && isset($grupos)) {
                    $grupoEncontrado = collect($grupos)->firstWhere('id', (int) $idGrupoContexto);
                    $nombreGrupoContexto = $grupoEncontrado->nombre ?? null;
                }

                if (! $nombreGrupoContexto) {
                    $nombreGrupoContexto = \App\Models\Grupo::query()
                        ->whereKey((int) $idGrupoContexto)
                        ->value('nombre');
                }
            }

            $nombreFechaContexto = null;
            if ($idFechaContexto) {
                $nombreFechaContexto = $nombreFecha ?? null;

                if (! $nombreFechaContexto && isset($fechas)) {
                    $fechaEncontrada = collect($fechas)->firstWhere('id', (int) $idFechaContexto);
                    $nombreFechaContexto = $fechaEncontrada->nombre ?? null;
                }

                if (! $nombreFechaContexto) {
                    $nombreFechaContexto = \App\Models\Fecha::query()
                        ->whereKey((int) $idFechaContexto)
                        ->value('nombre');
                }
            }

            $nombreFaseContexto = $idFaseContexto
                ? (\App\Models\Fase::query()->whereKey((int) $idFaseContexto)->value('nombre') ?: null)
                : null;

            $nombreCopaContexto = $idCopaContexto
                ? (\App\Models\Copa::query()->whereKey((int) $idCopaContexto)->value('nombre') ?: null)
                : null;

            $resumenContexto = array_filter([
                'Categoria' => $nombreCategoriaContexto ?: ($idCategoriaContexto ? 'ID '.$idCategoriaContexto : null),
                'Grupo' => $nombreGrupoContexto ?: ($idGrupoContexto ? 'ID '.$idGrupoContexto : null),
                'Fecha' => $nombreFechaContexto ?: ($idFechaContexto ? 'ID '.$idFechaContexto : null),
                'Fase' => $nombreFaseContexto ?: ($idFaseContexto ? 'ID '.$idFaseContexto : null),
                'Copa' => $nombreCopaContexto ?: ($idCopaContexto ? 'ID '.$idCopaContexto : null),
            ], static fn ($valor) => $valor !== null && $valor !== '');
        @endphp
        @if ($mostrarSelectorGlobal)
            <div class="mb-3 px-3 sm:px-4">
                <div data-panel-context-toolbar class="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">Seleccionar contexto</p>
                            <p class="text-sm font-semibold text-slate-800">Módulo actual: {{ $labelContextoActual }}</p>
                            @if (! empty($resumenContexto))
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($resumenContexto as $clave => $valor)
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                                            {{ $clave }}: {{ $valor }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <form action="{{ route('admin', ['idEdicion' => $idEdicionContexto]) }}" method="GET" id="selector-contexto-form"
                            class="grid w-full min-w-0 grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto] lg:w-[480px] lg:flex-shrink-0">
                            <select name="tipo" id="selector-contexto-tipo"
                                class="h-10 w-full rounded-lg border border-white/30 bg-white/95 px-3 text-sm font-medium text-slate-700 shadow-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200">
                                @foreach ($opcionesContextoSelector as $opcionContexto)
                                    <option value="{{ $opcionContexto['tipo'] }}" data-destino="{{ $opcionContexto['route'] }}" {{ $tipoContextoActual === $opcionContexto['tipo'] ? 'selected' : '' }}>
                                        {{ $opcionContexto['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="idCategoria" value="{{ $idCategoriaContexto }}">
                            <input type="hidden" name="idGrupo" value="{{ $idGrupoContexto }}">
                            <input type="hidden" name="idFecha" value="{{ $idFechaContexto }}">
                            <input type="hidden" name="idFase" value="{{ $idFaseContexto }}">
                            <input type="hidden" name="idCopa" value="{{ $idCopaContexto }}">
                            <button type="submit"
                                class="inline-flex h-10 items-center justify-center rounded-lg bg-cyan-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700">
                                Cambiar
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        @endif

        @if (Route::is('admin'))
            <div class="mb-3 grid grid-cols-1 gap-3 px-3 sm:grid-cols-2 sm:px-4 xl:grid-cols-4">
                <div class="min-w-0">
                    <a
                         href="{{ route('notificaciones.index', ['idEdicion' => $idEdicionContexto]) }}">
                        <button type="button"
                            class="group flex h-full min-h-[54px] w-full items-center justify-center gap-2 rounded-xl border border-white/15 bg-slate-900/75 px-4 py-2 text-sm font-semibold text-white shadow transition duration-200 hover:-translate-y-0.5 hover:bg-slate-900">
                            <svg class="h-4 w-4 text-cyan-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            Notificaciones
                        </button>
                    </a>
                </div>
                <div class="min-w-0">
                    <a href="{{ route('partido.index', ['idEdicion' => $idEdicionContexto]) }}">
                        <button type="button"
                            class="group flex h-full min-h-[54px] w-full items-center justify-center gap-2 rounded-xl border border-white/15 bg-slate-900/75 px-4 py-2 text-sm font-semibold text-white shadow transition duration-200 hover:-translate-y-0.5 hover:bg-slate-900">
                            <svg class="h-4 w-4 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Partidos
                        </button>
                    </a>
                </div>

                {{-- Botón Imprimir Tabla de Posiciones (todas las categorías y grupos) --}}
                <div class="min-w-0">
                    <button onclick="imprimirEnIframe('{{ route('admin.imprimir-tabla-posiciones', ['idEdicion' => $idEdicionContexto ?? '']) }}')"
                        class="flex h-full min-h-[54px] w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow transition duration-200 hover:-translate-y-0.5 hover:bg-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Imprimir Tablas de Posiciones
                    </button>
                </div>

                {{-- Botón Imprimir Fixture (todos los partidos o por fecha) --}}
                <div class="min-w-0">
                    <button type="button"
                        onclick="abrirModalFixtureImpresion('{{ route('admin.imprimir-fixture', ['idEdicion' => $idEdicionContexto ?? '']) }}')"
                        class="flex h-full min-h-[54px] w-full items-center justify-center gap-2 rounded-xl border border-blue-400/30 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow transition duration-200 hover:-translate-y-0.5 hover:bg-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Imprimir Fixture
                    </button>
                </div>

                {{-- Botón Planilla de Cobro --}}
                <div class="min-w-0">
                    <button onclick="imprimirConTitulo('{{ route('admin.planilla-cobro', ['idEdicion' => $idEdicionContexto ?? '']) }}', 'Planilla de Cobro')"
                        class="flex h-full min-h-[54px] w-full items-center justify-center gap-2 rounded-xl border border-amber-400/30 bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow transition duration-200 hover:-translate-y-0.5 hover:bg-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Planilla de Cobro
                    </button>
                </div>

            </div>
            <!-- Filtro de horario -->
            <div class="flex justify-center px-3 sm:px-4">
                <div class="relative inline-block w-full text-left md:w-80 filtro-fecha">
                    <p class="text-center text-gray-800 font-semibold bg-gray-200 py-2 rounded-lg shadow-md mb-2">
                        Filtro por Horario
                    </p>
                    <form action="{{ route('admin', $parametrosEdicionActual) }}" method="GET"
                        class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4">
                        <div class="relative w-full">
                            <select id="horario" name="horario"
                                class="appearance-none bg-white text-gray-800 font-medium border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:outline-none hover:border-gray-400 px-4 py-2 rounded-lg shadow-md w-full transition ease-in-out duration-200">
                                <option value="">Mostrar Todos</option>
                                @foreach ($horariosDisponibles as $horario)
                                    <option value="{{ $horario }}" {{ request('horario') == $horario ? 'selected' : '' }}>
                                        {{ $horario }} hs
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @foreach (request()->except(['horario', '_token']) as $key => $value)
                            <!-- Excluir horario y token -->
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <button type="submit"
                            class="w-full rounded-lg bg-blue-600 px-4 py-2 font-bold text-white transition ease-in-out duration-200 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:w-auto">
                            Filtrar
                        </button>
                    </form>
                </div>
            </div>
            <div class="flex justify-center mb-4">
                @if(request('horario'))
                    <p class="text-center text-gray-700 font-semibold  bg-gray-200 py-2 px-4 rounded-lg shadow-md">
                        Mostrando partidos de las {{ request('horario') }} hs
                    </p>
                @endif
            </div>
            @php
                $pendientesPorPartido = collect($equiposSinAsistencia['equipos'] ?? [])->groupBy('partido_id');
            @endphp
            @foreach ($partidos as $categoria => $partidosPorCategoria)
                <div class="px-3 sm:px-4">
                    <h1 class="text-center text-2xl font-bold text-white sm:text-3xl lg:text-4xl">{{ $categoria }}</h1>
                </div>
                @php
                    $partidosPorFecha = $partidosPorCategoria->groupBy('nombre_fecha');
                @endphp
                @foreach ($partidosPorFecha as $fecha => $partidos)
                    <h3 class="my-2 text-center text-xl font-semibold text-white sm:text-2xl lg:text-3xl">{{ $fecha }}
                    </h3>
                    @foreach ($partidos as $p)
                        @php
                            $resultadoCargado = $p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null;
                            $estadoGolesLabel = null;
                            $estadoGolesClase = '';

                            if ($p->goles_confirmados_at !== null) {
                                $estadoGolesLabel = 'Goles confirmados';
                                $estadoGolesClase = 'bg-emerald-100 text-emerald-700';
                            } elseif ($resultadoCargado) {
                                $estadoGolesLabel = 'Goles pendientes de carga';
                                $estadoGolesClase = 'bg-amber-100 text-amber-700';
                            }
                        @endphp
                        <div class="mb-5 flex justify-center px-3 sm:px-4">
                            <div class="grid w-full gap-4 bg-gray-50 p-2 sm:p-3 lg:w-11/12 xl:w-3/4 2xl:w-1/2">
                                <div class="overflow-x-auto rounded-lg shadow-lg md:overflow-x-visible">
                                    <div class="flex flex-col items-center justify-between gap-3 border-b px-3 py-4 sm:px-4 md:flex-row md:px-6">
                                        <div class="flex flex-col items-center gap-2 text-center sm:flex-row sm:gap-4 sm:text-left">
                                            <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" width="40" height="40" alt="Escudo {{ $p->nombre_local }}"
                                                class="object-cover" />
                                            <div class="text-base font-medium sm:text-lg">{{ $p->nombre_local }}</div>
                                            <div class="text-gray-500">vs</div>
                                            <div class="text-base font-medium sm:text-lg">{{ $p->nombre_visitante }}</div>
                                            <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" width="40" height="40" alt="Escudo {{ $p->nombre_visitante }}"
                                                class="object-cover" />
                                        </div>
                                        <div class="mt-1 text-center text-xl font-bold text-[--color-primary] sm:text-2xl md:mt-0">
                                            {{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-center justify-between gap-2 px-3 py-3 text-muted-foreground sm:px-4 md:flex-row md:px-6">
                                        <div class="mb-1 flex items-center gap-2 md:mb-0">
                                            <span
                                                class="text-base sm:text-lg">{{ \Carbon\Carbon::parse($p->horario_datetime)->format('d-m-Y H:i') }}</span>
                                        </div>
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="text-sm sm:text-base">Cancha {{ $p->cancha }}</span>
                                        </div>
                                    </div>
                                    @if ($estadoGolesLabel)
                                        <div class="flex justify-center px-3 pb-4">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $estadoGolesClase }}">
                                                {{ $estadoGolesLabel }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                                @if($pendientesPorPartido->has($p->id))
                                    <div class="flex flex-wrap items-center justify-center gap-2 px-3 py-2">
                                        @foreach($pendientesPorPartido->get($p->id) as $pendiente)
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $pendiente['estado'] === 'sin_planilla' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ $pendiente['equipo'] }} ({{ $pendiente['condicion'] }}): {{ $pendiente['estado_label'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="my-2 flex justify-center">
                                    <a
                                        href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $idEdicionContexto, 'tipoPartido' => 'partido', 'horario' => $horarioString ?? 0, 'origen' => 'admin', 'horario_filtro' => $horarioString]) }}">
                                        <button class="w-full rounded bg-blue-500 px-4 py-2 font-bold text-white hover:bg-blue-700 sm:w-auto">
                                            Planillas
                                        </button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            @endforeach
        @endif
        @php
            $nombreRutaActual = request()->route()?->getName() ?? '';
            $esVistaShow = Str::endsWith($nombreRutaActual, '.show');
            $esVistaAdmin = request()->routeIs('admin');
            $clasesContenedorPrincipal = $esVistaAdmin
                ? 'mt-0 flex flex-grow items-center justify-center lg:pl-0'
                : 'mt-0 flex flex-grow justify-center lg:pl-0';
            $clasesSeccionPrincipal = $esVistaAdmin
                ? 'w-full min-w-0 px-3 sm:px-4 lg:w-3/4 lg:px-0'
                : 'w-full min-w-0 px-3 sm:px-4 lg:px-6';
        @endphp
        <div class="{{ $clasesContenedorPrincipal }}">
            <section class="{{ $clasesSeccionPrincipal }} panel-admin-content">
                @if (session('status'))
                    <div class="flex justify-center">
                        <div id="status-message" class="flex w-full items-center rounded-xl bg-white p-4 shadow-lg sm:w-11/12 lg:w-1/2">
                            <div class="flex h-10 w-10 items-center justify-center bg-blue-300 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                                    <path
                                        d="M256 48C141.1 48 48 141.1 48 256s93.1 208 208 208 208-93.1 208-208S370.9 48 256 48zm0 368c-88.2 0-160-71.8-160-160S167.8 96 256 96s160 71.8 160 160-71.8 160-160 160zm0-256c-17.7 0-32 14.3-32 32s14.3 32 32 32 32-14.3 32-32-14.3-32-32-32zm0 96c-17.7 0-32 14.3-32 32v64c0 17.7 14.3 32 32 32s32-14.3 32-32v-64c0-17.7-14.3-32-32-32z" />
                                </svg>
                            </div>
                            <div class="ml-6">
                                <div class="flex justify-center">
                                    <p class="text-lg text-black font-semibold">{{ session('status') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        setTimeout(function () {
                            document.getElementById('status-message').remove();
                        }, 8000);
                    </script>
                @endif
                @if ($esVistaShow)
                    @yield('detalle')
                @else
                    @yield('contenido')
                @endif
            </section>
        </div>
    </div>

    <div id="fixture-impresion-modal" class="fixed inset-0 z-[80] hidden items-center justify-center px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="fixture-impresion-modal-title">
        <button type="button" class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" aria-label="Cerrar modal de impresión de fixture" onclick="cerrarModalFixtureImpresion()"></button>

        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-white/15 bg-white shadow-2xl">
            <div class="bg-gradient-to-r from-blue-700 to-cyan-700 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-100">Fixture</p>
                        <h2 id="fixture-impresion-modal-title" class="mt-1 text-xl font-bold">Seleccionar fecha de impresión</h2>
                        <p class="mt-1 text-sm text-blue-100">Elegí una jornada específica o imprimí todo el fixture.</p>
                    </div>
                    <button type="button" class="rounded-full bg-white/15 p-2 text-white transition hover:bg-white/25" aria-label="Cerrar modal" onclick="cerrarModalFixtureImpresion()">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="space-y-5 px-6 py-6">
                <div>
                    <label for="fecha-fixture-impresion" class="mb-2 block text-sm font-semibold text-slate-700">Fecha a imprimir</label>
                    <select id="fecha-fixture-impresion"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Todas las fechas</option>
                        @foreach ($fechasFixtureImpresion ?? collect() as $fechaFixture)
                            <option value="{{ $fechaFixture->id }}">{{ $fechaFixture->nombre }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Si elegís “Todas las fechas”, se imprime el fixture completo de la edición.</p>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" onclick="cerrarModalFixtureImpresion()">
                        Cancelar
                    </button>
                    <button type="button" id="fixture-impresion-confirmar" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-blue-800" onclick="confirmarImpresionFixture()">
                        Imprimir fixture
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mensajes de éxito
            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: @json(session('success')),
                    confirmButtonColor: '#3085d6',
                });
            @endif

            // Mensajes de advertencia
            @if (session('warning'))
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: @json(session('warning')),
                    confirmButtonColor: '#d97706',
                });
            @endif

            // Mensajes de error (personalizados)
            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: @json(session('error')),
                    confirmButtonColor: '#d33',
                });
            @endif

                // Errores de validación (Laravel)
                @if ($errors->any())
                    let errorHtml = '<ul style="text-align: left;">';
                    @foreach ($errors->all() as $error)
                        errorHtml += '<li>{{ $error }}</li>';
                    @endforeach
                    errorHtml += '</ul>';

                    Swal.fire({
                        icon: 'error',
                        title: 'Errores de Validación',
                        html: errorHtml,
                        confirmButtonColor: '#d33',
                    });
                @endif
        });


    </script>

    {{-- Función para imprimir usando iframe oculto --}}
    <script>
        function imprimirConTitulo(url, titulo) {
            const tituloOriginal = document.title;
            document.title = titulo;
            window.addEventListener('afterprint', function restaurar() {
                document.title = tituloOriginal;
                window.removeEventListener('afterprint', restaurar);
            });
            imprimirEnIframe(url);
        }

        function imprimirEnIframe(url) {
            // Crear iframe oculto
            let iframe = document.getElementById('print-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'print-iframe';
                iframe.style.position = 'absolute';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = 'none';
                iframe.style.left = '-9999px';
                document.body.appendChild(iframe);
            }
            
            // Mostrar loading
            Swal.fire({
                title: 'Preparando impresión...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Cargar contenido e imprimir
            iframe.onload = function() {
                Swal.close();
                setTimeout(() => {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                }, 500);
            };
            
            iframe.src = url;
        }

        let fixtureImpresionUrl = null;

        function abrirModalFixtureImpresion(url) {
            fixtureImpresionUrl = url;
            const modal = document.getElementById('fixture-impresion-modal');
            const selectorFecha = document.getElementById('fecha-fixture-impresion');

            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            setTimeout(() => selectorFecha?.focus(), 50);
        }

        function cerrarModalFixtureImpresion() {
            const modal = document.getElementById('fixture-impresion-modal');

            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function confirmarImpresionFixture() {
            if (!fixtureImpresionUrl) {
                return;
            }

            imprimirFixtureCompleto(fixtureImpresionUrl);
            cerrarModalFixtureImpresion();
        }

        function imprimirFixtureCompleto(url) {
            const selectorFecha = document.getElementById('fecha-fixture-impresion');
            const urlImpresion = new URL(url, window.location.origin);

            if (selectorFecha?.value) {
                urlImpresion.searchParams.set('idFecha', selectorFecha.value);
            }

            imprimirEnIframe(urlImpresion.toString());
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalFixtureImpresion();
            }
        });
    </script>
    @yield('scripts')
</body>

</html>
