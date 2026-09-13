# Delta for Portal Público — Home reestructurada (#100)

> Nota de estado del dominio: `openspec/specs/portal-publico/spec.md` (canónico)
> todavía no existe. Existen deltas hermanas sin archivar (`fixture-explorador`,
> `navegacion-publica-persistente`, `goleadores-filtros-url`) que ya tocan este
> dominio sobre otras vistas (`fixture`, navegación compartida,
> `tabla-goleadores`). Este spec agrega ÚNICAMENTE los requirements que
> introduce `home-reestructurada` sobre `welcome.blade.php`,
> `ControladorHome` y el componente compartido `<x-tarjeta-partido>`; no
> redefine los requirements de esas changes hermanas. Al archivar, las cuatro
> deltas deben fusionarse en el canónico sin colisión de nombres.

## ADDED Requirements

### Requirement: Jerarquía de bloques de la home

La vista de home (`welcome.blade.php`) MUST renderizar, en este orden, los
siguientes bloques funcionales tras las alertas de sesión: (1) hero mínimo con
selector de edición, (2) bloque de información viva de la fecha relevante, (3)
bloque de seguimiento del equipo favorito, (4) accesos secundarios
(Posiciones, Goleadores, Finales, Vallas), (5) carrusel de categorías, (6)
historial reducido a links. El orden relativo entre estos bloques MUST
mantenerse independientemente de si existe o no un equipo favorito
seleccionado.

#### Scenario: Orden de bloques en el DOM

- GIVEN la home renderizada para una edición con partidos cargados
- WHEN se inspecciona el orden de los contenedores principales del `<main>`
- THEN el bloque de información viva aparece antes que el bloque de
  seguimiento de equipo, que aparece antes que los accesos secundarios, que
  aparecen antes que el carrusel de categorías, que aparece antes que el
  historial

(Trazabilidad: issue #100, criterio 1 — "jerarquía de la home".
Verificación: Pest sobre el HTML renderizado con `assertSeeInOrder()`, más
Playwright en 390px y 1280px para confirmar la jerarquía visual — AGENTS.md §8.)

### Requirement: Bloque de información viva de la fecha relevante

El controlador de home MUST resolver la fecha relevante de la edición activa
reutilizando `FixtureExploradorService::resolverFechaRelevante()` (el mismo
criterio que usa la vista de fixture, sin duplicar el predicado `jugado=0 OR
NULL` + `horario >= now()`), y la vista MUST renderizar el listado completo de
los partidos de esa fecha (sin límite artificial de cantidad), cada uno con
`<x-tarjeta-partido>`, junto con un link a `route('fixture', ['idEdicion' =>
...])`. Cuando la fecha relevante resuelta tenga al menos un partido no jugado
con horario futuro, la etiqueta del bloque MUST ser "Próximos partidos"; en
cualquier otro caso (fecha histórica, última jugada, o fallback por ausencia
de partidos futuros) la etiqueta MUST ser "Fecha pendiente". El bloque NO MUST
filtrar los partidos por categoría ni por equipo favorito.

#### Scenario: Etiqueta "Próximos partidos" con partidos futuros

- GIVEN una edición cuya fecha relevante tiene al menos un partido no jugado
  con `horario_datetime` futuro
- WHEN se renderiza la home para esa edición
- THEN el bloque de información viva muestra la etiqueta "Próximos partidos"

(Verificación: Pest — `it('muestra "Próximos partidos" cuando la fecha
relevante tiene partidos futuros')`.)

#### Scenario: Etiqueta "Fecha pendiente" sin partidos futuros

- GIVEN una edición cuya fecha relevante es histórica (todos sus partidos
  jugados) o no tiene partidos con horario futuro
- WHEN se renderiza la home para esa edición
- THEN el bloque de información viva muestra la etiqueta "Fecha pendiente" y
  igualmente lista los partidos de esa fecha (no queda vacío)

(Verificación: Pest — `it('muestra "Fecha pendiente" cuando la fecha relevante
es histórica o sin horario')`. Este es el camino real hoy: la edición 10 tiene
0 partidos futuros y 32 pendientes históricos de 137, según explore.md §6.)

#### Scenario: Listado completo de la fecha relevante, sin recorte

- GIVEN una fecha relevante con más de 6 partidos cargados
- WHEN se renderiza el bloque de información viva
- THEN se muestran todos los partidos de esa fecha, no un subconjunto limitado

(Verificación: Pest — `it('lista todos los partidos de la fecha relevante, no
un subconjunto')`.)

#### Scenario: Edición sin partidos no rompe la home

- GIVEN una edición sin ningún partido cargado
- WHEN se renderiza la home para esa edición
- THEN la respuesta es exitosa (sin error de variable indefinida) y el bloque
  de información viva se muestra sin partidos, sin excepción

(Verificación: Pest — `it('no rompe cuando la edición no tiene partidos')`.)

#### Scenario: Link a fixture completo desde el bloque vivo

