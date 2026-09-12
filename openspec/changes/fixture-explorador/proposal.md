# Propuesta — Fixture como explorador (#98)

Fase: SDD proposal. Basada en `openspec/changes/fixture-explorador/explore.md` (leído completo) y en las decisiones ya cerradas por el usuario (ver sección 0). No se modifica código de aplicación en esta fase.

## 0. Decisiones ya cerradas (no se reabren)

- Default = **fecha relevante**, con chip **"Todas"** que reproduce el modo actual (todos los partidos de la edición/categoría).
- Enlaces con `idFecha` explícito siempre reproducen esa fecha; enlaces legacy sin `idFecha` aterrizan en la fecha relevante.
- Mobile-first, gramática visual = chips (patrón de `tabla-posiciones`) + barra sticky de fechas ya existente.
- Términos de dominio en español, `route()` con params, URL-first.

## 1. Intent

Cerrar #98: el fixture público hoy carga sin filtro de fecha por defecto (`~137` partidos de una edición en una sola página, sin paginar), no expone ningún control de categoría/estado/fecha aunque el hero los promete, y no permite buscar un equipo sin scrollear todo. La propuesta convierte `layouts/fixture.blade.php` en un explorador: fecha relevante por defecto, navegación por chips (fecha, categoría, estado), buscador de equipo server-side multi-fecha, y elimina el hack de JS que hoy simula esto con `scrollIntoView`.

No se toca paginación de resultados jugados/tablas de posiciones, ni el sistema de planillas públicas, ni las tarjetas de partido (su HTML/estilos quedan intactos).

## 2. Decisiones técnicas (con justificación)

### 2.1 Fecha relevante — cálculo

Definición server-side (reemplaza el hack de JS que hace exactamente esto en el cliente):

1. La fecha dueña del partido no jugado con `horario_datetime` futuro más próximo (`MIN(horario_datetime)` entre `jugado=0/null AND horario_datetime >= now()`).
2. Si no hay partidos futuros: la fecha dueña del último partido jugado (`MAX(horario_datetime)` entre `jugado=1`).
3. Si la categoría/edición no tiene partidos: la primera fecha por `id` asc.

Justificación: es la misma lógica que ya vive en el JS (`fixture.blade.php`, sección "relevant date hack"), movida al controlador para que sea la fuente de verdad tanto del render inicial como de los chips. Se calcula sobre `partidos.horario_datetime` porque `fechas` no tiene columna de fecha/orden (confirmado en el explore); no hay alternativa.

### 2.2 Orden de fechas (chips)

Orden por **`MIN(horario_datetime)` ascendente** de cada fecha (fallback `id asc` para una fecha sin partidos cargados todavía). No se usa `id desc` (el orden actual del controlador).

Justificación: `id desc` es orden de inserción, no calendario — el explore señala que hoy la barra muestra "más nueva primero", lo cual lee al revés para una agenda. Con chips en orden calendario, "fecha relevante" queda visualmente en el medio/borde correcto del strip y "anterior/siguiente" (si se agregara después) sería trivial de derivar por posición en el array ya ordenado.

### 2.3 Prev/Next explícitos: NO se agregan

Con 6 fechas por categoría (dato real, edición 10) el chip strip horizontal-scroll ya cubre la navegación completa en una sola fila visible/scrolleable; agregar flechas prev/next duplicaría la misma acción con más superficie de UI y más casos borde (fecha relevante en el extremo del strip). Se documenta como mejora futura si una edición crece a >10-12 fechas por categoría; no aplica hoy.

### 2.4 Categoría — chips

Se agregan chips de categoría reutilizando la gramática exacta de `tabla-posiciones.blade.php` (`route('fixture', ['idEdicion'=>..,'idCategoria'=>$c->id])`, mismas clases activo/inactivo `border-[#f7f280]/...`). `$categorias` ya llega a la vista y hoy no se usa — es la fuente de datos, cero query nueva. Al cambiar de categoría el resto de filtros (`idFecha`, `estado`) se **resetea** (una categoría distinta tiene su propio conjunto de fechas — confirmado en el explore: `fechas.idCategoria` filtra o es `NULL` compartida), evitando arrastrar un `idFecha` que no pertenece a la nueva categoría.

### 2.5 Estado — chips

Chips `Todos | Próximos | Finalizados` que exponen el param `estado` ya soportado por el controlador. Usan el idiom `$queryBase = array_filter(request()->except([...]))` + `array_merge($queryBase, ['estado' => X])` (mismo patrón de `tabla-vallas.blade.php:107` y `goleadores-resultados.blade.php:7-12`), así preservan `idEdicion`/`idCategoria`/`idFecha`/`equipo` al cambiar de estado.

### 2.6 Buscador de equipo — mecanismo

