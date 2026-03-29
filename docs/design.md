# Diseno tecnico - Cambio multi-alcance de planillas, header, push y rol arbitral

## Resumen ejecutivo

La exploracion muestra dos realidades distintas:

- `header` y `push notifications` son un problema correctivo y transversal;
- `planilla publica` y la carga de goles ya tienen base funcional existente, pero ahora deben exponer una capa explicita de transparencia entre reporte arbitral y conciliacion oficial;
- el `rol encargado/a de arbitros` es la feature grande, porque agrega permisos, un listado mobile-first de ultima fecha, filtros, acceso restringido a planilla y una UX nueva.

La recomendacion tecnica es partir el trabajo en tres cambios logicos y, para el alcance arbitral, reutilizar el flujo actual de actualizacion de planilla mediante un servicio compartido en lugar de duplicar reglas dentro de otra vista.

## Estado tecnico actual relevante

### Header

- `resources/views/layouts/navigation.blade.php` renderiza CTA de login para guest y tambien el boton de push siempre.
- `resources/js/push-notifications.js` reescribe ese boton como `Iniciar sesion` cuando el visitante no esta autenticado.

### Push

- `resources/views/head.blade.php` siempre incluye `resources/js/push-notifications.js` y siempre registra `/sw.js`.
- `routes/web.php` mantiene endpoints de subscribe, unsubscribe, status, VAPID y panel operativo.
- `public/sw.js` sigue procesando `push` y `notificationclick`.
- `User` y varias notificaciones de dominio siguen preparados para `WebPushChannel`.

### Planilla

- `PlanillaPublicaService` ya arma payload readonly desde `planilla_jugadores`.
- `ControladorPlanillaJugador::actualizarJugadores()` ya contiene la logica real de guardado e invalidacion.
- `PlanillaConfirmacionService` encapsula confirmacion y desconfirmacion del partido.
- El baseline actual modela bien el dato oficial por jugador, pero no documenta todavia una capa separada para `reporte arbitral` cuando ese reporte no logra vincularse a una fila oficial.

### Roles

- el modelo actual es simple: enum `rol` + middleware que solo deja pasar admins.
- no existe hoy un esquema RBAC mas fino.

## Particion recomendada

### Cambio 1 - Hardening correctivo

Incluye:

- `header`
- `push notifications`

Ventajas:

- riesgo acotado;
- elimina ruido visual y una feature rota;
- puede desplegarse rapido y revertirse via configuracion.

### Cambio 2 - Consolidacion de planilla publica

Incluye:

- dejar explicitamente aceptado el baseline actual;
- cerrar regresiones entre lectura publica y carga admin;
- agregar una representacion explicita para inconsistencias entre dorsal/goles reportados por arbitraje y planilla oficial;
- reforzar pruebas y contrato.

Ventajas:

- aprovecha codigo ya existente;
- reduce incertidumbre antes de abrir el rol arbitral.

### Cambio 3 - Rol `encargado/a de arbitros`

Incluye:

- permiso nuevo;
- rutas nuevas;
- listado de todos los partidos de la ultima fecha dentro del contexto operativo;
- filtros de busqueda mobile-first;
- acceso restringido a planilla por partido;
- vista mobile-first;
- reuso del caso de uso actual.

Ventajas:

- mantiene separado el cambio de seguridad/permisos del correctivo de push.

## Propuesta tecnica por subproblema

## 1. Header

### Solucion recomendada

Eliminar la ambiguedad en guest haciendo que la cabecera solo tenga un CTA principal de autenticacion.

### Como se logra

- si push queda deshabilitado, el boton de push desaparece completo del header y el bug queda resuelto por consecuencia;
- si por timing el bugfix de header se hace antes que el apagado de push, el boton de push no debe mutar a `Iniciar sesion` para guest.

### Tradeoff

- corregir solo el texto del boton arregla el sintoma;
- ocultar el CTA de push en guest o apagar push de punta a punta corrige la causa.

Recomendacion: resolver la causa, no solo el copy.

## 2. Push notifications

### Objetivo tecnico de "deshabilitar totalmente"

En este repo, "deshabilitar totalmente" significa:

- no exponer CTA ni estados visuales de push;
- no registrar nuevos dispositivos;
- no exponer utilmente la clave VAPID ni el flujo de alta;
- no despachar `WebPushChannel` desde las notificaciones del dominio;
- no dejar un service worker de push como unica barrera residual;
- mantener la configuracion y los datos de suscripciones para poder reactivar luego.

