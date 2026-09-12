# Delta for Portal Público — Seguir mi equipo (#99)

> Nota de estado del dominio: `openspec/specs/portal-publico/spec.md` (canónico) todavía
> no existe. Existen otras changes activas sin archivar sobre el mismo dominio
> (`fixture-explorador`, `goleadores-filtros-url`, `navegacion-publica-persistente`).
> Este spec agrega ÚNICAMENTE los requirements que introduce `seguir-equipo`
> (selector de equipo, endpoint `/equipo-seguido`, card de próximo/último partido
> en la home); no redefine goleadores, navegación compartida ni el fixture
> explorador. Al archivar, los requirements de esta change se fusionan al
> canónico sin colisión de nombres.

## ADDED Requirements

### Requirement: Selección de equipo sin autenticación

La home pública MUST exponer, cuando exista una edición activa resuelta y esa
edición tenga al menos un equipo, un selector de equipo que liste los equipos
de esa edición y permita filtrar la lista por texto sin generar peticiones de
red adicionales. Cuando no exista edición activa resuelta, el selector MUST
NOT renderizarse.

#### Scenario: Selector filtra en memoria sin requests adicionales [Playwright]

- GIVEN la home pública renderizada con una edición activa que tiene equipos
  cargados
- WHEN un visitante escribe un término parcial en el buscador del selector
- THEN la lista se filtra a los equipos cuyo nombre contiene ese término, sin
  disparar ninguna petición de red nueva

#### Scenario: Sin edición activa no hay selector [Pest]

- GIVEN la home pública renderizada sin ninguna edición activa resoluble
- WHEN se inspecciona la respuesta
- THEN no se renderiza el bloque de selector de equipo ni la card de equipo
  seguido

(Trazabilidad: criterio 1 — "selección de equipo sin autenticación".)

### Requirement: Persistencia local del equipo seguido

Al elegir un equipo del selector, el cliente MUST guardar en `localStorage`
únicamente el nombre del equipo elegido, sin ningún otro dato personal ni
identificador de sesión, bajo una clave estable y reconocible del dominio. Esa
selección MUST seguir disponible en visitas posteriores del mismo dispositivo
sin que el visitante tenga que volver a elegir el equipo.

#### Scenario: La selección sobrevive a recargar la página [Playwright]

- GIVEN un visitante que ya eligió un equipo en una visita anterior al mismo
  dispositivo
- WHEN recarga la home pública
- THEN la card de su equipo aparece automáticamente, sin que tenga que volver
  a seleccionarlo en el selector

#### Scenario: Solo se guarda el nombre del equipo [Playwright]

- GIVEN un visitante que elige un equipo en el selector
- WHEN se inspecciona el valor guardado en `localStorage` para la clave del
  equipo seguido
- THEN el valor contiene únicamente el nombre del equipo, sin datos
  personales ni identificadores de sesión

(Trazabilidad: criterio 2 — "persistencia local".)

### Requirement: Contrato del endpoint de equipo seguido

Una petición `GET` al endpoint de equipo seguido con los parámetros `nombre`
(nombre de equipo) e `idEdicion` (identificador de edición) MUST responder
siempre con estado `200`, incluso cuando el nombre no resuelva ningún equipo
en esa edición. La resolución del nombre MUST ser tolerante a diferencias de
mayúsculas/minúsculas y de espaciado respecto del nombre normalizado
almacenado. Cuando el nombre no resuelva un equipo en la edición indicada, el
cuerpo de la respuesta MUST indicar explícitamente que el equipo no está
disponible, sin usar un código de error HTTP para ese caso. Una petición sin
`nombre` o sin un `idEdicion` válido MUST responder con un error de
validación, distinto del caso "equipo no disponible".

#### Scenario: Equipo resuelto en la edición solicitada [Pest]

- GIVEN un equipo existente en una edición dada
- WHEN se pide el endpoint de equipo seguido con el nombre exacto de ese
  equipo y el `idEdicion` de esa edición
- THEN la respuesta tiene status `200` e indica que el equipo está disponible

#### Scenario: Nombre con diferente capitalización o espaciado resuelve igual [Pest]

- GIVEN un equipo cuyo nombre normalizado en base de datos es
  "Amigos F.c."
- WHEN se pide el endpoint con el nombre "amigos f.c." (minúsculas, mismo
  espaciado lógico)
- THEN la respuesta resuelve el mismo equipo que con el nombre normalizado

#### Scenario: Nombre que no existe en la edición pedida [Pest]

- GIVEN una edición sin ningún equipo con el nombre solicitado
- WHEN se pide el endpoint de equipo seguido con ese nombre y esa edición
- THEN la respuesta tiene status `200` e indica explícitamente que el equipo
  no está disponible

#### Scenario: Equipo existente en otra edición distinta a la pedida [Pest]

- GIVEN un equipo cuyo nombre existe en una edición A pero no en una edición B
- WHEN se pide el endpoint de equipo seguido con ese nombre y el `idEdicion`
  de la edición B
