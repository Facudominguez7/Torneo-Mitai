# Especificacion - Cambio multi-alcance de planillas, header, push y rol arbitral

## Objetivo

Documentar un cambio multi-alcance para:

- corregir el header duplicado en guest;
- deshabilitar totalmente push notifications de forma segura y reversible;
- consolidar el comportamiento de planilla publica y carga de goles desde admin, incorporando transparencia entre dato arbitral reportado y dato oficial conciliado;
- definir una nueva experiencia mobile-first para un rol `encargado/a de arbitros` que vea todos los partidos de la ultima fecha, pueda filtrarlos e ingresar a una planilla restringida para cargar solo goles reutilizando la logica actual.

No forma parte de esta fase implementar codigo de producto.

## Hallazgos del explorer con evidencia

### 1. Header

- `resources/views/layouts/navigation.blade.php` muestra para usuarios guest un CTA explicito `Iniciar sesion` en la cabecera.
- Ese mismo header tambien renderiza siempre el boton `#push-toggle-btn`.
- `resources/js/push-notifications.js` cambia el texto del boton de push a `Iniciar sesion` cuando `data-authenticated=false`.
- Resultado observado en codigo: para guest existen dos accesos equivalentes al login en la misma cabecera.

### 2. Push notifications

- `resources/views/head.blade.php` carga `resources/js/push-notifications.js` en todas las paginas y registra `navigator.serviceWorker.register('/sw.js')` al cargar.
- `resources/views/layouts/navigation.blade.php` expone el CTA visual de push siempre.
- `routes/web.php` mantiene activas rutas de suscripcion, estado, VAPID y panel operativo de push.
- `app/Models/User.php` usa `HasPushSubscriptions`.
- `app/Notifications/ResultadoCargado.php`, `app/Notifications/NotificacionPersonalizada.php` y `app/Notifications/NuevoPartidoProgramado.php` agregan `WebPushChannel` si el usuario tiene suscripciones.
- `public/sw.js` sigue escuchando eventos `push` y `notificationclick`.

### 3. Planilla publica y carga de goles

- `app/Services/PlanillaPublicaService.php` ya lee `planilla_jugadores.numero_camiseta` y `planilla_jugadores.goles`.
- `resources/views/components/planilla-publica-modal.blade.php` ya muestra `Camiseta` y `Goles` para local y visitante.
- `resources/views/Panel/planilla/show.blade.php` ya permite editar `numero_camiseta`, `goles`, `asistencia` y `tarjeta_roja`.
- `app/Http/Controllers/Panel/ControladorPlanillaJugador.php` persiste esos campos en `actualizarJugadores()` y usa `PlanillaConfirmacionService` para invalidar confirmacion al modificar la planilla.
- `tests/Feature/PlanillaPublicaTest.php` y `tests/Feature/Panel/PlanillaConfirmacionTest.php` ya cubren parte de este flujo.
- Aclaracion funcional nueva: el negocio necesita explicar inconsistencias entre lo que arbitraje reporta por dorsal y lo que la planilla oficial logra vincular a un jugador real.
- Caso real explicitado por usuario: arbitraje puede informar `#8 -> 3 goles`, pero la planilla oficial no tener ningun jugador cargado con dorsal `8`.
- Implicancia: no alcanza con mostrar `numero_camiseta` y `goles`; la experiencia debe distinguir entre `dato reportado por arbitraje` y `dato confirmado/oficial`.

### 4. Roles y permisos

- La base actual usa un enum de usuario con `admin`, `editor` y `regular` en `database/migrations/2025_09_14_114210_create_users_table.php`.
- `app/Http/Middleware/UserAccesPanelMiddleware.php` deja pasar solo a `Auth::user()->isAdmin()`.
- Hoy no existe un rol especifico ni un permiso intermedio para una carga acotada de goles desde telefono.

## Desglose del alcance por subproblema

### A. `header`

Tipo: bugfix corto.