### Estrategia recomendada: feature flag reversible

Agregar una bandera central, por ejemplo `config/push.php` o `config/features.php`, con una clave tipo `push.enabled=false`.

### Puntos de corte requeridos

#### UI y assets

- `resources/views/layouts/navigation.blade.php`: ocultar CTA de push si la bandera esta apagada.
- `resources/views/head.blade.php`: no cargar `resources/js/push-notifications.js` cuando push esta apagado.
- `resources/views/head.blade.php`: no registrar `/sw.js` cuando push esta apagado.

#### Rutas y controlador

- `routes/web.php`: mantener rutas pero hacer que el controlador responda `404` o `410` cuando push este apagado, para conservar reversibilidad sin rehacer routing;
- `ControladorNotificacionesPush`: centralizar un guard clause temprano `if (! config('push.enabled'))` y cortar subscribe, unsubscribe, status, VAPID y pantallas operativas.

#### Dominio de notificaciones

- `ResultadoCargado`, `NotificacionPersonalizada`, `NuevoPartidoProgramado`: el metodo `via()` no debe agregar `WebPushChannel` si la bandera esta apagada.
- Esto evita que el dominio siga intentando entregar push aunque queden suscripciones persistidas.

#### Service worker

- existe un worker ya registrado en clientes previos;
- por eso no alcanza con esconder el boton.

Recomendacion:

- versionar `public/sw.js` hacia una variante neutra sin listeners de push mientras dure la deshabilitacion;
- mantener el registro apagado para nuevos clientes;
- permitir que clientes existentes reciban el worker neutral en visitas posteriores.

### Decisiones de reversibilidad

- NO borrar `push_subscriptions` en esta etapa;
- NO eliminar config VAPID ni tablas;
- SI aislar toda decision de activacion detras de una bandera unica.

### Alternativas evaluadas

#### Opcion A - Ocultar solo boton

No sirve. Deja service worker, rutas y canales activos.

#### Opcion B - Borrar tablas y codigo

Si, apagaria todo, pero NO es reversible ni seguro para un bugfix corto.

#### Opcion C - Feature flag transversal

Es la recomendada: corta comportamiento, preserva datos y permite rollback limpio.

## 3. Planilla publica y carga de goles

### Decision arquitectonica

Tratar este subalcance como baseline existente a consolidar, no como una feature desde cero, y sumar una capa de observabilidad funcional que explique la diferencia entre `dato arbitral reportado` y `dato confirmado/oficial`.

### Evidencia de reuso actual

- lectura publica: `app/Services/PlanillaPublicaService.php`;
- render readonly: `resources/views/components/planilla-publica-modal.blade.php`;
- carga admin: `resources/views/Panel/planilla/show.blade.php` + `ControladorPlanillaJugador::actualizarJugadores()`;
- confirmacion: `PlanillaConfirmacionService`.

### Fuente de verdad

La fuente canonica del dato oficial debe seguir siendo `planilla_jugadores`.

Justificacion:

- es el registro por partido y por jugador;
- ya alimenta tanto la vista admin como la publica;
- evita mezclar agregados de `tabla_goleadores` con la carga puntual del encuentro.

### Capa adicional requerida: transparencia arbitral

El nuevo requisito NO reemplaza la fuente oficial; agrega una segunda capa semantica para mostrar el origen del dato.

Separacion propuesta:

- `dato reportado por arbitraje`: dorsal y goles informados por quien carga arbitraje, aun si no existe jugador conciliado;
- `dato confirmado/oficial`: fila de `planilla_jugadores` efectivamente vinculada a un jugador del partido.

Regla central:

- si ambos coinciden y estan conciliados, la UI puede mostrar el resultado como confirmado;
- si arbitraje reporta algo que no logra matchear con planilla oficial, la UI debe exponer la inconsistencia, no esconderla ni inventar identidad.

### Contrato a preservar

- `numero_camiseta`
- `goles`
- `asistencia`
- estado de `goles_confirmados`
- sin exponer DNI ni fecha de nacimiento en la planilla publica.

### Representacion tecnica/UI sugerida

Sin definir aun implementacion cerrada, la documentacion recomienda que el payload o view model de planilla publica/admin soporte dos colecciones o secciones logicas:

