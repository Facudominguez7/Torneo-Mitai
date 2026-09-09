# Portal Público (Tabla de Goleadores) Specification

## Purpose

La vista pública de tabla de goleadores (`route('tabla-goleadores')`) debe ser
URL-first: todo su estado de filtrado, orden y paginación se resuelve desde la
query string de la petición, sin depender de sesión ni de una petición previa.
Esto permite compartir enlaces, recargar la página, abrir en otro
navegador/incógnito y usar Atrás/Adelante del navegador de forma coherente.

## Requirements

### Requirement: Resolución de estado desde la URL

El sistema DEBE resolver `idEdicion`, `idCategoria`, `sort`, `dir`,
`search_value` y `page` exclusivamente desde los query params de la petición
GET a `tabla-goleadores`, sin leer ni escribir la clave de sesión
`tabla_goleadores_filtros`.

#### Scenario: Filtro por edición y categoría vía query string

- GIVEN una edición y categoría existentes con jugadores cargados en
  `TablaGoleador`
- WHEN se hace `GET /tabla-de-goleadores?idEdicion={id}&idCategoria={id}`
  sin cookie de sesión previa
- THEN la respuesta contiene los goleadores de esa edición y categoría

#### Scenario: Búsqueda por nombre case-insensitive vía query string

- GIVEN un goleador registrado con nombre "LuCaS" y apellido "PereZ"
- WHEN se hace `GET` a `tabla-goleadores` con `search_value=lucas` (y
  `idEdicion`/`idCategoria` correspondientes)
- THEN la respuesta contiene "PereZ LuCaS" y no contiene goleadores que no
  matchean el término de búsqueda

(Trazabilidad: cubre el test rojo
`tests/Feature/Panel/TablaGoleadoresBusquedaTest.php::'aplica busqueda case insensitive por nombre de jugador'`)

#### Scenario: Filtro por búsqueda encuentra un resultado fuera de la primera página

- GIVEN 30 goleadores que llenan la primera página y 1 goleador adicional
  ("Ulises Objetivo") que solo aparece filtrando antes de paginar
- WHEN se hace `GET` a `tabla-goleadores` con `search_value=ulises` (y
  `idEdicion`/`idCategoria` correspondientes)
- THEN la respuesta contiene "Objetivo Ulises"

(Trazabilidad: cubre el test rojo
`tests/Feature/Panel/TablaGoleadoresBusquedaTest.php::'encuentra un jugador fuera de la primera pagina al filtrar antes de paginar'`)

### Requirement: Ausencia de redirect en peticiones no-AJAX

Una petición GET no-AJAX a `tabla-goleadores` con query string DEBE responder
`200` y renderizar la vista con el estado solicitado, sin `redirect()` hacia
la ruta sin parámetros.

#### Scenario: GET con query string responde 200

- GIVEN una edición y categoría existentes
- WHEN se hace `GET /tabla-de-goleadores?idEdicion={id}&idCategoria={id}`
  sin cookie de sesión previa
- THEN la respuesta tiene status `200` (no `302`)

### Requirement: Independencia de sesión

El contenido renderizado para un mismo conjunto de query params DEBE ser
idéntico sin importar si el cliente tiene o no una sesión previa con
`tabla_goleadores_filtros` almacenada.

#### Scenario: Mismo query, con y sin sesión previa

- GIVEN un cliente A sin cookie de sesión y un cliente B con una sesión
  previa que contiene otros filtros guardados en `tabla_goleadores_filtros`
- WHEN ambos hacen `GET` a `tabla-goleadores` con el mismo query string
- THEN ambas respuestas muestran el mismo contenido de goleadores

### Requirement: Estado completo en links de orden

Los 4 links de orden de la tabla (nombre, equipo, categoría, goles) DEBEN
preservar `idEdicion`, `idCategoria` y `search_value` del estado actual, y
DEBEN resetear la paginación (sin `page`, o `page=1`).

#### Scenario: Link de orden conserva filtros activos