Problema:

- la cabecera guest duplica el acceso a login por la convivencia entre CTA principal y CTA de push mutado por JS.

Resultado esperado:

- el usuario guest ve un solo camino claro para iniciar sesion.

### B. `push notifications`

Tipo: bugfix corto con impacto transversal.

Problema:

- push sigue activo en UI, frontend, service worker, rutas y notificaciones del dominio.

Resultado esperado:

- la funcionalidad queda apagada de punta a punta sin destruir configuracion ni datos, para poder reactivarla luego.

### C. `planilla publica`

Tipo: feature ya parcialmente presente que debe consolidarse y quedar explicitamente aceptada.

Problema:

- el pedido funcional del usuario ya aparece cubierto parcialmente en el codigo actual, pero falta tratarlo como alcance formal del cambio, dejar criterios de regresion claros entre la carga admin y la visualizacion publica, y modelar explicitamente las inconsistencias entre goles reportados por arbitraje y jugadores efectivamente vinculados en la planilla oficial.

Resultado esperado:

- se preserva y valida que la planilla publica muestre numero de camiseta y goles provenientes de la planilla oficial del partido;
- ademas, cuando exista diferencia entre lo reportado por arbitraje y lo conciliado oficialmente, el usuario debe ver una explicacion explicita sin inventar identidad de jugador.

### D. `rol encargado de arbitros`

Tipo: feature mayor.

Problema:

- hoy solo admin puede entrar al panel y la vista de planilla expone muchos campos y acciones que no corresponden a una carga mobile-first desde cancha.

Resultado esperado:

- un rol acotado puede entrar desde el telefono, ver todos los partidos de la ultima fecha dentro de su contexto operativo, aplicar filtros, ingresar a una planilla restringida y guardar usando EXACTAMENTE las mismas reglas de negocio que la planilla actual;
- dentro de esa planilla solo puede anotar o editar goles segun el numero de camiseta ya existente, sin modificar el dorsal ni otros campos administrativos.

## Dependencias entre subproblemas

- `header` y `push notifications` estan relacionados: si push se oculta o se apaga correctamente, el doble `Iniciar sesion` desaparece en guest.
- `planilla publica` depende funcionalmente de la carga realizada en admin o por el futuro rol arbitral, porque ambos leen y escriben sobre `planilla_jugadores`.
- `rol encargado de arbitros` depende de extraer o reutilizar la logica actual de actualizacion de planilla; NO debe inventar otra regla de calculo de goles ni otra semantica de confirmacion.
- `push notifications` es independiente del flujo de planillas, salvo por el impacto visual en header y por la necesidad de no mezclar ambos cambios en un mismo entregable si se busca menor riesgo.

## Recomendacion de particion logica

- Cambio logico 1: `header` + `push notifications`.
- Cambio logico 2: consolidacion de `planilla publica` y de la carga actual admin como baseline reutilizable.
- Cambio logico 3: `rol encargado de arbitros` mobile-first.

Motivo:

- el primer bloque es correctivo y reversible;
- el segundo bloque es de estabilizacion funcional con bajo riesgo porque ya existe codigo base;
- el tercero introduce permisos, nuevas rutas y nueva UX, por lo tanto merece una feature separada.

## Requerimientos funcionales

### RF-H01 Header sin duplicacion de login

WHEN un usuario no autenticado visualiza la cabecera publica THEN el sistema SHALL mostrar un unico CTA principal de autenticacion.

### RF-H02 Header consistente al deshabilitar push

IF push notifications esta deshabilitado THEN el header SHALL dejar de renderizar cualquier CTA o estado asociado a push.

### RF-P01 Deshabilitacion total de push en frontend

IF push notifications esta deshabilitado THEN el sistema SHALL dejar de cargar el flujo de suscripcion del cliente, incluyendo CTA visible, script especifico y registro util de push.

### RF-P02 Deshabilitacion total de push en backend

