# Delta for Portal Público — Fixture explorador (#98)

> Nota de estado del dominio: `openspec/specs/portal-publico/spec.md` (canónico) todavía no existe.
> Existen dos changes activas sin archivar que también tocan este dominio:
> `goleadores-filtros-url` y `navegacion-publica-persistente`. Este spec agrega
> ÚNICAMENTE los requirements nuevos que introduce `fixture-explorador` (vista
> `layouts/fixture.blade.php` y su controlador); no redefine ni duplica los
> requirements de esas dos changes hermanas (tabla de goleadores, navegación
> compartida). Al archivar, las tres changes deben fusionarse en el canónico
> sin colisión — no comparten requirement names.

## ADDED Requirements

### Requirement: Fecha relevante por defecto

Una petición GET a la vista de fixture con una edición (y opcionalmente
categoría) seleccionada, sin el parámetro `idFecha`, MUST renderizar
únicamente la fecha relevante: la primera fecha con partidos aún no jugados
cuyo horario sea futuro; si ninguna fecha tiene partidos futuros, la última
fecha con partidos ya jugados; si la edición/categoría no tiene partidos
cargados, la primera fecha por orden de creación. Una petición con
`idFecha=<id>` explícito MUST renderizar exactamente esa fecha, sin aplicar
la resolución de fecha relevante.

#### Scenario: Sin idFecha y con partidos futuros

- GIVEN una edición/categoría con varias fechas, donde una de ellas tiene al
  menos un partido no jugado con horario futuro
- WHEN se hace `GET` al fixture con esa edición/categoría y sin `idFecha`
- THEN la respuesta renderiza únicamente los partidos de la fecha dueña del
  próximo partido no jugado

#### Scenario: Sin idFecha y sin partidos futuros

- GIVEN una edición/categoría donde todos los partidos de todas las fechas
  están jugados
- WHEN se hace `GET` al fixture con esa edición/categoría y sin `idFecha`
- THEN la respuesta renderiza únicamente los partidos de la fecha del último
  partido jugado

#### Scenario: Sin idFecha y sin partidos cargados

- GIVEN una edición/categoría con fechas creadas pero sin ningún partido
  cargado en ninguna de ellas
- WHEN se hace `GET` al fixture con esa edición/categoría y sin `idFecha`
- THEN la respuesta renderiza la primera fecha (sin error ni pantalla vacía
  inesperada)

#### Scenario: idFecha explícito ignora la resolución de fecha relevante

- GIVEN una edición/categoría con varias fechas y una fecha relevante distinta
  a la fecha `N`
- WHEN se hace `GET` al fixture con `idFecha=N`
- THEN la respuesta renderiza únicamente los partidos de la fecha `N`,
  independientemente de cuál sea la fecha relevante