**Server-side, param `equipo`, GET, ignora `idFecha` y agrupa resultados por fecha cuando está activo.**

Mecanismo exacto:
- Nuevo `where` en ambas ramas de la query existente: `->when($equipo, fn($q) => $q->where(fn($sub) => $sub->where('el.nombre', 'like', '%'.$equipoEscapado.'%')->orWhere('ev.nombre', 'like', '%'.$equipoEscapado.'%')))`.
- Escape de comodines `%`/`_`/`\` del término antes de interpolarlo en el patrón `LIKE` (vía `addcslashes($termino, '%_\\')` + `ESCAPE '\\'` o el helper equivalente de query builder) — no es una vulnerabilidad de inyección SQL (el binding ya parametriza el valor), pero sin escape un usuario que busca `%` o `_` literal en el nombre de un equipo obtiene coincidencias falsas.
- Cuando `equipo` está presente, la query **ignora `idFecha`** (se comporta como la rama "sin fecha" del controlador actual, que ya trae todos los partidos de la edición/categoría) y aplica `estado` igual que hoy. La vista agrupa por `idFechas` y renderiza **solo las fechas con matches** (no las 6 completas).
- Justificación: el criterio de aceptación es explícito ("buscar equipo sin recorrer todo el fixture"); si el default renderiza una sola fecha, un filtro client-side sobre `data-local`/`data-visitante` (que ya existe en cada card) solo encontraría partidos de esa fecha — no cumple el criterio. Server-side con este param es la única opción que lo cumple sin duplicar datos al cliente.
- Mecanismo de disparo: `<input type="search" name="equipo">` dentro de un `<form method="GET">` que preserva `idEdicion`/`idCategoria`/`estado` como hidden inputs (mismo patrón que `Panel/tabla_goleador/index.blade.php:19-30`), sin JS nuevo más allá de un debounce opcional de navegación (`setTimeout` + `location.assign`, vanilla, sin Alpine ni dependencias nuevas) — no se replica la arquitectura AJAX/partial de `tabla-goleadores` porque eso agrega un endpoint y una vista parcial nuevos que el issue no pide y que empujarían el diff bien por encima del presupuesto de 400 líneas.

### 2.7 Contract de "Todas" en la URL

**`idFecha=todas`** (valor string explícito del mismo param), no un param nuevo (`vista=todas`).

Justificación: reutiliza el único punto de verdad que el controlador ya lee (`$request->idFecha`), evita mantener dos params que puedan quedar inconsistentes (`idFecha=5&vista=todas`), y es exactamente la opción que ya se sugirió como preferida. Contract:
- Ausencia de `idFecha` ⇒ fecha relevante (nuevo default).
- `idFecha=<id numérico>` ⇒ esa fecha (comportamiento ya existente, sin cambios).
- `idFecha=todas` ⇒ todos los partidos de la edición/categoría (comportamiento actual sin `idFecha`, preservado 1:1 como escape hatch).
- Enlaces legacy (sin `idFecha`) cambian de significado — hoy "todas", después "relevante" — y es el cambio de comportamiento intencional que pide el issue; queda mitigado porque "Todas" es un tap de distancia (chip siempre visible) y ningún enlace del repo pasaba `idFecha` (confirmado en el explore, tabla de "incoming links").

**Fragmentos `#fecha-N` legacy**: se acepta la posible ruptura sin redirect automático. Nadie linkea con `#fecha-N` en el repo (no aparece en ningún `route()` ni notificación); es solo un riesgo teórico de un usuario que copió la URL de la barra de direcciones. Agregar un redirect client-side (`if (location.hash.startsWith('#fecha-')) location.assign(...)`) es una mitigación barata si se prioriza, pero no es requisito de este change — se deja como ítem de seguimiento, no bloquea la propuesta.

### 2.8 DOM y render