IF push notifications esta deshabilitado THEN el sistema SHALL impedir nuevas altas de suscripcion, consultas de VAPID y envios push operativos.

### RF-P03 Reversibilidad de push

WHEN el equipo tecnico decida reactivar push THEN el sistema SHALL poder volver a habilitarlo sin requerir reconstruir datos historicos ni reescribir el dominio completo.

### RF-PL01 Visualizacion publica de dorsal y goles

WHEN existe planilla cargada para un partido o instancia final THEN la planilla publica SHALL mostrar por jugador `nombre`, `apellido`, `numero_camiseta`, `goles` y `asistencia`, sin exponer datos sensibles adicionales.

### RF-PL02 Fuente unica de verdad

WHEN la planilla publica muestra dorsal y goles THEN esos valores SHALL provenir de `planilla_jugadores`, que es la fuente oficial por partido.

### RF-PL03 Carga administrativa de goles

WHEN un administrador edita la planilla del partido THEN el sistema SHALL permitir modificar `numero_camiseta` y `goles` desde la planilla existente.

### RF-PL04 Consistencia de confirmacion

WHEN se modifica un valor persistido de la planilla THEN el sistema SHALL mantener el comportamiento actual de invalidar la confirmacion manual de goles si existia.

### RF-PL05 Transparencia entre dato arbitral y dato oficial

WHEN exista informacion de goles reportada por arbitraje THEN el sistema SHALL diferenciar visual y semanticamente el dato `reportado por arbitraje` del dato `confirmado/oficial` de planilla.

### RF-PL06 Dorsal arbitral sin jugador vinculado

WHEN arbitraje reporte goles para un dorsal que no coincide con ningun jugador de la planilla oficial THEN el sistema SHALL mostrar el dorsal reportado y la cantidad de goles como dato arbitral pendiente, sin adjudicar nombre ni identidad no confirmada.

### RF-PL07 Goles pendientes de vinculacion

WHEN existan goles reportados por arbitraje que aun no puedan conciliarse con una fila oficial de `planilla_jugadores` THEN el sistema SHALL exponer ese estado como pendiente de vinculacion y explicar que la planilla oficial todavia no tiene un jugador confirmado para ese reporte.

### RF-PL08 Prohibicion de identidad inferida

IF no existe vinculacion oficial entre un reporte arbitral y un jugador de planilla THEN el sistema SHALL evitar mostrar nombre, apellido o cualquier inferencia de identidad basada solo en dorsal o contexto manual.

### RF-PL09 Etiquetado del origen del dato

WHEN la interfaz muestre goles o dorsales dentro del flujo de transparencia THEN el sistema SHALL indicar que campos pertenecen a `dato arbitral reportado` y que campos pertenecen a `dato confirmado/oficial`.

### RF-AR01 Nuevo rol operativo

WHEN se crea un usuario con rol `encargado/a de arbitros` THEN el sistema SHALL permitirle acceder solo al flujo acotado de carga de goles definido para arbitraje.

### RF-AR02 Vista mobile-first acotada

WHEN un usuario con rol `encargado/a de arbitros` abre la planilla desde su telefono THEN el sistema SHALL mostrar una interfaz mobile-first enfocada en dorsal y goles por equipo.

### RF-AR03 Superficie minima de edicion

WHEN un usuario con rol `encargado/a de arbitros` usa su vista dedicada THEN el sistema SHALL ocultar nombre, apellido, DNI, fecha de nacimiento, asistencia, tarjeta roja, altas/bajas manuales y acciones administrativas no necesarias.

### RF-AR04 Misma logica de guardado

WHEN el rol `encargado/a de arbitros` anota o edita goles THEN el sistema SHALL ejecutar EXACTAMENTE la misma logica de persistencia, recalculo e invalidacion que hoy usa la planilla del partido actual.

### RF-AR05 Mismo alcance de partido