1. `jugadores_oficiales`
   - filas vinculadas a `planilla_jugadores`;
   - muestran identidad oficial del jugador, dorsal oficial y goles conciliados.

2. `reportes_arbitrales_pendientes`
   - filas sin jugador oficial vinculado;
   - muestran solo dorsal reportado, cantidad de goles reportados y estado de conciliacion;
   - nunca muestran nombre/apellido inferidos.

Badges o estados sugeridos:

- `Oficial` o `Confirmado`: dato conciliado contra planilla oficial;
- `Reportado por arbitraje`: dato de origen arbitral aun no conciliado totalmente;
- `Pendiente de vinculacion`: dorsal/goles reportados sin jugador oficial asociado;
- `Inconsistencia detectada`: opcional para casos donde dorsal reportado contradice la planilla oficial cargada.

Copy funcional sugerido:

- `Dato arbitral reportado` para el origen de arbitraje;
- `Dato confirmado en planilla oficial` para el registro conciliado;
- `No hay un jugador oficial vinculado a este dorsal` para el caso borde principal.

Decision UX importante:

- la interfaz debe explicar el por que de la diferencia;
- no debe "resolver" visualmente la inconsistencia adjudicando un nombre por aproximacion humana.

### Reutilizacion de datos existentes

La propuesta debe reutilizar primero lo que ya existe:

- `planilla_jugadores` como base del dato oficial;
- logica actual de admin para editar `numero_camiseta` y `goles`;
- `PlanillaConfirmacionService` para el estado de confirmacion;
- futuros reportes arbitrales o estructura equivalente solo como capa complementaria de observabilidad, no como reemplazo del registro oficial.

Conclusiones de reuso:

- la planilla publica no necesita cambiar su concepto de fuente oficial;
- si aparece una fuente arbitral separada, debe agregarse como lectura paralela y claramente etiquetada;
- `tabla_goleadores` sigue siendo un agregado derivado, no el punto de conciliacion.

### Decision de implementacion futura

Antes de abrir el rol arbitral conviene extraer la logica de `actualizarJugadores()` a un servicio compartido.

Propuesta de nombre:

- `app/Services/ActualizarPlanillaPartidoService.php`

Responsabilidades:

- recibir `tipoPartido`, `partidoId`, `idEdicion` y payload de jugadores;
- persistir `goles`, `asistencia`, `numero_camiseta`, `tarjeta_roja`;
- recalcular `Jugador` y `TablaGoleador`;
- invalidar confirmacion usando `PlanillaConfirmacionService`;
- devolver resultado consistente para admin y arbitros.

Tradeoff:

- extraer primero agrega trabajo tecnico;
- pero evita que admin y arbitros diverjan en reglas despues.

Recomendacion: extraer antes de sumar el nuevo rol.

## 4. Rol `encargado/a de arbitros`

## Decision principal

Agregar un rol tecnico nuevo en la columna `users.rol`, con slug ASCII recomendado `encargado_arbitros`, y construir una superficie de acceso separada del panel admin tradicional.

### Por que no conviene usar el panel admin tal cual

- el middleware actual deja pasar solo admin;
- la vista actual muestra y postea muchos campos ajenos al caso de uso arbitral;
- en telefono la densidad de inputs es alta y mezcla responsabilidades.

### Por que no conviene inventar otro guardado

Porque el usuario pidio MISMO comportamiento que la planilla actual. Si duplicamos reglas, tarde o temprano se rompen.

### Impacto funcional nuevo sobre este rol

La futura interfaz mobile-first no solo debe permitir ver la ultima fecha, filtrar partidos y cargar goles; tambien debe dejar claro cuando un reporte arbitral queda sin conciliacion oficial.

Eso implica que el flujo arbitral futuro deberia contemplar, como minimo:

- un listado inicial con todos los partidos de la ultima fecha visible;
- filtros rapidos para encontrar un partido sin tabla de escritorio;
- acceso a planilla restringida por partido;
- estado visible de `reportado` vs `conciliado`;
- feedback cuando el dorsal cargado no exista en la planilla oficial del partido;
- posibilidad de dejar el reporte marcado como pendiente para resolucion posterior por admin o por correccion de planilla.

## Definicion de `ultima fecha`

### Decision recomendada