- THEN la respuesta tiene status `200` e indica que el equipo no está
  disponible en esa edición

#### Scenario: idEdicion inválido o inexistente es un error de validación [Pest]

- GIVEN una petición al endpoint de equipo seguido sin `idEdicion`, o con un
  `idEdicion` que no corresponde a ninguna edición existente
- WHEN se procesa la petición
- THEN la respuesta es un error de validación, no un estado de negocio "no
  disponible"

(Trazabilidad: criterio 8 — "endpoint siempre 200"; y contrato base para los
criterios 3-4-6.)

### Requirement: Próximo partido del equipo seguido

Cuando el equipo resuelto tenga al menos un partido no jugado con horario
futuro registrado en la edición consultada, la respuesta del endpoint de
equipo seguido MUST incluir el próximo partido de ese equipo: el rival, si el
equipo juega de local o visitante, y la fecha/horario de ese partido. Cuando
el equipo no tenga ningún partido futuro pendiente, el campo correspondiente
al próximo partido MUST ser explícitamente nulo, sin que eso constituya un
error.

#### Scenario: Equipo con un próximo partido pendiente [Pest]

- GIVEN un equipo con un partido no jugado y horario futuro registrado en la
  edición consultada
- WHEN se pide el endpoint de equipo seguido para ese equipo
- THEN la respuesta incluye el próximo partido con su rival, localía y
  fecha/horario

#### Scenario: Equipo sin próximos partidos pendientes [Pest]

- GIVEN un equipo cuya categoría ya finalizó su fixture, sin partidos futuros
  pendientes
- WHEN se pide el endpoint de equipo seguido para ese equipo
- THEN la respuesta indica explícitamente que no hay próximo partido, sin que
  la petición falle

(Trazabilidad: criterio 3 — "próximo partido".)

### Requirement: Último resultado del equipo seguido

Cuando el equipo resuelto tenga al menos un partido jugado registrado en la
edición consultada, la respuesta del endpoint de equipo seguido MUST incluir
el último resultado de ese equipo: el rival, si jugó de local o visitante, y
el marcador final. Cuando el equipo no tenga ningún partido jugado todavía, el
campo correspondiente al último resultado MUST ser explícitamente nulo, sin
que eso constituya un error, y es independiente de si el equipo tiene o no un
próximo partido pendiente.

#### Scenario: Equipo con un último resultado disponible [Pest]

- GIVEN un equipo con al menos un partido jugado registrado en la edición
  consultada
- WHEN se pide el endpoint de equipo seguido para ese equipo
- THEN la respuesta incluye el último resultado con su rival, localía y
  marcador

#### Scenario: Equipo sin ningún partido jugado ni programado [Pest]

- GIVEN un equipo existente en la edición consultada sin ningún partido
  cargado todavía
- WHEN se pide el endpoint de equipo seguido para ese equipo
- THEN la respuesta indica que el equipo está disponible, con próximo y
  último resultado ambos explícitamente nulos

(Trazabilidad: criterio 4 — "último resultado".)

### Requirement: Estados de la card de equipo seguido

La home pública MUST renderizar la card de equipo seguido en uno de estos
cuatro estados, mutuamente excluyentes, según la selección guardada y la
respuesta del endpoint: (a) sin selección guardada, la card MUST NOT
renderizarse; (b) selección disponible con próximo y/o último partido, la
card MUST mostrar la información disponible de cada uno de forma
independiente; (c) selección disponible sin próximo ni último partido, la
card MUST mostrar un mensaje indicando que todavía no hay partidos cargados
para ese equipo, junto con los accesos directos; (d) selección guardada pero
no disponible en la edición activa, la card MUST mostrar un mensaje explícito
de que el equipo no participa en esa edición junto con una acción para
cambiar o quitar la selección.

#### Scenario: Sin selección guardada no hay card [Pest]

- GIVEN un visitante sin ningún equipo guardado en `localStorage`
- WHEN se renderiza la home pública
- THEN no se muestra ninguna card de equipo seguido

#### Scenario: Equipo disponible con próximo y último partido [Playwright]

- GIVEN un visitante con un equipo guardado que tiene próximo y último
  partido en la edición activa
- WHEN se carga la home pública
- THEN la card muestra tanto el próximo partido como el último resultado

#### Scenario: Equipo disponible sin ningún partido cargado [Playwright]

- GIVEN un visitante con un equipo guardado que no tiene próximo ni último
  partido en la edición activa
- WHEN se carga la home pública
- THEN la card muestra el mensaje de que todavía no hay partidos cargados
  para ese equipo, junto con los accesos directos a fixture y tabla de
  posiciones

#### Scenario: Equipo guardado no disponible en la edición activa [Playwright]

- GIVEN un visitante con un equipo guardado de una edición anterior que no
  participa en la edición activa actual