WHEN el rol `encargado/a de arbitros` entra a cargar THEN el sistema SHALL poder operar sobre partido regular e instancia final si el negocio lo habilita, sin duplicar reglas de dominio.

### RF-AR06 Listado de ultima fecha

WHEN un usuario con rol `encargado/a de arbitros` entra a su flujo operativo THEN el sistema SHALL mostrar todos los partidos correspondientes a la ultima fecha disponible dentro del contexto de edicion/categoria habilitado para esa vista.

### RF-AR07 Definicion operativa de ultima fecha

IF existen multiples fechas, categorias o contextos historicos THEN el sistema SHALL considerar `ultima fecha` como la fecha mas reciente visible y editable dentro del contexto operativo seleccionado, evitando mezclar partidos de otras ediciones o vistas ajenas.

### RF-AR08 Filtros para encontrar partidos

WHEN el usuario arbitral visualiza el listado de ultima fecha THEN el sistema SHALL permitir filtrar partidos al menos por equipo, categoria o zona y estado de carga, priorizando una busqueda rapida desde mobile.

### RF-AR09 Acceso a planilla restringida por partido

WHEN el usuario arbitral selecciona un partido del listado THEN el sistema SHALL permitir ingresar a la planilla restringida de ese partido sin exponer acciones de administracion general.

### RF-AR10 Edicion exclusiva de goles

WHEN el usuario arbitral abre la planilla restringida THEN el sistema SHALL permitir anotar o editar SOLO los goles de cada fila ya existente de planilla usando el numero de camiseta como referencia visual.

### RF-AR11 Dorsal solo lectura

WHEN el usuario arbitral edita la planilla restringida THEN el sistema SHALL mostrar el `numero_camiseta` como dato readonly y SHALL impedir modificarlo desde ese flujo.

### RF-AR12 Paridad funcional con planilla actual

WHEN el usuario arbitral modifica goles en la planilla restringida THEN el sistema SHALL conservar el mismo comportamiento funcional de la planilla actual respecto de validacion, persistencia, recalculo e invalidacion de confirmacion.

## Requerimientos no funcionales

### RNF-01 Bajo riesgo sobre worktree legacy

La solucion SHOULD modificar solo los puntos necesarios y mantener naming legacy como `idEdicion`, `idEquipo`, etc.

### RNF-02 Reuso de logica

La solucion del rol arbitral MUST reutilizar servicios o casos de uso existentes para evitar divergencia funcional con admin.

### RNF-03 Mobile-first real

La experiencia del rol arbitral MUST priorizar telefono: scroll corto, blancos tactiles amplios, lectura por equipo y guardado claro.

### RNF-03b Descubribilidad operativa en mobile

La vista inicial arbitral MUST permitir identificar y filtrar rapido partidos de la ultima fecha con controles tactiles simples, sin depender de tablas anchas ni interacciones de escritorio.

### RNF-04 Seguridad operativa de push

Deshabilitar push MUST ser seguro y reversible, evitando que queden canales activos por accidente.

### RNF-05 Regresion controlada

La consolidacion de planilla publica MUST mantener los tests y contratos actuales como baseline.

### RNF-06 Transparencia sin ambiguedad

La solucion MUST priorizar claridad semantica: el usuario debe poder distinguir rapido si un gol esta confirmado en planilla oficial o si sigue siendo un reporte arbitral pendiente.

## Escenarios concretos

### Escenario 1 - Guest en home

- Dado un visitante no autenticado
- cuando entra al sitio publico
- entonces ve un solo CTA de login en header
- y no ve un segundo `Iniciar sesion` derivado del flujo de push.

### Escenario 2 - Push apagado

- Dado que push fue deshabilitado por configuracion
- cuando un usuario abre cualquier pagina
- entonces no aparece CTA de push
- y el frontend no intenta iniciar un alta nueva de suscripcion.

### Escenario 3 - Admin carga goles