- Default: una fecha (~12-30 tarjetas según categoría, dato real edición 10: 12/19/20/28/28/30 por fecha) en vez de ~137.
- `idFecha=todas`: igual que hoy, sin límite (paginar `partidos` está fuera de alcance de #98).
- Buscador activo: solo las fechas con matches, agrupadas.
- Se agrega `->with('fecha')` / se ajusta el `select` para eliminar el N+1 de `$p->fecha->nombre` detectado en el explore (gratis en el mismo cambio, no es refactor aparte).

## 3. Servicio propuesto

Lógica de "fecha relevante" + "orden de fechas" se extrae a `app/Services/FixtureExploradorService.php` (namespace de dominio, no del controlador) con métodos tipados:
- `resolverFechaRelevante(int $idEdicion, ?int $idCategoria): ?int`
- `ordenarFechas(Collection $fechas, int $idEdicion, ?int $idCategoria): Collection`

Justificación: el controlador ya es monolítico (§1 del explore: "no FormRequest, no service, todo inline"); esta lógica nueva (dos queries de agregación) no es orquestación de request/response, es regla de negocio con casos borde (edición sin partidos, sin próximos) — corresponde a `app/Services/**` por convención del proyecto. No se refactoriza el resto del método `fixture()` (no se toca lo que no cambia).

## 4. Alcance de archivos

| Archivo | Cambio |
|---|---|
| `app/Services/FixtureExploradorService.php` | **Nuevo.** Fecha relevante + orden de fechas. |
| `app/Http/Controllers/ControladorHome.php` (método `fixture`) | Inyecta el service; agrega ramas `idFecha=todas` y `equipo`; agrega `idEdicion`, `idFecha`, `estado`, `orden`, `equipo` al `compact()`; agrega `->with('fecha')`/ajuste de `select` para el N+1. |
| `resources/views/layouts/fixture.blade.php` | Chips de categoría (nuevo), chips de estado (nuevo), chip "Todas" reescrito para apuntar a `idFecha=todas` (hoy es un ancla `#fixture-contenido`), chips de fecha reescritos como `route()` reales con `idFecha=N` (hoy son anclas `#fecha-N`), input de búsqueda `equipo`, agrupado de resultados de búsqueda por fecha. Se elimina el bloque JS del hack de scroll (`scrollIntoView`, doble `scrollTo(0,0)`, `pageshow`); se conserva intacto el `IntersectionObserver` de la barra sticky (independiente, confirmado en el explore). |
| `tests/Feature/PlanillaPublicaTest.php` | Se agregan defaults (`?? null` / `?? 'todos'`) para las variables nuevas en `test_fixture_renderiza_sin_explotar_si_falta_public_token` — **no se toca su array exhaustivo existente**, solo se blindan los nuevos accesos en la vista. |
| `tests/Feature/FixtureExploradorTest.php` | **Nuevo.** Ver §6. |

No se tocan: rutas (`route('fixture', ...)` ya soporta todos los params), `NavegacionPublicaTest.php` (sigue pegándole a `route('fixture')` bare y a `idEdicion`, ambos casos siguen resolviendo sin explotar), tarjetas de partido, sistema de planillas públicas, `ImprimirFixtureCompletoA4Test.php` (fuera del flujo público).

## 5. Non-goals

- #99 (seguir equipo), #100 (home), #102–#104: fuera de alcance.
- No se rediseñan las tarjetas de partido (`<article class="partido-card">`) ni su HTML/estilos.
- No se extrae `<x-partido-card>` (el explore lo señala como refactor de alto valor, pero es un work unit propio, previo, para no inflar este diff — se deja como sugerencia de seguimiento, no parte de #98).
- No se agregan dependencias nuevas (sin Alpine nuevo, sin librería de fechas; JS vanilla como el resto del portal).
- No se pagina `idFecha=todas` (mantiene el comportamiento actual 1:1).
- No se implementa el redirect client-side de `#fecha-N` (§2.7, queda como ítem de seguimiento opcional).

## 6. Tests (red mínima)

Nuevo `tests/Feature/FixtureExploradorTest.php` (Pest), con fixtures de BD reales (factories de `Edicion`/`Categoria`/`Fecha`/`Equipo`/`Partido`):
1. Default (sin `idFecha`) con partidos futuros ⇒ renderiza solo la fecha con el próximo partido no jugado.
2. Default sin partidos futuros (todos jugados) ⇒ renderiza la fecha del último jugado.
3. `idFecha=todas` ⇒ renderiza todas las fechas (paridad con el comportamiento actual, assert sobre cantidad de `partido-card`).
4. `estado=finalizados` ⇒ excluye no jugados (ya cubierto a nivel query, se agrega assert sobre el HTML).
5. `equipo=<nombre parcial>` con match en una fecha distinta a la relevante ⇒ esa fecha aparece agrupada aunque no sea la relevante ni se pida `idFecha`.
6. `idCategoria` cambia el set de chips de fecha (fechas compartidas `idCategoria IS NULL` + propias de la categoría).
7. Bare `route('fixture')` (sin `idEdicion`) sigue sin explotar (regresión de `NavegacionPublicaTest`).

`PlanillaPublicaTest::test_fixture_renderiza_sin_explotar_si_falta_public_token` se corre sin cambios en su array de variables (los defaults viven en la vista).

## 7. Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| Enlaces ya compartidos (WhatsApp) con `idEdicion`+`idCategoria` cambian de "todas" a "una fecha" | Chip "Todas" siempre visible y a un tap; es el cambio de comportamiento que pide el issue, no un bug. |
| Test exhaustivo de `PlanillaPublicaTest` rompe con cualquier variable nueva no default-eada | Cada variable nueva lleva `?? null` / `?? 'todos'` en la vista antes de usarse; se corre el test como parte de la verificación de este change. |
| N+1 de `$p->fecha` se toca al mismo tiempo que el resto | Cambio aislado y mínimo (`with`/`select`), cubierto por los tests nuevos que ya iteran partidos. |
| Búsqueda de equipo con `%`/`_` literal en el nombre produce falsos positivos | Escape explícito del término antes de interpolarlo en el patrón LIKE (§2.6). |
| Diff grande en una sola PR dificulta el review | Ver §8, se recomienda partir en 2 PRs encadenadas. |

## 8. Forecast y estrategia de PRs

Estimación de líneas netas:
- Service nuevo: ~60-90 líneas.
- Controlador (`fixture()`): ~40-60 líneas modificadas/agregadas.
- Vista (`fixture.blade.php`): chips + input + remoción del hack JS: ~120-180 líneas netas (agregado de chips/form, borrado del bloque scroll).
- Tests: ~120-180 líneas (7 casos nuevos + ajuste de defaults).

Total estimado: **~350-500 líneas**, cerca o por encima del umbral de 400. Se recomienda partir en 2 PRs encadenadas (`chained-pr`):

1. **PR1 — Backend**: service + controlador (fecha relevante, orden, `idFecha=todas`, `equipo`, N+1) + tests de `FixtureExploradorTest` centrados en controlador/datos. La vista solo recibe los `compact()` nuevos con defaults (sin UI nueva todavía) — `PlanillaPublicaTest` se ajusta acá.
2. **PR2 — Vista**: chips de categoría/estado/fecha reales, input de búsqueda, eliminación del hack JS, más los tests que aserten HTML (cards agrupadas, chips activos).

## 9. Criterios de éxito

- `route('fixture', ['idEdicion'=>X])` sin más params renderiza una sola fecha (la relevante), no toda la edición.
- El chip "Todas" reproduce exactamente el conteo de partidos que el fixture muestra hoy sin `idFecha`.
- Buscar un equipo devuelve resultados de cualquier fecha sin necesidad de tocar los chips de fecha.
- `PlanillaPublicaTest` y `NavegacionPublicaTest` pasan sin modificarse en su intención (solo defaults agregados donde corresponde).
- `pint --dirty` limpio en los archivos tocados.

## 10. Rollback

Cambio contenido a un controlador, una vista y un service nuevo; revertir es un `git revert` de la(s) PR(s) sin migraciones ni cambios de esquema — no hay rollback de datos.

## 11. Verificación (a ejecutar en el Verifier, dentro de Docker)

```
docker compose exec app ./vendor/bin/pint --dirty
docker compose exec app php artisan test --compact --filter=FixtureExplorador
docker compose exec app php artisan test --compact tests/Feature/PlanillaPublicaTest.php
docker compose exec app php artisan test --compact tests/Feature/NavegacionPublicaTest.php
```

## Proposal question round

Casi todas las decisiones de producto ya vinieron cerradas en el encargo (fecha relevante, default vs "Todas", mobile-first, non-goals). Los únicos puntos donde tomé una decisión de diseño sin confirmación explícita del usuario, y que valen una revisión rápida:

1. **Reset de filtros al cambiar de categoría** (§2.4): al tocar un chip de categoría, `idFecha`/`estado`/`equipo` se descartan en vez de preservarse. ¿Correcto, o preferís que se preserve `estado`/`equipo` y solo se resetee `idFecha`?
2. **Fragmentos `#fecha-N` legacy** (§2.7): decidí no implementar el redirect automático en este change (nadie los usa hoy, según el explore). ¿Lo dejamos afuera del alcance de #98, o lo sumás como ítem obligatorio?
3. **Chips de categoría entran en el alcance de #98** (§2.4): el explore marcó que hoy no existen filtros de categoría en la vista aunque el hero los promete. Asumo que "construirlos" es parte de este issue (no de la navegación general). ¿Confirmás?

Si no hay objeciones, sigo con las decisiones tal como están documentadas arriba.

## Skill resolution

`skill_resolution: paths-injected`. Se cargó `laravel-blade/SKILL.md` (path inyectado). Su bloque "Agent Workflow (MANDATORY)" pide spawnear subagentes (`fuse-ai-pilot:*`) vía `TeamCreate`; no se ejecutó porque (a) esta fase tiene prohibido lanzar subagentes hijos (el orquestador es dueño de la delegación) y (b) no hay herramienta `TeamCreate` disponible en este entorno. Se aplicaron en cambio las reglas de contenido del skill (chips reutilizando gramática existente, `@props`/anonymous components no aplican acá porque no se crean componentes nuevos, buscador con `<input>` + form GET, sin `{!! !!}`).