(Trazabilidad: issue #98, criterio 1 — "fecha relevante por defecto".)

### Requirement: Vista completa opcional (`idFecha=todas`)

Una petición GET al fixture con `idFecha=todas` MUST renderizar todos los
partidos de todas las fechas de la edición/categoría seleccionada, replicando
el comportamiento que la vista tenía antes de este cambio cuando no se
enviaba `idFecha`.

#### Scenario: idFecha=todas reproduce el fixture completo

- GIVEN una edición/categoría con N fechas y M partidos en total
- WHEN se hace `GET` al fixture con `idFecha=todas`
- THEN la respuesta renderiza las N fechas con sus M partidos, sin límite ni
  paginación

(Trazabilidad: issue #98, criterio 2 — "vista Todas como escape hatch".)

### Requirement: Navegación por chips de fecha

> **ENMIENDA (decisión del owner, post-aprobación):** los chips de fecha se
> muestran en orden descendente (más reciente primero) por decisión del owner.
> El orden calendario ascendente del service se conserva como fuente de datos;
> la inversión es solo de presentación en la vista.

La vista de fixture MUST exponer un chip por cada fecha de la
categoría activa, cada uno enlazando a esa fecha mediante `idFecha`. El chip
correspondiente a la fecha actualmente mostrada MUST llevar
`aria-current="page"` y ningún otro chip de fecha lo lleva. Cada chip de
fecha MUST preservar en su enlace la categoría, el estado y el término de
búsqueda activos en la petición actual.

#### Scenario: Chip de fecha activa marcado

- GIVEN el fixture renderizado para la fecha `N` de una categoría con varias
  fechas
- WHEN se inspeccionan los chips de fecha de la respuesta
- THEN el chip de la fecha `N` tiene `aria-current="page"` y los chips de las
  demás fechas no lo tienen

#### Scenario: Chip de fecha preserva estado y búsqueda activos

- GIVEN el fixture renderizado con `estado=finalizados` y `equipo=leones`
  activos para una fecha dada
- WHEN se inspecciona el enlace de un chip de una fecha distinta
- THEN ese enlace incluye `estado=finalizados` y `equipo=leones` además de su
  propio `idFecha`

(Trazabilidad: issue #98, criterio 3 — "navegación por fecha".)

### Requirement: Chips de categoría con reinicio de filtros

La vista de fixture MUST exponer un chip por cada categoría de la edición
activa. Al seleccionar una categoría distinta a la activa, la respuesta MUST
renderizar la fecha relevante de esa nueva categoría (no la fecha que estaba
activa antes del cambio) y MUST descartar cualquier `estado`, `idFecha` o
`equipo` que estuviera activo, mostrando el estado por defecto (`todos`, sin
búsqueda).

#### Scenario: Cambiar de categoría resetea fecha, estado y búsqueda

- GIVEN el fixture renderizado para la categoría A con `idFecha=5`,
  `estado=finalizados` y `equipo=leones` activos
- WHEN se sigue el chip de la categoría B (con un conjunto de fechas distinto
  al de A)
- THEN la respuesta renderiza la fecha relevante de la categoría B, sin
  `estado` distinto de `todos` y sin filtro de `equipo` aplicado

#### Scenario: Chips de fecha se recalculan por categoría

- GIVEN dos categorías de la misma edición con conjuntos de fechas distintos
- WHEN se cambia de una categoría a otra mediante su chip
- THEN los chips de fecha mostrados corresponden a las fechas de la nueva
  categoría, no a las de la categoría anterior

(Trazabilidad: issue #98, criterio 4 — "filtros por categoría".)

### Requirement: Chips de estado

> **ENMIENDA (decisión del owner, post-aprobación):** se eliminan los chips de
> estado de la UI; el filtrado por estado queda disponible solo vía query
> param. Los scenarios de este requirement quedan sin efecto.

La vista de fixture MUST exponer tres chips de estado — `todos`,
`proximos` y `finalizados` — que expongan el parámetro `estado` en su
enlace. Seleccionar un chip de estado MUST preservar la fecha y la
categoría activas.

#### Scenario: Cambiar de estado preserva fecha y categoría

- GIVEN el fixture renderizado para una categoría e `idFecha` determinados
- WHEN se sigue el chip de estado `finalizados`
- THEN la respuesta renderiza esa misma categoría y fecha, mostrando
  únicamente partidos jugados

(Trazabilidad: issue #98, criterio 5 — "filtro por estado".)

### Requirement: Búsqueda de equipo multi-fecha

La vista de fixture MUST exponer un buscador de equipo (parámetro `equipo`,
GET, resuelto en el servidor) que, cuando esté presente y no vacío, ignore
`idFecha` y busque coincidencias parciales, insensibles a mayúsculas, contra
el nombre del equipo local o del equipo visitante en todas las fechas de la
edición/categoría seleccionada. La respuesta MUST agrupar los partidos
encontrados por fecha y renderizar únicamente las fechas con al menos una
coincidencia. La búsqueda MUST ser combinable con `estado` y MUST tratar los
comodines literales del término de búsqueda (`%`, `_`) como texto literal, no
como comodines de patrón.

#### Scenario: Búsqueda encuentra coincidencia en una fecha distinta a la activa

- GIVEN una edición/categoría con varias fechas, y un equipo que solo juega en
  una fecha distinta a la fecha relevante
- WHEN se hace `GET` al fixture con `equipo=<nombre parcial de ese equipo>`
  y sin `idFecha`
- THEN la respuesta renderiza la fecha donde juega ese equipo, agrupada,
  aunque no sea la fecha relevante

#### Scenario: Búsqueda combinada con estado

- GIVEN un equipo con un partido jugado y un partido no jugado en fechas
  distintas
- WHEN se hace `GET` al fixture con `equipo=<nombre parcial>` y
  `estado=finalizados`
- THEN la respuesta renderiza únicamente la fecha con el partido jugado de
  ese equipo

#### Scenario: Comodines literales en el término de búsqueda no producen falsos positivos

- GIVEN dos equipos, uno con un nombre que contiene el carácter `%` o `_` y
  otro sin ese carácter
- WHEN se busca por el nombre exacto del equipo que contiene `%` o `_`
- THEN la respuesta solo incluye partidos de ese equipo, sin incluir partidos
  de equipos no relacionados como si el carácter fuera un comodín

(Trazabilidad: issue #98, criterio 6 — "buscador de equipo sin recorrer todo
el fixture".)

### Requirement: Self-heal de anchors legacy de fecha

Cuando la URL cargada en el navegador contenga un fragmento `#fecha-N` y no
contenga el parámetro `idFecha`, el cliente MUST normalizar la URL a
`idFecha=N` (reemplazando la entrada de historial actual, sin agregar una
nueva) y MUST mostrar el contenido correspondiente a esa fecha.

#### Scenario: Enlace legacy con fragmento de fecha se autocorrige

- GIVEN un enlace compartido previamente con el fragmento `#fecha-7` y sin
  `idFecha` en la query string
- WHEN un visitante abre ese enlace
- THEN la URL visible pasa a incluir `idFecha=7` y la vista muestra los
  partidos de esa fecha

(Trazabilidad: issue #98, criterio 7 — "compatibilidad con enlaces
compartidos previamente".)

### Requirement: Compatibilidad con enlaces y pruebas existentes

Un enlace existente al fixture sin `idFecha` (edición sola, o edición y
categoría) MUST seguir respondiendo `200`, sin excepciones ni errores por
variables faltantes en la vista, aunque su contenido por defecto cambie de
"todos los partidos" a "la fecha relevante". Las pruebas existentes que
cubren el fixture con un contrato de variables previo MUST seguir pasando sin
modificar su intención, únicamente agregando valores por defecto para las
variables nuevas.

#### Scenario: Enlace legacy sin idFecha no explota

- GIVEN un enlace existente de la forma `route('fixture', ['idEdicion' => X])`
  sin `idFecha`
- WHEN se sigue ese enlace
- THEN la respuesta tiene status `200` y renderiza la fecha relevante, sin
  error de variable indefinida

#### Scenario: Fixture sin token público sigue sin explotar

- GIVEN la vista de fixture renderizada sin un token público asociado, con el
  conjunto de variables previo a este cambio
- WHEN se renderiza la vista
- THEN la respuesta no explota y las variables nuevas introducidas por este
  cambio toman un valor por defecto cuando no se proveen explícitamente

#### Scenario: Rango de partidos por defecto se reduce a una sola fecha

- GIVEN una edición/categoría con varias fechas y decenas de partidos en
  total
- WHEN se hace `GET` al fixture sin `idFecha`
- THEN la respuesta contiene únicamente los partidos de una fecha (la
  relevante), no los partidos de todas las fechas de la edición/categoría

(Trazabilidad: issue #98, criterio 8 — "no regresión / compatibilidad".)

## Ambigüedades resueltas en este spec

- El dominio `portal-publico` no tiene canónico todavía; este spec asume que
  al archivar, sus requirements se fusionan junto a los de
  `goleadores-filtros-url` y `navegacion-publica-persistente` sin colisión de
  nombres (todos describen superficies distintas de la vista pública).
- "Reset de filtros al cambiar de categoría" se especifica como reset total
  (`idFecha`, `estado`, `equipo`), no parcial, siguiendo la decisión ya
  cerrada por el orquestador.
- El contrato `idFecha=todas` se documenta como valor de string explícito del
  mismo parámetro `idFecha`, no como un parámetro nuevo.
- El self-heal de `#fecha-N` se especifica como comportamiento observable en
  el cliente (reemplaza la entrada de historial, no agrega una nueva), sin
  atarlo a ninguna implementación de JS en particular.