- WHEN se carga la home pública con la edición activa distinta
- THEN la card muestra el mensaje explícito de que el equipo no participa en
  esa edición, junto con la acción para cambiar o quitar la selección

(Trazabilidad: criterio 5 — "estados de la card".)

### Requirement: Accesos directos a fixture y tabla de posiciones

Cuando la card de equipo seguido esté disponible (con o sin partidos
cargados), MUST incluir un enlace al fixture filtrado por ese equipo y un
enlace a la tabla de posiciones de la categoría de ese equipo, ambos
funcionales sin requerir recarga adicional de la home ni parámetros extra que
el visitante deba completar.

#### Scenario: Acceso directo al fixture del equipo [Playwright]

- GIVEN la card de un equipo disponible en la edición activa
- WHEN se sigue el enlace de fixture de la card
- THEN se llega a la vista de fixture ya filtrada por el nombre de ese equipo
  y la edición activa

#### Scenario: Acceso directo a la tabla de posiciones de la categoría del equipo [Playwright]

- GIVEN la card de un equipo disponible en la edición activa
- WHEN se sigue el enlace de tabla de posiciones de la card
- THEN se llega a la tabla de posiciones de la categoría de ese equipo en la
  edición activa

(Trazabilidad: criterio 6 — "accesos directos".)

### Requirement: Cambiar o quitar el equipo seguido

La card de equipo seguido MUST ofrecer una acción para quitar la selección
guardada, que borra la entrada correspondiente de `localStorage` y vuelve al
estado sin card, y MUST permitir reemplazar la selección por otro equipo
reabriendo el selector, sin necesidad de recargar manualmente la página.

#### Scenario: Quitar el equipo seguido limpia la selección [Playwright]

- GIVEN un visitante con una card de equipo seguido visible
- WHEN acciona "Quitar" en la card
- THEN la card desaparece, el selector vuelve a estar disponible y la entrada
  correspondiente ya no existe en `localStorage`

#### Scenario: Cambiar de equipo reemplaza la selección anterior [Playwright]

- GIVEN un visitante con un equipo ya guardado
- WHEN elige un equipo distinto desde el selector
- THEN la card se actualiza para reflejar el nuevo equipo y `localStorage`
  contiene el nombre del nuevo equipo, no el anterior

(Trazabilidad: criterio 7 — "cambiar/quitar favorito".)

### Requirement: Compatibilidad con la navegación pública y sin sesión requerida

El bloque de selector y card de equipo seguido MUST funcionar completamente
sin sesión iniciada y MUST NOT alterar el comportamiento observable de la
navegación pública compartida: no debe introducir un elemento `<nav>`
adicional ni usar `aria-current="page"` fuera de la navegación existente.

#### Scenario: Visitante sin sesión usa el selector y la card [Pest]

- GIVEN un visitante sin sesión iniciada
- WHEN interactúa con el selector de equipo y consulta el endpoint de equipo
  seguido
- THEN todas las respuestas son exitosas sin requerir autenticación

#### Scenario: La navegación pública no se ve alterada [Pest]

- GIVEN la home pública renderizada con el bloque de selector y card de
  equipo seguido
- WHEN se ejecutan las pruebas existentes de navegación pública que cuentan
  ocurrencias exactas de `aria-current="page"` y de la navegación principal
- THEN esas pruebas siguen pasando sin modificarse

(Trazabilidad: criterio 8 — "compatibilidad".)

## Ambigüedades resueltas en este spec

- **Colisión de nombre entre categorías** (cerrada por el orquestador): el
  endpoint resuelve de forma determinística el primer equipo que matchea el
  nombre en la edición pedida quedando documentado como limitación conocida;
  este spec no exige persistir `idCategoria` en `localStorage` para
  desambiguar.
- **Equipo sin próximos partidos** (cerrada por el orquestador): "próximo
  nulo" es un estado válido de primera clase, no un error ni un caso a
  ocultar; ver Requirement "Próximo partido del equipo seguido".
- **Código de estado del endpoint** (cerrado por el orquestador, precedente
  `ControladorPlanillaPublica`): el endpoint responde siempre `200` para el
  flujo de negocio (incluido "no disponible"); solo un error de validación de
  parámetros de entrada (`idEdicion` ausente o inexistente) se trata distinto
  del caso "no disponible".
- El dominio `portal-publico` no tiene canónico todavía; este spec asume que,
  al archivar, sus requirements se fusionan junto a los de
  `fixture-explorador`, `goleadores-filtros-url` y
  `navegacion-publica-persistente` sin colisión de nombres, dado que cada uno
  describe una superficie distinta de la vista pública.
- El contrato JSON exacto (forma de `disponible`, `equipo`, `proximo`,
  `ultimo`, `links`) se documenta en `proposal.md` §2.1; este spec fija el
  comportamiento observable (siempre 200, matching tolerante, independencia
  de `proximo`/`ultimo`) sin repetir el shape completo, que es un detalle de
  implementación derivado directamente de estos requirements.