- GIVEN el bloque de información viva renderizado para una edición con
  `idEdicion=X`
- WHEN se inspecciona el link "Ver todos los partidos" del bloque
- THEN el link apunta a `route('fixture', ['idEdicion' => X])`

(Trazabilidad: issue #100, criterio 2 — "bloque de información viva".
Verificación: Pest con aserciones sobre el `href` renderizado.)

### Requirement: Hero mínimo sin prosa ni CTAs duplicados

El hero de la home MUST mostrar únicamente: el nombre de la edición activa
como único `<h1>`, el `<form>`/`<select>` de selección de edición, y como
máximo un CTA primario y un CTA secundario. El hero NO MUST contener párrafos
descriptivos, badges decorativos sin link, ni un segundo acceso a
`route('fixture', ...)` en ninguna otra parte del hero. En toda la página, el
acceso a `fixture` (edición completa) MUST aparecer una única vez fuera de la
navegación compartida.

#### Scenario: Hero sin párrafo descriptivo ni badges

- GIVEN la home renderizada para cualquier edición
- WHEN se inspecciona el bloque de hero
- THEN no contiene ningún `<p>` de prosa descriptiva ni badges decorativos sin
  link (los eliminados: kicker "fútbol formativo", párrafo de 33 palabras, 3
  badges "Actualización en vivo"/"Comunidad Mita'í"/"Formación y valores")

(Verificación: Pest — `assertDontSee()` sobre los textos eliminados listados
en la propuesta.)

#### Scenario: Un único acceso a fixture completo fuera de la navegación

- GIVEN la home renderizada completa (hero + bloque de información viva +
  accesos secundarios)
- WHEN se cuentan los links cuyo destino es `route('fixture', [...])` sin
  `idFecha` ni `idCategoria` adicionales, excluyendo el enlace de la
  navegación compartida
- THEN existe exactamente uno (el del bloque de información viva)

(Trazabilidad: issue #100, criterio 3 — "hero mínimo, sin CTAs duplicados".
Verificación: Pest contando ocurrencias del `href` de fixture en el HTML
renderizado.)

### Requirement: Tarjeta de partido compartida entre home y fixture

El componente anónimo `<x-tarjeta-partido>` (`resources/views/components/
tarjeta-partido.blade.php`, `@props(['partido', 'interzonal' => false])`)
MUST ser el único markup usado para renderizar una tarjeta de partido tanto en
`welcome.blade.php` como en `layouts/fixture.blade.php`, produciendo el mismo
HTML para el mismo partido en ambas vistas (mismos data-attributes
`data-jugado`, `data-horario`, `data-local`, `data-visitante`, mismo `id="
partido-{{ $p->id }}"`).

#### Scenario: Fixture no sufre regresión visual tras la extracción

- GIVEN el fixture renderizado con partidos estándar e interzonales antes y
  después de reemplazar el markup inline por `<x-tarjeta-partido>`
- WHEN se comparan ambas capturas en 390px y 1280px
- THEN el HTML generado y la apariencia visual son idénticos

(Verificación: tests existentes de `tests/Feature/*Fixture*.php` en verde
[regresión, no test nuevo] + Playwright comparativo antes/después —
AGENTS.md §8.)

#### Scenario: Misma tarjeta se renderiza igual en home y en fixture

- GIVEN un partido con los mismos datos renderizado una vez en la home (bloque
  de información viva) y una vez en el fixture
- WHEN se comparan ambos fragmentos de HTML generados por
  `<x-tarjeta-partido>`
- THEN el markup es idéntico salvo por el contexto de layout que lo envuelve

(Trazabilidad: issue #100 — "componente compartido, sin duplicación de
markup". Verificación: Playwright con capturas de la tarjeta en ambos
contextos.)

### Requirement: Bloque de seguimiento de equipo favorito reubicado, no reescrito

### Requirement: Selección de equipo consciente de categoría (enmienda post-#99)

La selección del equipo seguido MUST distinguir la categoría del equipo: el
selector muestra la categoría junto a cada equipo, la selección persiste
`idCategoria` junto al nombre, y el endpoint resuelve el equipo exacto
(categoría + edición) cuando recibe `idCategoria`.

#### Scenario: Equipos con el mismo nombre en categorías distintas

- GIVEN dos categorías de la misma edición con un equipo homónimo
- WHEN el visitante despliega el selector
- THEN cada opción muestra su categoría de forma visible y seleccionable

#### Scenario: La selección resuelve el equipo de la categoría elegida

- GIVEN el visitante selecciona el equipo homónimo de una categoría específica
- WHEN la home consulta el endpoint con nombre + idCategoria
- THEN la card resuelve el equipo de ESA categoría (no el primer match por id)


El bloque de seguimiento de equipo favorito (`#seguir-equipo-bloque`,
introducido por la change `seguir-equipo-vista`) MUST conservar, tras su
reubicación dentro de la nueva jerarquía de la home, los mismos identificadores
de contenedor y el mismo contrato de datos con `welcome.js` que tenía antes de
la reubicación.

