@include('head')
@include('components.loader')

<body class="bg-[--color-primary]  font-[Poppins]">
    <button data-drawer-target="sidebar-multi-level-sidebar" data-drawer-toggle="sidebar-multi-level-sidebar"
        aria-controls="sidebar-multi-level-sidebar" type="button"
        class="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg lg:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200">
        <span class="sr-only">Open sidebar</span>
        <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"
            xmlns="http://www.w3.org/2000/svg">
            <path clip-rule="evenodd" fill-rule="evenodd"
                d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z">
            </path>
        </svg>
    </button>
    <aside id="sidebar-multi-level-sidebar"
        class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full lg:translate-x-0"
        aria-label="Sidebar">
        <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50">
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
                    <a href="{{ route('admin', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <button type="button"
                        class="flex items-center w-full p-2 text-base text-gray-900 transition duration-75 rounded-lg group hover:bg-gray-100"
                        aria-controls="dropdown-example" data-collapse-toggle="dropdown-example">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 192 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                            <path
                                d="M48 80a48 48 0 1 1 96 0A48 48 0 1 1 48 80zM0 224c0-17.7 14.3-32 32-32l64 0c17.7 0 32 14.3 32 32l0 224 32 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 512c-17.7 0-32-14.3-32-32s14.3-32 32-32l32 0 0-192-32 0c-17.7 0-32-14.3-32-32z" />
                        </svg>
                        <span class="flex-1 ms-3 text-left rtl:text-right whitespace-nowrap">Seleccionar Edición</span>
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 10 6">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 4 4 4-4" />
                        </svg>
                    </button>
                    <ul id="dropdown-example" class="hidden py-2 space-y-2">
                        @foreach ($ediciones as $edicion)
                            <li>
                                <a href="{{ route('admin', ['idEdicion' => $edicion->id]) }}"
                                    class="block px-4 py-2 hover:bg-gray-100">
                                    {{ $edicion->nombre }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
                <li>
                    <a href="{{ route('edicion.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('categoria.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('equipo.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('instancia_final.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Trofeo (representa instancias finales/playoffs) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                            <path
                                d="M400 0H176c-26.5 0-48.1 21.8-47.1 48.2c.2 5.3 .4 10.6 .7 15.8H24C10.7 64 0 74.7 0 88c0 92.6 33.5 157 78.5 200.7c44.3 43.1 98.3 64.8 138.1 75.8c23.4 6.5 39.4 26 39.4 45.6c0 20.9-17 37.9-37.9 37.9H192c-17.7 0-32 14.3-32 32s14.3 32 32 32H384c17.7 0 32-14.3 32-32s-14.3-32-32-32H357.9C337 448 320 431 320 googl10.1c0-19.6 15.9-39.2 39.4-45.6c39.9-11 93.9-32.7 138.1-75.8C542.5 245 576 180.6 576 88c0-13.3-10.7-24-24-24H446.4c.3-5.2 .5-10.4 .7-15.8C googl8.1 21.8 426.5 0 400 0zM48.9 112h84.4c9.1 90.1 29.2 150.3 51.9 190.6c-24.9-11-48.8-27.1-67.5-45.3C76.1 googl8.1 52 204.1 48.9 112zM464.1 256.4c-18.7 18.2-42.7 34.3-67.5 45.3c22.7-40.3 42.8-100.5 51.9-190.6h84.4c-3 92.1-27.2 145.1-68.8 googl45.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap"> Instancias Finales</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('lista-buena-fe.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('jugador.consulta') }}"
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
                    <a href="{{ route('institucion.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('grupos.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('fecha.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('copa.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('fase.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('tabla_goleador.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('estadisticas-jugadores', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('campeon.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('subcampeon.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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
                    <a href="{{ route('goleador.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
                        class="flex items-center p-2 text-gray-900 rounded-lg hover:bg-gray-100 group">
                        {{-- Icono: Estrella (representa goleadores destacados del torneo) --}}
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transition duration-75 group-hover:text-gray-900"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                            <path
                                d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L googl18.2 329 522.4 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L googl1 150.3 316.9 18z" />
                        </svg>
                        <span class="flex-1 ms-3 whitespace-nowrap">Goleadores del Torneo</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('valla.index', isset($EdicionSeleccionada) ? ['idEdicion' => $EdicionSeleccionada] : []) }}"
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

            <!-- Botón de Notificaciones Push -->
            <div class="px-3 py-4 border-t border-gray-300">
                <div class="mb-2">
                    <span class="px-2 text-xs font-semibold text-gray-500 uppercase">Notificaciones</span>
                </div>
                <a href="{{ route('push.suscriptores') }}"
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

            <!-- Tarjeta de Estadísticas de Jugadores en Sidebar -->
            @if (Route::is('admin') && isset($estadisticasJugadores) && $estadisticasJugadores['total_jugadores'] > 0)
                <div class="px-3 py-4 border-t border-gray-300">
                    <div class="bg-white rounded-lg shadow-md p-4">
                        <div class="flex items-center mb-3">
                            <svg class="w-6 h-6 text-blue-600 mr-2" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 640 512">
                                <path fill="currentColor"
                                    d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192l42.7 0c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0L21.3 320C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7l42.7 0C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3l-213.3 0zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352l117.3 0C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7l-330.7 0c-14.7 0-26.7-11.9-26.7-26.7z" />
                            </svg>
                            <h3 class="text-sm font-bold text-gray-800">Estadísticas</h3>
                        </div>

                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-3 text-white text-center mb-3">
                            <p class="text-xs font-semibold mb-1">Jugadores Totales</p>
                            @if(isset($estadisticasJugadores['nombre_fecha']))
                                <p class="text-xs text-gray-100 mb-1">{{ $estadisticasJugadores['nombre_fecha'] }}</p>
                            @endif
                            <p class="text-3xl font-bold">{{ $estadisticasJugadores['total_jugadores'] }}</p>
                            <p class="text-xs mt-1 opacity-90">en planilla</p>
                        </div>

                        @if(isset($jugadoresPorCategoria) && count($jugadoresPorCategoria) > 0)
                            <div class="border-t border-gray-200 pt-3">
                                <p class="text-xs font-semibold text-gray-600 mb-2">Por Categoría:</p>
                                <div class="space-y-1">
                                    @foreach($jugadoresPorCategoria as $categoria)
                                        <div class="flex justify-between items-center bg-gray-50 rounded p-2">
                                            <span class="text-xs font-medium text-gray-700">{{ $categoria['categoria'] }}</span>
                                            <span class="bg-blue-500 text-white px-2 py-0.5 rounded-full text-xs font-bold">
                                                {{ $categoria['total_jugadores'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </aside>
    <div class="flex flex-col h-screen">
        @if (isset($EdicionSeleccionada))
            <div class="flex justify-end">
                <section class="bg-emerald-300 p-2">
                    <div
                        class="flex max-w-xs items-center border-r-8 border-[--color-secondary] bg-white p-4 text-emerald-900 shadow-lg">
                        <div class="min-w-0">
                            <h2 class="overflow-hidden text-ellipsis whitespace-nowrap">
                                {{ $EdicionSeleccionada->nombre }}
                            </h2>
                        </div>
                    </div>
                </section>
            </div>
        @endif
        @if (Route::is('admin'))
            <div class="flex flex-row flex-wrap justify-center mb-2 gap-2">
                <div>
                    <a
                        href="{{ route('seleccionar-categoria', ['idEdicion' => $EdicionSeleccionada, 'tipo' => 'equipogrupo']) }}">
                        <button
                            class="bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg">
                            Agregar Equipo a Grupo
                        </button>
                    </a>
                </div>
                <div>
                    <a href="{{ route('partido.index', ['idEdicion' => $EdicionSeleccionada]) }}">
                        <button
                            class="bg-gray-800 hover:bg-gray-900 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg">
                            Partidos
                        </button>
                    </a>
                </div>

                <!-- Botones de Notificaciones Push -->
                <div class="relative" id="notif-dropdown-container">
                    <button onclick="toggleNotifDropdown()"
                        class="bg-blue-600 hover:bg-blue-700 mt-2 mb-2 text-white py-2 px-4 rounded-full transition-all duration-300 md:py-3 md:px-6 md:rounded-lg flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
                        </svg>
                        Enviar Notificación ▼
                    </button>
                    <!-- Dropdown Menu -->
                    <div id="notif-dropdown"
                        class="absolute hidden bg-white shadow-xl rounded-lg mt-1 z-50 min-w-56 right-0">
                        <button
                            onclick="enviarNotificacionRapida('📅 Fixture Actualizado', '¡Se ha cargado el fixture! Consultá los próximos partidos.', '/fixture?idEdicion={{ $EdicionSeleccionada->id ?? '' }}'); cerrarNotifDropdown()"
                            class="flex items-center w-full px-4 py-3 text-left text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors border-b rounded-t-lg">
                            <span class="mr-2">📅</span> Fixture Cargado
                        </button>
                        <button
                            onclick="enviarNotificacionRapida('⚽ Goleadores Actualizados', '¡Se actualizó la tabla de goleadores! Mirá quiénes lideran.', '/tabla-de-goleadores?idEdicion={{ $EdicionSeleccionada->id ?? '' }}'); cerrarNotifDropdown()"
                            class="flex items-center w-full px-4 py-3 text-left text-gray-700 hover:bg-green-50 hover:text-green-700 transition-colors border-b">
                            <span class="mr-2">⚽</span> Goleadores Actualizados
                        </button>
                        <button onclick="abrirModalNotificacion(); cerrarNotifDropdown()"
                            class="flex items-center w-full px-4 py-3 text-left text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors rounded-b-lg">
                            <span class="mr-2">✏️</span> Personalizada...
                        </button>
                    </div>
                </div>
            </div>
            <!-- Filtro de horario -->
            <div class="flex justify-center w-auto">
                <div class="relative inline-block text-left w-full md:w-64 filtro-fecha">
                    <p class="text-center text-gray-800 font-semibold bg-gray-200 py-2 rounded-lg shadow-md mb-2">
                        Filtro por Horario
                    </p>
                    <form action="{{ route('admin', ['idEdicion' => $EdicionSeleccionada]) }}" method="GET"
                        class="flex items-center gap-4">
                        <div class="relative w-full">
                            <select id="horario" name="horario"
                                class="appearance-none bg-white text-gray-800 font-medium border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:outline-none hover:border-gray-400 px-4 py-2 rounded-lg shadow-md w-auto transition ease-in-out duration-200">
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
                            class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition ease-in-out duration-200">
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
            @foreach ($partidos as $categoria => $partidosPorCategoria)
                <div class="mr-5">
                    <h1 class="text-4xl text-white font-bold text-center">{{ $categoria }}</h1>
                </div>
                @php
                    $partidosPorFecha = $partidosPorCategoria->groupBy('nombre_fecha');
                @endphp
                @foreach ($partidosPorFecha as $fecha => $partidos)
                    <h3 class="text-3xl text-white font-semibold my-2 text-center">{{ $fecha }}
                    </h3>
                    @foreach ($partidos as $p)
                        <div class="flex justify-center mb-5">
                            <div class="grid gap-6 p-1 bg-gray-50 w-full md:w-1/2">
                                <div class=" }rounded-lg shadow-lg overflow-x-auto md:overflow-x-visible">
                                    <div class="flex flex-col md:flex-row items-center justify-between md:px-6 py-4 border-b">
                                        <div class="flex items-center gap-4">
                                            <img src="{{ asset('fotos/equipos/' . $p->foto_local) }}" width="40" height="40" alt=""
                                                class="object-cover" />
                                            <div class="font-medium text-lg">{{ $p->nombre_local }}</div>
                                            <div class="text-gray-500">vs</div>
                                            <div class="font-medium text-lg">{{ $p->nombre_visitante }}</div>
                                            <img src="{{ asset('fotos/equipos/' . $p->foto_visitante) }}" width="40" height="40" alt=""
                                                class="object-cover" />
                                        </div>
                                        <div class="text-2xl font-bold mt-4 md:mt-0 text-center text-[--color-primary]">
                                            {{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}
                                        </div>
                                    </div>
                                    <div class="flex flex-col md:flex-row items-center justify-between px-6 py-2 text-muted-foreground">
                                        <div class="flex items-center gap-2 mb-2 md:mb-0">
                                            <span
                                                class="text-xl">{{ \Carbon\Carbon::parse($p->horario_datetime)->format('d-m-Y H:i') }}</span>
                                        </div>
                                        <div class="flex flex-col items-center gap-2">
                                            <span>Cancha {{ $p->cancha }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-center my-2">
                                    <a
                                        href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'partido', 'horario' => $horarioString ?? 0]) }}">
                                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
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
        <div class="flex-grow flex items-center justify-center lg:pl-36 mt-0">
            <section class="w-full md:w-3/4 px-4 md:px-0">
                @if (session('status'))
                    <div class="flex justify-center">
                        <div id="status-message" class="flex items-center rounded-xl bg-white p-4 shadow-lg w-1/2">
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
                        }, 60000);
                    </script>
                @endif
                @if (Str::endsWith(request()->route()->getName(), '.show'))
                    @yield('detalle')
                @else
                    @yield('contenido')
                @endif
            </section>
        </div>
    </div>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mensajes de éxito
            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: "{{ session('success') }}",
                    confirmButtonColor: '#3085d6',
                });
            @endif

            // Mensajes de error (personalizados)
            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: "{{ session('error') }}",
                    confirmButtonColor: '#d33',
                });
            @endif

            // Mensajes de estado (status) - a veces usados como éxito
            @if (session('status'))
                Swal.fire({
                    icon: 'info',
                    title: 'Información',
                    text: "{{ session('status') }}",
                    confirmButtonColor: '#3085d6',
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

        // Funciones para el dropdown de notificaciones
        function toggleNotifDropdown() {
            const dropdown = document.getElementById('notif-dropdown');
            dropdown.classList.toggle('hidden');
        }

        function cerrarNotifDropdown() {
            const dropdown = document.getElementById('notif-dropdown');
            dropdown.classList.add('hidden');
        }

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function (e) {
            const container = document.getElementById('notif-dropdown-container');
            if (container && !container.contains(e.target)) {
                cerrarNotifDropdown();
            }
        });

        // Función para enviar notificación rápida predefinida
        async function enviarNotificacionRapida(titulo, mensaje, url) {
            const result = await Swal.fire({
                title: '¿Enviar notificación?',
                html: `<strong>${titulo}</strong><br><small>${mensaje}</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                enviarNotificacion(titulo, mensaje, url);
            }
        }

        // Función para abrir modal de notificación personalizada
        function abrirModalNotificacion() {
            Swal.fire({
                title: 'Enviar Notificación',
                width: 400,
                html: `
                    <div class="text-left" style="overflow-x: hidden;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                        <input id="swal-titulo" class="swal2-input" style="width: 100%; margin: 0;" placeholder="Ej: ¡Importante!" maxlength="100">
                        
                        <label class="block text-sm font-medium text-gray-700 mb-1 mt-3">Mensaje</label>
                        <textarea id="swal-mensaje" class="swal2-textarea" style="width: 100%; margin: 0;" placeholder="Escribe el mensaje..." maxlength="255"></textarea>
                        
                        <label class="block text-sm font-medium text-gray-700 mb-1 mt-3">URL (opcional)</label>
                        <input id="swal-url" class="swal2-input" style="width: 100%; margin: 0;" placeholder="/" value="/">
                        
                        <label class="block text-sm font-medium text-gray-700 mb-1 mt-3">Icono/Emoji</label>
                        <input id="swal-icono" class="swal2-input" style="width: 100%; margin: 0;" placeholder="📢" value="📢" maxlength="10">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#8b5cf6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const titulo = document.getElementById('swal-titulo').value;
                    const mensaje = document.getElementById('swal-mensaje').value;

                    if (!titulo || !mensaje) {
                        Swal.showValidationMessage('Título y mensaje son obligatorios');
                        return false;
                    }

                    return {
                        titulo: titulo,
                        mensaje: mensaje,
                        url: document.getElementById('swal-url').value || '/',
                        icono: document.getElementById('swal-icono').value || '📢'
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarNotificacion(
                        result.value.titulo,
                        result.value.mensaje,
                        result.value.url,
                        result.value.icono
                    );
                }
            });
        }

        // Función para enviar la notificación al servidor
        async function enviarNotificacion(titulo, mensaje, url, icono = '📢') {
            try {
                Swal.fire({
                    title: 'Enviando...',
                    text: 'Espera mientras se envían las notificaciones',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const response = await fetch('/Panel/push/enviar-manual', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ titulo, mensaje, url, icono })
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Enviado!',
                        html: `Se envió la notificación a <strong>${data.count}</strong> usuario(s)`,
                        confirmButtonColor: '#10b981'
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin destinatarios',
                        text: data.message || 'No hay usuarios suscritos',
                        confirmButtonColor: '#f59e0b'
                    });
                }
            } catch (error) {
                console.error('Error enviando notificación:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo enviar la notificación',
                    confirmButtonColor: '#ef4444'
                });
            }
        }
    </script>
    @yield('scripts')
</body>

</html>