Para este alcance, `ultima fecha` debe calcularse dentro del contexto operativo de la vista arbitral y no como una ultima fecha global de toda la base.

Contexto minimo recomendado:

- `idEdicion` vigente o seleccionado;
- agrupador competitivo visible en esa vista, por ejemplo categoria, zona o campeonato si el negocio ya lo usa para separar fixtures;
- solo partidos que el rol arbitral realmente puede abrir en ese flujo.

### Razon

- evita mezclar partidos de distintas ediciones;
- reduce ruido en mobile;
- mantiene coherencia con la planilla actual, que ya opera por contexto de partido/edicion.

### Tradeoff

- `ultima fecha` global es mas simple de calcular, pero puede mostrar partidos irrelevantes o cruzados entre contextos;
- `ultima fecha` contextual requiere definir mejor el query, pero entrega una UX mas correcta y menos ambigua.

Recomendacion: usar `ultima fecha contextual` como default documental.

## Permisos y roles

### Estado actual

- `admin`
- `editor`
- `regular`

### Propuesta

- sumar `encargado_arbitros`

### Ajustes tecnicos esperados

- migracion para extender enum de `users.rol`;
- helper en `User`, por ejemplo `isEncargadoArbitros()` o un metodo mas general `tieneRolPanel()`;
- reemplazar `UserAccesPanelMiddleware` por una politica mas explicita en las rutas que hoy sean admin-only y en las nuevas rutas arbitrales.

### Recomendacion de middleware

En lugar de seguir acoplando todo a `isAdmin()`, crear middleware dedicados:

- `EnsureAdminRole`
- `EnsureArbitralRole`

O uno parametrizable si se quiere menos codigo:

- `EnsureUserHasRole:admin`
- `EnsureUserHasRole:admin,encargado_arbitros`

Tradeoff:

- middleware parametrizable es mas reusable;
- middleware separados son mas legibles para este proyecto, que hoy no tiene un RBAC sofisticado.

Recomendacion: parametrizable simple, sin introducir un framework de permisos completo.

## Rutas propuestas

### Admin actual

Mantener:

- `GET /Panel/planilla/{partidoId}/{idEdicion}/{tipoPartido}/{horario?}`
- `POST /Panel/planilla/actualizar-jugador`
- `POST /Panel/planilla/confirmar-goles`
- `POST /Panel/planilla/quitar-confirmacion-goles`

### Nuevo flujo arbitral

Propuesta de namespace:

- `GET /Panel/arbitros/partidos/ultima-fecha`
- `GET /Panel/arbitros/partidos/{partidoId}/{idEdicion}/{tipoPartido}`
- `POST /Panel/arbitros/partidos/actualizar-goles`

Notas:

- la ruta de `ultima-fecha` debe devolver todos los partidos visibles en la fecha mas reciente del contexto activo y aceptar filtros de consulta simples;
- las rutas arbitrales no deben exponer altas/bajas manuales ni confirmacion administrativa;
- pueden apuntar al mismo servicio de actualizacion que admin, pero con validacion de campos permitidos mas estricta;
- si negocio necesita asignar partidos por usuario arbitral, esa asociacion se agrega despues como feature complementaria. En esta fase se documenta pero no se exige.

### Filtros sugeridos para `ultima-fecha`

Filtros minimos recomendados:

- `equipo`: busqueda por nombre parcial de local o visitante;
- `categoria` o agrupador competitivo equivalente;
- `zona` si el fixture ya la expone;
- `estado_carga`: por ejemplo `sin cargar`, `cargado`, `pendiente de vinculacion`.

Tradeoff:

- demasiados filtros rompen la rapidez mobile;
- muy pocos filtros degradan la encontrabilidad cuando la fecha tiene muchos partidos.

Recomendacion: empezar por 3 o 4 filtros cortos con controles tactiles y texto de busqueda unico.

## Forma de reuso exacto de la logica actual

### Regla

Admin y `encargado_arbitros` deben terminar llamando al mismo caso de uso de actualizacion.

### Propuesta de capas

- `ControladorPlanillaJugador` queda fino y usa el servicio compartido.
- nuevo `ControladorPlanillaArbitral` renderiza mobile-first y tambien usa el mismo servicio.
- `PlanillaConfirmacionService` se mantiene como dependencia del servicio de actualizacion.

### Payload arbitral recomendado

El payload arbitral debe ser mas chico que el admin:

```php
[
    'partido_id' => 123,
    'idEdicion' => 7,
    'tipoPartido' => 'partido',
    'jugadores' => [
        '40100001' => [
            'goles' => 2,
        ],
    ],
]
```

Decision de permiso:

- `numero_camiseta` puede viajar en el payload solo como referencia interna si hiciera falta validar consistencia, pero NO debe venir editable desde la UI arbitral;
- la UI arbitral debe tratarlo como readonly y el backend arbitral debe ignorar o rechazar cambios de dorsal enviados manualmente.

### Ventaja

Aunque la UI arbitral no edite asistencia ni roja, el mismo servicio puede conservar defaults y semantica actual sin reescribir reglas.

### Consideracion importante

La vista admin hoy postea mas campos de los que `actualizarJugadores()` realmente usa. Eso es una buena noticia: la version arbitral puede mandar menos datos sin perder comportamiento de dominio.

### Extension recomendada para conciliacion futura

Si el proyecto agrega un payload o lectura especifica para `reportes arbitrales`, conviene que incluya al menos:

```php
[
    'dorsal_reportado' => 8,
    'goles_reportados' => 3,
    'estado_conciliacion' => 'pendiente',
    'planilla_jugador_id' => null,
]
```

Razon:

- permite mostrar transparencia real sin depender de inferencias;
- deja abierto un camino claro para que admin o negocio concilien despues contra `planilla_jugadores`.

## Estrategia mobile-first

### Objetivo UX

Resolver la carga desde cancha con una interfaz rapida, limpia y apta para pulgar, empezando por un listado de ultima fecha y siguiendo con una planilla de edicion minima.

### Principios

- primera pantalla orientada a encontrar partido rapido;
- filtros visibles y de bajo costo cognitivo;
- lista por equipo en cards apiladas;
- cada fila muestra dorsal como dato dominante;
- control de goles con input numerico grande o stepper `- / +`;
- CTA de guardado fijo o facilmente accesible;
- feedback claro de guardado y de confirmacion invalidada;
- evitar tablas densas en mobile.

### Estructura recomendada de pantalla

- pantalla 1: header simple con `Ultima fecha`, chips/filtros y cards de partidos con categoria, horario, cancha, local, visitante y estado de carga;
- pantalla 2: header simple con partido, categoria, hora y cancha;
- bloque `Local`;
- bloque `Visitante`;
- cada jugador en fila corta: `#10` readonly + control de goles;
- seccion o badge visible para distinguir `dato arbitral reportado` de `dato oficial conciliado`;
- si existe un reporte sin jugador oficial, card separada de `pendiente de vinculacion`;
- footer sticky con `Guardar cambios`.

### Fuera de la vista arbitral

- nombre y apellido;
- DNI;
- fecha de nacimiento;
- asistencia;
- tarjeta roja;
- edicion manual de `numero_camiseta`;
- altas/bajas manuales;
- botones de imprimir;
- confirmacion manual de goles.

## Permisos exactos del flujo arbitral

### Lectura permitida

- listado de partidos de la ultima fecha dentro del contexto operativo;
- metadatos minimos del partido: local, visitante, categoria, zona si aplica, horario, cancha y estado de carga;
- planilla restringida con dorsal visible, goles actuales y estados de conciliacion relevantes.

### Edicion permitida

- modificar `goles` de filas existentes accesibles desde la planilla del partido.

### Edicion prohibida

- cambiar `numero_camiseta`;
- editar identidad del jugador;
- tocar asistencia o tarjetas;
- confirmar o quitar confirmacion manualmente;
- dar altas o bajas de jugadores;
- abrir otras vistas administrativas fuera del flujo arbitral.

### Decision de enforcement

La restriccion debe vivir en dos capas:

- UI: no renderizar controles no permitidos;
- backend: validar que el controlador arbitral solo acepte cambios sobre `goles`.

Esto evita que el permiso dependa solo del frontend.

### Impacto en panel admin

El panel admin futuro deberia absorber esta aclaracion como herramienta de conciliacion, no solo como grilla editable.

Recomendacion documental:

- mantener la grilla oficial existente como origen de verdad;
- sumar indicadores visuales cuando exista un reporte arbitral pendiente o inconsistente;
- permitir que admin entienda rapido por que hay goles reportados sin jugador oficial, en vez de ver un simple "dato faltante".

Beneficio:

- reduce soporte manual;
- mejora auditabilidad del flujo entre cancha y planilla oficial;
- prepara el terreno para la UX mobile-first sin cambiar aun el producto.

## Seguridad y restricciones de acceso

### Push apagado

- la bandera debe cortarlo tanto para guest como para autenticados.

### Rol arbitral

- solo `admin` y `encargado_arbitros` deben poder entrar a rutas arbitrales, segun el caso;
- `editor` y `regular` deben recibir redireccion o `403` segun convencion definida.

Decision adicional:

- el listado de `ultima fecha` y la planilla restringida comparten la misma frontera de acceso;
- abrir la planilla por URL directa sin permiso debe fallar igual que abrir el listado.

### Planilla publica

- sigue siendo readonly;
- no se toca su politica publica en este cambio mas alla de preservar el contrato funcional;
- pero si debe documentarse que la lectura publica futura puede necesitar mostrar secciones o labels distintas para `reportado por arbitraje` y `confirmado/oficial`.

## Testing recomendado para la futura implementacion

### Header

- feature o view test que verifique un unico CTA de login en guest.

### Push

- tests de controlador que validen respuesta de feature apagada;
- tests de notificaciones que verifiquen ausencia de `WebPushChannel` cuando la bandera este en `false`.

### Planilla y admin

- conservar `PlanillaPublicaTest` y `PlanillaConfirmacionTest` como baseline;
- agregar pruebas que aseguren no regresion de `numero_camiseta` y `goles`.
- agregar escenarios de transparencia donde un dorsal reportado no tenga jugador oficial y se muestre como pendiente sin identidad inferida.

### Rol arbitral

- acceso permitido para `encargado_arbitros`;
- acceso denegado para `regular`;
- listado de ultima fecha limitado al contexto operativo esperado;
- filtros de equipo/categoria/zona/estado funcionando sin degradar mobile;
- persistencia de goles usando la misma semantica que admin;
- rechazo o ignorado de intentos de cambiar `numero_camiseta` desde el flujo arbitral;
- invalidacion de confirmacion cuando hubo cambios reales;
- vista mobile-first sin campos no permitidos;
- feedback visible para pendientes de vinculacion e inconsistencias entre dato arbitral y dato oficial.

## Tradeoffs principales

### Reusar servicio compartido vs duplicar controlador completo

- reusar servicio: mas trabajo inicial, mucha menos deuda futura;
- duplicar controlador: parece mas rapido, pero rompe el requisito de comportamiento exacto.

Recomendacion: servicio compartido.

### Mostrar inconsistencia vs esconderla

- mostrar inconsistencia con labels claras: mas honesto y auditable;
- esconderla o absorberla silenciosamente: parece mas prolijo, pero rompe transparencia y puede inducir errores de interpretacion.

Recomendacion: explicitar la diferencia entre reporte arbitral y planilla oficial.

### Nuevo rol enum vs sistema completo de permisos

- nuevo rol enum: encaja con la arquitectura actual y resuelve rapido;
- RBAC completo: mas flexible, pero sobredimensionado para este cambio.

Recomendacion: nuevo rol enum ahora; RBAC solo si aparecen mas roles especializados.

### Apagar push por flag vs borrar feature

- flag: reversible y seguro;
- borrar: mas destructivo y de mayor riesgo operacional.

Recomendacion: flag transversal.

## Secuencia sugerida de implementacion futura

1. Apagar push de punta a punta mediante feature flag y corregir header guest.
2. Extraer servicio compartido de actualizacion de planilla tomando como fuente la logica actual.
3. Congelar el baseline de planilla publica/admin con tests de regresion y con escenarios de transparencia arbitral.
4. Diseñar el view model o payload que separe `dato arbitral reportado` de `dato confirmado/oficial`.
5. Agregar rol `encargado_arbitros`, middleware y rutas acotadas.
6. Construir la vista mobile-first arbitral reutilizando el servicio compartido y mostrando estados de conciliacion.

## Resultado esperado al finalizar

- header sin duplicacion;
- push completamente apagado pero reversible;
- planilla publica y admin formalizadas sobre la misma fuente de verdad, con transparencia explicita sobre reportes arbitrales pendientes;
- nueva experiencia arbitral mobile-first con listado de ultima fecha, filtros, planilla restringida a edicion de goles y sin duplicar reglas del negocio.