#### Scenario: El bloque de favorito sigue siendo hidratado por welcome.js

- GIVEN `welcome.js` sin cambios y el bloque `#seguir-equipo-bloque` reubicado
  en su nueva posición dentro de `welcome.blade.php`
- WHEN se carga la home en el navegador con un equipo favorito guardado
- THEN el bloque se hidrata y muestra el equipo favorito exactamente igual que
  antes de la reubicación

(Trazabilidad: issue #100, criterio 5 — "favorito integrado". Verificación:
Playwright cargando la home con `localStorage` pre-poblado, en 390px y 1280px.)

### Requirement: Home sin prosa institucional ni código muerto

La vista de home NO MUST contener la prosa institucional, kickers y
descripciones de tarjeta identificados como candidatos a eliminación en la
propuesta (~290 palabras: kicker y párrafo de hero, badges, card de edición,
kicker/párrafo de accesos directos, kickers y descripciones de historial,
kicker/párrafo de comunidad e institucional). El controlador de home
(`ControladorHome::index()`) NO MUST calcular ni pasar a la vista las
variables `$roles` ni `$idCategoria`, y NO MUST ejecutar la consulta
`User::pluck('rol')`.

#### Scenario: Prosa eliminada no aparece en el HTML renderizado

- GIVEN la home renderizada para cualquier edición
- WHEN se inspecciona el HTML de respuesta
- THEN no contiene ninguno de los textos de prosa listados en la propuesta
  como eliminados

(Verificación: Pest — `assertDontSee()` por cada texto eliminado.)

#### Scenario: El controlador no consulta todos los usuarios ni pasa variables muertas

- GIVEN una petición `GET` a la ruta de home
- WHEN se ejecuta `ControladorHome::index()`
- THEN la vista se renderiza sin recibir `$roles` ni `$idCategoria`, y no se
  ejecuta ninguna consulta `User::pluck(...)` durante la petición

(Trazabilidad: issue #100, criterios 6-7 — "sin texto innecesario" y "sin dead
code". Verificación: Pest inspeccionando las variables compartidas de la vista
[`View::composer`/`assertViewHas` negativo] y, si aplica, aserciones sobre el
número de queries ejecutadas con `DB::listen` o `assertQueryCountLessThan`
equivalente del proyecto.)

### Requirement: Compatibilidad con navegación pública y acceso uniforme al home

El cambio de jerarquía de la home NO MUST alterar el comportamiento observable
de la navegación pública compartida (`layouts/navigation.blade.php`): no
introduce nuevos elementos `<nav>` propios ni nuevos atributos
`aria-current` fuera de los que ya gestiona esa navegación. La home renderizada
MUST ser funcionalmente equivalente para un visitante sin sesión y para un
usuario con sesión (autenticado o administrador), en cuanto a los bloques
descritos en este spec.

#### Scenario: Suite de navegación pública sigue en verde

- GIVEN la suite de tests de navegación pública existente (`NavegacionPublica
  Test`) en verde antes de este cambio
- WHEN se aplican los cambios de jerarquía de la home descritos en este spec
- THEN esa suite sigue en verde sin modificaciones, con el mismo conteo de
  `aria-current` que antes del cambio

(Verificación: Pest — ejecutar `NavegacionPublicaTest` sin modificarlo, como
regresión.)

#### Scenario: Home funcional para guest y para admin

- GIVEN un visitante sin sesión y un usuario administrador
- WHEN cada uno visita la home reestructurada
- THEN ambos ven los mismos bloques funcionales descritos en este spec (hero,
  información viva, seguimiento de equipo, accesos secundarios, carrusel,
  historial), sin que el rol oculte o reordene esos bloques

(Trazabilidad: issue #100, criterio 8 — "compatibilidad". Verificación: Pest
con `actingAs()` para admin y sin autenticación para guest, más Playwright en
390px y 1280px para ambos casos.)

## Ambigüedades resueltas en este spec

- El bloque de seguimiento de equipo favorito se especifica como **reubicado,
  no reescrito**: este spec no redefine su contrato de datos ni su
  comportamiento interno (eso corresponde a la change `seguir-equipo-vista`),
  solo exige que sobreviva intacto a la reubicación.
- Vallas se incluye explícitamente como cuarto chip de accesos secundarios
  (decisión ya cerrada en la propuesta), junto con Posiciones, Goleadores y
  Finales.
- El bloque de historial reducido a links no se especifica con requirement
  propio adicional: queda cubierto por "Home sin prosa institucional ni código
  muerto" (elimina kickers y descripciones) y por "Jerarquía de bloques de la
  home" (posición relativa al final).
- No se especifica requirement para el bloque de comunidad/institucional
  recortado ni para su eventual eliminación: la propuesta lo marca como
  candidato adicional fuera del pedido original de #100, pendiente de
  confirmación explícita del dueño en la fase de tareas.