- Dado un administrador en la planilla del partido
- cuando cambia numero de camiseta o goles y guarda
- entonces el sistema persiste los cambios en `planilla_jugadores`
- y, si la planilla estaba confirmada, remueve la confirmacion.

### Escenario 4 - Usuario consulta planilla publica

- Dado un partido con planilla cargada
- cuando el usuario abre `Ver planilla`
- entonces ve dorsal y goles por jugador con datos readonly provenientes del partido real.

### Escenario 5 - Dorsal reportado por arbitro sin jugador oficial

- Dado que arbitraje reporto `dorsal 8 = 3 goles`
- y que la planilla oficial no tiene ningun jugador vinculado con dorsal `8`
- cuando el usuario consulta la planilla publica o la vista de conciliacion
- entonces el sistema muestra `#8 - 3 goles` como dato reportado por arbitraje
- y explica que el reporte esta pendiente de vinculacion en la planilla oficial
- y no muestra nombre ni apellido inventados.

### Escenario 6 - Goles reportados pendientes de vinculacion

- Dado que existen reportes arbitrales cargados antes de completar o corregir la planilla oficial
- cuando el usuario revisa el estado del partido
- entonces ve cuales goles estan pendientes de vinculacion
- y distingue esos registros de los goles ya conciliados con jugadores oficiales.

### Escenario 7 - Encargado/a de arbitros desde telefono

- Dado un usuario con rol `encargado/a de arbitros`
- y que existe una ultima fecha disponible dentro de su contexto operativo
- cuando abre su flujo mobile-first
- entonces ve todos los partidos de esa ultima fecha
- y puede filtrarlos para encontrar uno rapido
- y al entrar a la planilla solo ve el dorsal como referencia readonly y controles para anotar o editar goles
- y al guardar se ejecuta la misma logica de dominio que en admin.

### Escenario 8 - Usuario sin permiso arbitral

- Dado un usuario comun o editor sin permiso especifico
- cuando intenta abrir la vista de arbitraje
- entonces el sistema niega acceso al flujo restringido.

### Escenario 9 - Arbitro intenta cambiar el dorsal

- Dado un usuario con rol `encargado/a de arbitros` dentro de la planilla restringida
- cuando revisa una fila de jugador
- entonces ve el `numero_camiseta` solo como referencia
- y no dispone de controles para editar ese valor
- y solo puede modificar la cantidad de goles.

## Criterios de aceptacion por subproblema

### CA-Header

- no hay doble CTA de login en guest;
- el comportamiento no degrada el menu de usuario autenticado.

### CA-Push

- no hay CTA visible;
- no hay alta nueva de suscripcion;
- no se despachan canales WebPush desde las notificaciones del dominio mientras la bandera este apagada;
- la deshabilitacion puede revertirse sin migraciones destructivas.

### CA-Planilla publica

- la vista publica sigue mostrando dorsal y goles;
- los datos salen de `planilla_jugadores`;
- cuando haya diferencia entre arbitraje y planilla oficial, la interfaz lo explica explicitamente;
- un dorsal reportado sin jugador oficial se muestra como pendiente, sin identidad inferida;
- se distingue que es dato arbitral y que es dato confirmado/oficial;
- no se exponen DNI ni fecha de nacimiento;
- la confirmacion sigue reflejando el estado del partido.

### CA-Rol arbitral

- existe una vista/listado mobile-first especifica para la ultima fecha;
- el listado permite filtrar partidos sin salir del flujo arbitral;
- el rol solo puede ver y editar el alcance permitido;
- dentro de la planilla restringida solo edita goles y el `numero_camiseta` queda readonly;
- admin y rol arbitral reutilizan la misma logica de guardado;
- no se duplica la regla de invalidacion de confirmacion.

## Fuera de alcance de esta fase

- implementar controladores, migraciones, Blade o JS de producto;
- correr builds;
- definir el task breakdown de implementacion;
- decidir aun el nombre final de UX visible del rol mas alla del slug tecnico recomendado.