- GIVEN una petición a `tabla-goleadores` con `idEdicion`, `idCategoria` y
  `search_value` activos
- WHEN se inspecciona el link de orden por "goles" en la respuesta renderizada
- THEN el link incluye `idEdicion`, `idCategoria` y `search_value` con los
  mismos valores de la petición actual, y no incluye un `page` mayor a 1

### Requirement: Estado completo en links de paginación

El paginador DEBE preservar todos los filtros activos (`idEdicion`,
`idCategoria`, `search_value`, `sort`, `dir`) en cada link de página.

#### Scenario: Link de paginación conserva filtros activos

- GIVEN una petición a `tabla-goleadores` con más resultados que una página y
  con `idEdicion`, `idCategoria`, `search_value`, `sort` y `dir` activos
- WHEN se inspecciona un link de página distinta a la actual en la respuesta
  renderizada
- THEN el link incluye `idEdicion`, `idCategoria`, `search_value`, `sort` y
  `dir` con los mismos valores de la petición actual

### Requirement: Actualizaciones AJAX preservan el flujo existente sin recarga completa

Las actualizaciones vía AJAX (búsqueda con debounce, filtro de categoría,
orden, paginación) DEBEN seguir actualizando únicamente el contenedor de
resultados, sin recargar la página completa, y sin que el input de búsqueda
pierda el foco mientras el usuario escribe.

#### Scenario: Cambio de filtro por AJAX actualiza solo el contenedor de resultados

- GIVEN la vista `tabla-goleadores` cargada en el navegador con resultados
  visibles
- WHEN el usuario cambia la categoría seleccionada o escribe en el campo de
  búsqueda (tras el debounce) o hace click en un link de orden o de
  paginación
- THEN el contenedor de resultados se actualiza con los nuevos datos sin
  recargar la página, y el input de búsqueda conserva el foco si el cambio
  se originó en él

(Nota: loading/error states, mensaje de vacío con el término de búsqueda y
`aria-live` quedan fuera de alcance — issue #102.)

### Requirement: Sincronización de History API

Cada actualización confirmada por AJAX (post-debounce en el caso de
búsqueda) DEBE apilar una entrada de historial del navegador vía
`history.pushState` reflejando el estado completo en la URL. Un evento
`popstate` DEBE restaurar el estado desde la URL resultante y volver a pedir
los resultados correspondientes.

#### Scenario: Cambio de filtro por AJAX apila una entrada de historial

- GIVEN la vista `tabla-goleadores` cargada con un estado inicial en la URL
- WHEN el usuario cambia un filtro (categoría, orden o página) y la
  actualización AJAX resuelve
- THEN la URL visible del navegador refleja el nuevo estado completo y se
  agregó una nueva entrada al historial (no se reemplazó la anterior)

#### Scenario: Atrás del navegador restaura el estado previo

- GIVEN el usuario cambió un filtro por AJAX y el navegador apiló una nueva
  entrada de historial
- WHEN el usuario navega con el botón Atrás del navegador
- THEN el evento `popstate` dispara una solicitud de resultados con los
  parámetros de la URL restaurada y el contenedor de resultados se actualiza
  en consecuencia

(Nota: no hay `pushState` por cada tecla en el input de búsqueda — solo al
resolver la actualización post-debounce.)

### Requirement: No regresión de otras vistas públicas

Las vistas públicas `fixture`, `tabla-posiciones`, `tabla-vallas` e
`instancias-finales` NO DEBEN cambiar su comportamiento observable como
consecuencia de este cambio.

#### Scenario: Vistas públicas hermanas no afectadas

- GIVEN las rutas públicas `fixture`, `tabla-posiciones`, `tabla-vallas` e
  `instancias-finales` con sus tests existentes en verde antes del cambio
- WHEN se aplican los cambios de este spec (acotados a `tablaGoleadores` y
  sus vistas)
- THEN los tests existentes de esas otras vistas públicas siguen en verde sin
  modificaciones
