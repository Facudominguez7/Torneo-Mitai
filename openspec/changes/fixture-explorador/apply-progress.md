# Apply Progress — fixture-explorador (#98)

Fase: SDD apply. Ejecutada en una pasada T1→T7 según mandato del orquestador (sin commit; árbol limpio salvo 3 archivos ajenos preexistentes: `.gitignore`, `opencode.json`, `sync-db.sh` — no tocados).
Store: `openspec`. Skill: `skill_resolution: paths-injected` (`laravel-blade`). Strict TDD: activo por mandato del prompt (no hay `openspec/config.yaml`); ciclos RED → GREEN → TRIANGULATE documentados abajo.

## Estado final

- `tests/Feature/FixtureExploradorTest.php` (nuevo, Pest): **22 passed (91 assertions)**.
- `tests/Feature/PlanillaPublicaTest.php`: **sin regresiones** — mantiene únicamente su fallo preexistente del baseline (`devuelve una planilla publica readonly...` por `MAX(updated_at)` en `ultima_actualizacion`, ajeno al fixture y capturado ANTES de tocar código). `test_fixture_renderiza_sin_explotar_si_falta_public_token` ✓ pasa **sin modificar el array del test** (los defaults viven en la vista, como exigía el spec).
- `tests/Feature/NavegacionPublicaTest.php`: **8 passed (43 assertions)** — el conteo de `aria-current` de la nav no se rompe (los chips nuevos viven fuera de `<nav>`).
- Suite completa `php artisan test --compact`: **30 failed / 161 passed = baseline exacto de la branch** (ProfileTest 4×419, Auth/PasswordReset/Authentication/Password*, Panel/*, PlanillaPublicaTest×1 — todos preexistentes). Cero fallos introducidos. (Un run intermedio mostró "38 failed": artefacto de haber corrido dos pest en paralelo sobre la misma `mitai_testing`; aislado siempre da el baseline.)
- `pint --test app/Services/FixtureExploradorService.php tests/Feature/FixtureExploradorTest.php` ⇒ **PASS**.
- `pint --test app/Http/Controllers/ControladorHome.php` ⇒ 1 issue: `new_with_parentheses, fully_qualified...` sobre los **imports preexistentes (línea 4)** — deuda conocida e instrucción explícita de no arreglarla. Mi código no agrega issues.
- Curl de humo (edición 10 real, 6 fechas / 137 partidos):
  - `GET /fixture?idEdicion=10` ⇒ 200, **1 sola sección** (`id="fecha-253"` = fecha relevante calculada por el service), 28 cards, badge con nombre de fecha.
  - `GET /fixture?idEdicion=10&idFecha=todas` ⇒ 200, **6 secciones / 137 cards** (paridad 1:1 con el comportamiento previo).
  - `GET /fixture?idEdicion=10&equipo=Guerreros` ⇒ 200, **27 cards en 6 secciones** — cruce exacto con la BD: 27 partidos reales de equipos "Guerreros" en 6 fechas. Sin falsos positivos.

## Evidencia TDD

### T1 — Service `FixtureExploradorService` (PR1 backend)

| Ciclo | Evidencia |
|---|---|
| RED | 5 tests del service fallan con `BindingResolutionException` (clase inexistente, RED esperado por tasks) + 19 failed totales del archivo. Run: `Tests: 19 failed, 3 passed (40 assertions)`. |
| GREEN | Service creado: `resolverFechaRelevante()` (próximo no jugado futuro → último jugado → primera fecha por id, con filtro opcional de categoría) y `ordenarFechas()` (MIN(horario_datetime) asc, desempate por id, fechas sin partidos al final). 5/5 verdes. |
| TRIANGULATE | Casos de colisión (dos fechas con idéntico MIN ⇒ id asc), categoría ajena no contamina (fechaB con partido más próximo no gana para catA) y fechas sin partidos al final: `it ordena fechas por primer horario y desempata por id` + `it resuelve la fecha relevante dentro de la categoria pedida`. Verdes. |
| Verificación | `pint --test app/Services/FixtureExploradorService.php` ⇒ PASS. |

### T2 — `ControladorHome::fixture()` (PR1 backend)

| Ciclo | Evidencia |
|---|---|
| RED | Tests HTTP por assertion (no 500): `Failed asserting that 4 is identical to 2` (default renderizaba todo, esperaba solo fecha relevante), etc. |
| GREEN | Ramas nuevas: `equipo` presente ⇒ búsqueda multi-fecha con `addcslashes($equipo, '\\%_')` (precede a `idFecha`); `idFecha === 'todas'` ⇒ vista completa; id numérico existente en la edición ⇒ esa fecha; cualquier otro valor ⇒ fecha relevante (no explota). `with('fecha')` en las 3 ramas (N+1 de `$p->fecha->nombre` eliminado). `compact()` suma `idEdicion`, `idFecha`, `idFechaRelevante`, `estado`, `equipo`. |
| TRIANGULATE | `todas+equipo` ⇒ precedencia de búsqueda (`Tests: ... 22 passed` incluye `it da precedencia a la busqueda de equipo sobre idFecha=todas`); `idFecha=999999` ⇒ cae a relevante; `estado=estadoinexistente` ⇒ 200 sin marcar ningún chip de estado. |
| Defaults de vista | `PlanillaPublicaTest` pasa SIN tocarlo: la vista defiende con `$var ?? null` / `$estado ?? 'todos'` (el test provee un array fijo sin `idEdicion`/`estado`/`equipo`/`idFecha`). |
| Verificación | PlanillaPublicaTest + NavegacionPublicaTest ⇒ sin cambios de intención. Pint ControladorHome ⇒ solo deuda preexistente (desvío documentado abajo). |

Hallazgo durante GREEN: `Equipo::setNombreAttribute` normaliza a Title Case (`Pepe_Gril` ⇒ `Pepe_gril`) — comportamiento de dominio preexistente. La query LIKE escapada es correcta (validada contra MySQL por binding: `%Pepe\_Gril%` matchea solo el nombre real); los tests se ajustaron a los nombres normalizados que realmente almacena la BD.

### T4 — Chips de fecha reales + "Todas" (PR2 vista)

| Ciclo | Evidencia |
|---|---|
| RED | `it marca el chip de la fecha activa...` falla (anchors viejos `#fecha-N` sin `idFecha`). |
| GREEN | Anchors reescritos a `route('fixture', ...)` con `array_filter`/`array_merge` (idiom tabla-vallas); chip de la fecha relevante omite `idFecha` (default implícito); "Todas" apunta a `idFecha=todas`; `aria-current="page"` solo en la fecha mostrada; chips preservan `idCategoria`+`estado`+`equipo`. Iteran `$fechas` (orden calendario del service), no solo las fechas con partidos del render actual. |
| Verificación | `--filter=FixtureExplorador` ⇒ 22 passed. |

### T5 — Chips de categoría y de estado (PR2 vista)

| Ciclo | Evidencia |
|---|---|
| RED | `it los chips de categoria reinician fecha estado y busqueda` + `it los chips de estado preservan...` fallan (no existían). |
| GREEN | Categorías: gramática exacta de `tabla-posiciones.blade.php` (rounded-full, focus-visible ring `#f7f280/40`, activo `border-[#f7f280] bg-[#f7f280]/20`), href solo `idEdicion`+`idCategoria` (reset total por spec §2.4). Estado: `Todos/Próximos/Finalizados` exponen `estado` preservando `idFecha`+`idCategoria`+`equipo`. |
| Verificación | Suite verde. Revisión visual manual mobile-first: **pendiente** (requiere browser; la estructura es mobile-first: strips `overflow-x-auto` con scrollbar oculto `[scrollbar-width:none] [&::-webkit-scrollbar]:hidden`, `flex-wrap` en estados). |

### T6 — Buscador de equipo (PR2 vista)

| Ciclo | Evidencia |
|---|---|
| RED | `it expone el buscador...` + `it muestra un mensaje cuando la busqueda...` fallan. |
| GREEN | `<form method="GET" action="{{ route('fixture') }}" role="search">` con hidden `idEdicion`/`idCategoria`/`estado` + `<input type="search" name="equipo">` (patrón `Panel/tabla_goleador`); mensaje `No se encontraron partidos para «...».` tanto dentro de `#fixture-contenido` (con grupos) como en la rama sin grupos; resultados agrupados por fecha vía `fechasNavegacion` (solo fechas con match). Sin JS nuevo (sin debounce: submit nativo). |
| Verificación | Suite verde; grep `scrollIntoView` en la vista ⇒ solo queda el de `layouts/navigation.blade.php:136` (nav compartida, fuera de alcance — el de la vista del fixture fue eliminado en T7). |

### T7 — Self-heal `#fecha-N` y remoción del hack (PR2 vista)

| Ciclo | Evidencia |
|---|---|
| RED (máximo posible) | `it normaliza anchors legacy de fecha sin emitir el hack de scroll` falla: `Not to contain: scrollIntoView` (el hack aún se emitía). |
| GREEN | Eliminados: `scrollIntoView` + filtro `dataset.jugado` + doble `scrollTo(0,0)` + listener `pageshow`. Conservado intacto: `IntersectionObserver` de la barra sticky (sentinel/spacer/`fijarBarraFechas`/`sincronizarAlturaSpacer`/resize — 8 referencias verificadas). Agregado IIFE: si `location.hash` matchea `/^#fecha-(\d+)$/` y no hay `idFecha` en la query ⇒ `history.replaceState` a `?idFecha=N` + `location.reload()` (reemplaza entrada de historial, no agrega). |
| Verificación | `assertDontSee("block: 'center'")`, `assertDontSee('dataset.jugado')`, `assertDontSee('pageshow')` + `toContain('replaceState')` + `toContain('location.reload')` ⇒ verdes. |

## Archivos

| Archivo | Cambio | Líneas |
|---|---|---|
| `app/Services/FixtureExploradorService.php` | **Nuevo**: fecha relevante + orden calendario de fechas. | 96 |
| `app/Http/Controllers/ControladorHome.php` | `fixture()`: ramas `equipo`/`todas`/relevante, escape LIKE, `with('fecha')`, fechas ordenadas también sin categoría, `compact()` extendido. | diff: 86 ins / 48 del en el archivo (junto con vista: 173 ins / 48 del) |
| `resources/views/layouts/fixture.blade.php` | Chips categoría/estado/Todas/fecha con `route()`, buscador, mensaje sin resultados, defaults Blade, self-heal JS, remoción del hack. | 135 ins / — (diff -w: 133 — sin ruido de reindentación) |
| `tests/Feature/FixtureExploradorTest.php` | **Nuevo**: 22 tests Pest + 4 helpers. | 546 |
| `tests/Feature/PlanillaPublicaTest.php` | **NO modificado** (los defaults viven en la vista, como prefería el tasks). | 0 |

`git diff | grep '^-'` verificado: los únicos borrados reales son el hack JS, los anchors `#fecha-N`/`#fixture-contenido` viejos y la query `orderByDesc('id')` reemplazada por el orden calendario. Diff vs diff -w coinciden (reemplazos reales, sin reindentación).

## Comandos ejecutados (todos dentro de Docker)

```
docker compose exec app ./vendor/bin/pest tests/Feature/FixtureExploradorTest.php          # RED 19F/3P → GREEN 22P
docker compose exec app ./vendor/bin/pest tests/Feature/PlanillaPublicaTest.php            # 1F preexistente, 12P
docker compose exec app ./vendor/bin/pest tests/Feature/NavegacionPublicaTest.php          # 8P
docker compose exec app php artisan test --compact                                          # 30F/161P = baseline
docker compose exec app ./vendor/bin/pint --test app/Services/FixtureExploradorService.php tests/Feature/FixtureExploradorTest.php  # PASS
docker compose exec app ./vendor/bin/pint --test app/Http/Controllers/ControladorHome.php  # 1 issue preexistente (imports)
curl http://localhost:8000/fixture?...                                                      # humo ed10: 3 caminos OK
```

## Desvíos de diseño (justificados)

1. **`pint --test ControladorHome.php ⇒ limpio` (T2)**: no se cumple literalmente — el archivo mantiene 1 issue preexistente (`fully_qualified` imports, línea 4). Instrucción del encargo: no arreglar deuda preexistente. Mi diff no agrega issues.
2. **`scrollIntoView` assertion (T7 RED)**: el tasks pedía `assertDontSee('scrollIntoView')`, pero `layouts/navigation.blade.php:136` (compartida, fuera de superficies) usa `scrollIntoView` legítimamente con `inline: 'center'`. El assert usa marcadores únicos del hack eliminado (`block: 'center'`, `dataset.jugado`, `pageshow`). El spec (comportamiento observable) queda cubierto.
3. **Ninguno funcional**: el contract `idFecha` respeta tasks/spec — solo el literal `'todas'` abre la vista completa; id numérico inexistente y basura caen a la fecha relevante; la búsqueda precede sobre `todas`.

## Pendientes (bloqueadas por falta de browser — no por código)

- `- [ ]` T5: revisión visual manual mobile-first de la barra (chips/estado/buscador) en dispositivo o devtools responsive.
- `- [ ]` T7 verificación manual concreta del self-heal: (1) abrir `/fixture?idEdicion=10#fecha-253` ⇒ la URL pasa a `idFecha=253` sin nueva entrada de historial; (2) `?idFecha=5#fecha-7` ⇒ prevalece 5, sin loop (la condición `params.has('idFecha')` del IIFE lo garantiza y está verificada estáticamente); (3) `#fecha-9999` ⇒ sin error, queda la fecha relevante (el fallback server-side está cubierto por `it cae a la fecha relevante con un idFecha numerico inexistente`).
- `npm run build` no fue necesario: la vista no cambia assets ni agrega clases nuevas fuera de Tailwind ya compilado (no hay nuevas utilidades arbitrarias que requieran regeneración — se reutilizan clases existentes del layout y de tabla-posiciones).

## Workload / PR boundary

Líneas authored reales: **~815** (service 96 + tests 546 + controlador ~134 netas + vista ~135 netas ≈, sobre presupuesto de 400). No encoge más sin recortar cobertura de spec (8 requirements / 17 scenarios mapeados 1:1 en los 22 tests). El presupuesto construye slicing de review, nunca código. Recomendación: dividir en **2 PRs apiladas** como sugiere el tasks (PR1 backend: service + controlador + tests service/HTTP ≈ 380-420; PR2 vista: chips/buscador/self-heal + tests de HTML ≈ 400). Decisión de cadena/exception queda para el orquestador (`ask-on-risk`).

## Split PR1/PR2 (preparación del working tree para commit)

Ejecutado bajo delegación del orquestador: preparar el estado backend para la PR1 en cadena. La vista volvió EXACTAMENTE a HEAD (`git checkout HEAD -- resources/views/layouts/fixture.blade.php`; `git diff HEAD` sobre el archivo = 0 bytes, sin entrada en status). El diff de vista de esta sesión (chips, buscador, self-heal, branches de render) se apartó para la PR2 — reconstruir desde los tests de `FixtureExploradorVistaTest.php`, que son el contrato de render.

### Reparto de los 22 tests

Medición previa al split (archivo original de 22 contra vista legacy + controlador nuevo): **15 passed / 7 failed**. Fallan solo los verdaderamente dependientes de la vista nueva: 4 de chips, buscador (form hidden inputs), mensaje sin resultados y self-heal.

**Quedan en `tests/Feature/FixtureExploradorTest.php` (PR1, 8 tests — todos verdes con vista legacy):**

1. `resuelve la fecha del proximo partido no jugado como fecha relevante` (service)
2. `resuelve la fecha del ultimo partido jugado cuando no hay futuros` (service)
3. `resuelve la primera fecha por id cuando no hay partidos cargados` (service)
4. `ordena fechas por primer horario y desempata por id` (service)
5. `resuelve la fecha relevante dentro de la categoria pedida` (service)
6. `renderiza todas las fechas con idFecha=todas` (rama backend `todas`; legacy-compatible)
7. `responde 200 para el enlace bare del fixture sin idEdicion` (solo 200)
8. `resuelve la primera fecha cuando la categoria no tiene partidos cargados` (fallback sin partidos, legacy-compatible)

**Movidos a `tests/Feature/FixtureExploradorVistaTest.php` (PR2, 14 tests — contenido intacto, helpers duplicados con nombres distintos: `fixtureVistaBase/fechaVista/partidoVista/anclaVistaConTexto`, porque las funciones Pest son globales):**

1. `renderiza solo la fecha relevante cuando no viene idFecha`
2. `renderiza exactamente la fecha pedida con idFecha explicito`
3. `busca equipos en todas las fechas y agrupa por fecha`
4. `combina la busqueda de equipo con el estado finalizados`
5. `trata los comodines % y _ como texto literal en la busqueda`
6. `da precedencia a la busqueda de equipo sobre idFecha=todas`
7. `cae a la fecha relevante con un idFecha numerico inexistente`
8. `no marca ningun chip de estado activo con un estado desconocido`
9. `marca el chip de la fecha activa y enlaza las demas con idFecha`
10. `los chips de categoria reinician fecha estado y busqueda`
11. `los chips de estado preservan fecha categoria y busqueda`
12. `expone el buscador de equipo con hidden inputs de contexto`
13. `muestra un mensaje cuando la busqueda de equipo no tiene resultados`
14. `normaliza anchors legacy de fecha sin emitir el hack de scroll`

**Matiz documentado (decisión del orquestador respetada):** los tests 1-7 de la lista movida PASAN incluso con la vista legacy + controlador nuevo, porque el filtrado (fecha única/búsqueda) es server-side y la vista legacy renderiza bien la data que recibe. Se mueven igual a PR2 por categorización explícita del encargo: aserten HTML de render (`id="fecha-N"`, conteo de cards, agrupación) → contrato de vista. Solo los tests 8-14 movidos fallan hoy contra legacy (7 failed esperados hasta que vuelva la vista).

### Evidencia del estado backend verde (Docker)

```text
docker compose exec app ./vendor/bin/pest tests/Feature/FixtureExploradorTest.php        # 8 passed (13 assertions)
docker compose exec app ./vendor/bin/pest tests/Feature/PlanillaPublicaTest.php          # 1 failed (baseline preexistente ultima_actualizacion) + 12 passed
docker compose exec app ./vendor/bin/pest tests/Feature/FixtureExploradorVistaTest.php   # 7 passed + 7 failed = pendientes PR2 (esperado, archivo NO va en PR1)
docker compose exec app php artisan test --compact                                        # 37 failed / 154 passed = 30 baseline + 7 pendientes PR2; cero regresiones
docker compose exec app ./vendor/bin/pint --test tests/Feature/FixtureExploradorTest.php tests/Feature/FixtureExploradorVistaTest.php  # PASS
```

`php artisan test --compact` corrió CON el archivo de vista ya creado (untracked): los 7 failed adicionales son exactamente `FixtureExploradorVistaTest` (chips×4, buscador, sin-resultados, self-heal), todos nombrados y atribuibles. El árbol commiteado en PR1 no incluye ese archivo → la PR1 queda con el baseline de 30 fallos preexistentes, 0 introducidos. Aritmética de control: baseline 161 passed − 22 del archivo original + 8 (PR1) + 7 (vista que aún pasan en legacy) = 154 ✓.

`git diff --stat HEAD` tras el split: solo `.gitignore`, `opencode.json`, `sync-db.sh` (ajenos preexistentes, intactos) + `ControladorHome.php` (backend PR1). La vista NO figura. Untracked para PR1: `app/Services/FixtureExploradorService.php`, `tests/Feature/FixtureExploradorTest.php`, `openspec/changes/fixture-explorador/`. Untracked para PR2: `tests/Feature/FixtureExploradorVistaTest.php` (+ la vista a reconstruir).

## Structured status consumido

- Estado nativo: `changeName: null` + `applyState: blocked` por ambigüedad de selección → resuelto por mandato explícito del prompt (change `fixture-explorador`). `actionContext.mode: repo-local`, `allowedEditRoots` = raíz del repo → superficies respetadas: solo los 5 archivos permitidos (+2 archivos nuevos dentro de las superficies). Ningún archivo fuera de raíz.
- Store: `openspec` (`openspec/changes/fixture-explorador/`). Tasks actualizados in situ: 20/22 checkboxes `- [x]`; restantes = 2 verificaciones manuales de browser (arriba).

## Ronda 5 (reconstrucción vista + filtros consolidados)

**Contexto:** la vista nueva se descartó en el split PR1/PR2 y el working tree quedó en la vista legacy de HEAD. Se reconstruyó sobre el backend de PR1 (`114aba8`), incorporando el feedback de usuario: demasiados filtros sueltos → UN panel consolidado en la zona del hero. Incidente de arranque diagnosticado y resuelto por el orquestador: el working tree estaba en `fix/nav-sticky-ligera` sin el backend; `git switch feat/fixture-explorador` (los 4 sucios ajenos se trasladaron sin conflicto).

### Diseño implementado del panel consolidado

Un solo contenedor `div.mt-8.rounded-3xl.border-f7f280/25.bg-black/30` con dos filas:

- **Fila 1 (fechas):** chip `Todas` (`idFecha=todas`, activo solo con `idFecha=todas`) + un chip por fecha de `$fechas` (orden calendario del service). El chip de la fecha **relevante** enlaza SIN `idFecha` (default implícito); las demás llevan `idFecha=N`. Todos preservan `idEdicion`/`idCategoria`/`estado` y NO llevan `equipo` (clic en fecha limpia la búsqueda, por diseño del encargo).
- **Fila 2 (categoría + estado + buscador):** chips de categoría (solo `idEdicion`+`idCategoria`: reinician fecha/estado/búsqueda), chips de estado `Todos/Próximos/Finalizados` (preservan fecha explícita —incluida `todas`—, categoría y equipo; siempre llevan `estado=X` explícito) y el `<form method="GET">` con hidden `idEdicion`, `idCategoria` (si hay) e `estado`, input `type="search" name="equipo"`.
- Gramática de chips idéntica a tabla-posiciones/nav: activo `border-f7f280 bg-f7f280/20 text-f7f280` + `aria-current="page"`; inactivo `border-white/20 bg-white/5 text-white/70 hover:*`; `focus-visible:ring-f7f280/40`; `overflow-x-auto` mobile con scrollbar oculta y `lg:flex-wrap` en desktop.
- Chips activos por parámetro: fecha activa si `(string) $idFecha === (string) $fecha->id` o si no vino `idFecha` y es la relevante; estado activo si `$estado === $valor` (estado desconocido ⇒ ninguno); categoría activa por `idCategoria`.

### Comportamiento acompañante

- **Self-heal `#fecha-N`:** IIFE que si `location.hash` matchea `#fecha-(\d+)` y la URL no tiene `idFecha`, hace `history.replaceState` con `idFecha=N` y `location.reload()` (una sola vez: tras el reload `params.has('idFecha')` corta). Los anchors legacy `#fecha-N` de la barra sticky siguen funcionando gracias a esto.
- **Hack de scroll eliminado:** fuera el `scrollIntoView({ block: 'center' })` al próximo partido y el listener `pageshow`. El `scrollTo(0,0)` legacy ahora corre solo SIN hash (dentro de `if (!window.location.hash)`) para no pelear con el salto del anchor tras el reload del self-heal.
- **Barra sticky conservada** con su IntersectionObserver intacta; ahora marca como activa (`aria-current` + clases activas) la fecha activa server-side, reusando la MISMA función `$fechaEsActiva` del panel. Su chip `Todas` dejó de estar activo siempre: enlaza a `?idFecha=todas` (con categoría/estado) y solo se marca activo en modo `todas`. La barra sigue listando `$fechasNavegacion` (fechas con partidos renderizados: sus anchors `#fecha-N` necesitan destino), mientras el panel lista `$fechas` completo.
- **Mensaje sin resultados:** `No se encontraron partidos para "..."` dentro de `#fixture-contenido` cuando hay búsqueda y `$partidos` vacío.
- Defaults Blade en toda variable nueva (`$estado ?? 'todos'`, `$equipo ?? ''`, `$idFecha ?? null`, `$idFechaRelevante ?? null`, `$categorias ?? collect()`, `$fechas ?? collect()`): `PlanillaPublicaTest` renderiza la vista con array parcial y sigue pasando.

### Evidencia TDD (Docker)

```text
RED   docker compose exec app ./vendor/bin/pest tests/Feature/FixtureExploradorVistaTest.php   # 7 failed, 7 passed (46 assertions)
      # Rojos esperados: chips de estado (desconocido), chip fecha activa, chips categoría,
      # chips estado preservando, buscador hidden inputs, sin resultados, hack de scroll
GREEN docker compose exec app ./vendor/bin/pest tests/Feature/FixtureExploradorVistaTest.php   # 14 passed (78 assertions)
GREEN docker compose exec app ./vendor/bin/pest tests/Feature/FixtureExploradorTest.php        # 8 passed (13 assertions)
BASE  docker compose exec app ./vendor/bin/pest tests/Feature/PlanillaPublicaTest.php          # 1 failed (baseline preexistente ultima_actualizacion) + 12 passed
```

Durante el GREEN se corrigieron dos desvíos detectados por los tests: (1) el panel iteraba `$fechasNavegacion` y con `estado=finalizados` ocultaba los chips de fechas sin partidos en el set filtrado → pasó a `$fechas`; (2) quedaba un `scrollTo(0,0)` legacy duplicado y sin guard → eliminado, quedó solo el guard con hash.

### Verificación de reindentación

```text
git diff --stat HEAD -- resources/views/layouts/fixture.blade.php   # 180 líneas (152+/28-)
git diff -w --stat HEAD -- resources/views/layouts/fixture.blade.php # 176 líneas (150+/26-)
```

El delta de 4 líneas son EXCLUSIVAMENTE las líneas `window.scrollTo`/`setTimeout`/`}` que se reindentaron al quedar dentro del guard `if (!window.location.hash)` — un bloque que esta ronda reescribió, no un bloque ajeno. El `@php` legacy de `$fechasNavegacion` quedó byte-a-byte intacto en su posición original.

### Humo con edición real (id 10, vía HTTP contra Docker)

```text
GET /fixture?idEdicion=10                  # 200; 1 aria-label por cada grupo del panel; placeholder "Buscar equipo...";
                                           # UNA sola sección id="fecha-253" (relevante); 28 partido-cards
GET /fixture?idEdicion=10&idFecha=todas    # 200; secciones fecha-248..253 (todas)
GET /fixture?idEdicion=10&equipo=Huracan   # 200; 22 partido-cards
```

### Desvíos documentados de esta ronda

1. **Hidden input `estado` en el buscador:** el diseño del encargo pedía hidden `idEdicion` + `idCategoria (si hay)`, pero el test 12 exige `name="estado"` en el HTML. Se agregó `<input type="hidden" name="estado" value="{{ $estadoFiltro }}">` (además preserva el filtro al re-buscar, coherente con los chips de estado).
2. **Panel lista `$fechas` completo, barra sticky mantiene `$fechasNavegacion`:** el test 9 exige chips de TODAS las fechas aunque el filtro de estado deje solo una con partidos. La barra sticky conserva su semántica legacy (anchors `#fecha-N` que requieren destino renderizado).
3. **`Todas` de la barra sticky cambió `#fixture-contenido` → `?idFecha=todas`:** en modo fecha única el anchor interno no llevaba a ninguna vista de todas las fechas y quedaba activo siempre. Es la "modificación chica" autorizada para sincronizar el estado activo de la barra.

### Checklist Playwright (a ejecutar por el orquestador; toolbox `~/.local/mitai-playwright`)

- [ ] Desktop 1280×800, `/fixture?idEdicion=10`: panel único visible bajo el hero (sin bloques sueltos); fila 1 en `lg:flex-wrap` sin scroll horizontal; fila 2 con buscador a la derecha; chip de fecha relevante con estilo activo + `aria-current="page"`; chip `Todas` inactivo.
- [ ] Desktop: `?idEdicion=10&idFecha=todas` ⇒ chip `Todas` activo, ninguna fecha activa; `?idEdicion=10&estado=finalizados` ⇒ chip `Finalizados` activo.
- [ ] Mobile 390×844: filas 1 y 2 scrollean horizontal (`overflow-x-auto`) con scrollbar oculta; buscador full-width; tapping chip de otra fecha navega a esa fecha preservando estado; tapping chip de categoría resetea fecha/estado/búsqueda.
- [ ] Sticky bar: scrollear hasta que el sentinel salga de vista ⇒ barra fija `fixed top-0` con spacer correcto (sin salto); chip de fecha activa marcado en la barra; anclar `#fecha-250` de otra fecha ⇒ self-heal recarga con `idFecha=250` y aterriza en la fecha (sin loop).
- [ ] Buscador: buscar `Huracan` ⇒ 22 cards agrupadas; buscar `Equipo Inexistente Total` ⇒ mensaje "No se encontraron partidos"; enviar búsqueda con `estado=finalizados` previo ⇒ el hidden preserva el estado.
- [ ] Capturas desktop+mobile de los 3 caminos (default / todas / búsqueda) revisadas con `read` antes de cerrar la ronda.

## Ronda 6 (feedback del owner post-reconstrucción: 3 decisiones)

**Contexto:** sobre la vista reconstruida de Ronda 5 (SIN commitear, 14 tests verdes), el owner emitió 3 decisiones que anulan partes del spec. Se documentan como enmiendas, sin borrar historial. Los chips de estado ya habían sido eliminados de la vista por el owner antes de esta ronda (quedaban 2 tests colgando que los asertaban).

### Decisiones aplicadas

1. **Bloque "Acceso rápido" a tabla de posiciones: ELIMINADO.** Ambas variantes (mobile `sm:hidden` con `fa-ranking-star` + desktop `sm:block` con "Encontrá la tabla de posiciones sin perderte en el fixture") y su `section`/`@if` envolvente completa (≈66 líneas) — sin dejar contenedores vacíos. El bloque "Ver instancias finales" del hero NO se tocó. La nav ya tiene Posiciones a un clic.
2. **Chips de fecha en orden DESCENDENTE (más reciente primero, "Todas" primero).** Solo presentación en la vista: panel consolidado itera `($fechas ?? collect())->reverse()` y barra sticky (modo `todas`) itera `$fechasNavegacion->reverse()`. El service NO se tocó (su orden calendario ascendente alimenta la lógica de fecha relevante). El render de secciones/partidos no cambia.
3. **Tests: asserts de chips de estado fuera.** El param `estado` server-side sigue funcionando; solo se deja de probar como UI.

### Tests eliminados / ajustados (tests/Feature/FixtureExploradorVistaTest.php)

| Test | Acción | Detalle |
|---|---|---|
| `no marca ningun chip de estado activo con un estado desconocido` | **ELIMINADO** | Asertaba exclusivamente el chip de estado "Todos" (ya inexistente en UI). Era 1 de los 2 rojos del baseline. |
| `los chips de estado preservan fecha categoria y busqueda` | **ELIMINADO** | Asertaba exclusivamente chips de estado ("Próximos"/"Todos"). El otro rojo del baseline. |
| `combina la busqueda de equipo con el estado finalizados` | **AJUSTADO** | Mantenida la búsqueda multi-fecha; como refuerzo de "contenido renderizado finalizado": `toContain('Leones Del Norte')` + `not->toContain('Pumas Del Centro')` (Title Case por el mutator de `Equipo`). |
| `los chips de categoria reinician fecha estado y busqueda` | **SIN CAMBIOS** | No asertaba chips de estado (solo params del href del chip de categoría: reset de `idFecha`/`estado`/`equipo` sigue vigente). |
| `muestra los chips de fecha en orden descendente con Todas primero` | **NUEVO** | Protege la decisión 2: orden de anclas por posición (helper `posicionAnclaVista`) en vista default Y en modo `todas` (panel + barra). |

Archivo: 14 → **13 tests** (2 fuera, 1 nuevo).

### Spec enmendado (openspec/changes/fixture-explorador/specs/portal-publico/spec.md)

- Requirement **Chips de estado**: nota de enmienda — chips eliminados de la UI, filtrado solo vía query param, scenarios sin efecto (scenarios preservados como historial).
- Requirement **Navegación por chips de fecha**: nota de enmienda — orden descendente por decisión del owner; orden calendario del service intacto como fuente de datos.

### Evidencia (Docker, secuencial — en paralelo pest pisa la misma `mitai_testing`, artefacto ya conocido)

```text
RED (baseline pre-edición)  pest tests/Feature/FixtureExploradorVistaTest.php  # 2 failed, 12 passed — exactamente los 2 tests de chips de estado eliminados
GREEN                       pest tests/Feature/FixtureExploradorVistaTest.php  # 13 passed (80 assertions)
                            pest tests/Feature/FixtureExploradorTest.php       # 8 passed (13 assertions) — sin cambios
BASE                        pest tests/Feature/PlanillaPublicaTest.php         # 1 failed (preexistente ultima_actualizacion) + 12 passed
PINT                        pint --test tests/Feature/FixtureExploradorVistaTest.php  # PASS
```

### Humo edición 10 real (HTTP)

```text
GET /fixture?idEdicion=10                 # 200; 0× "Acceso rápido", 0× "Encontrá la tabla de posiciones", 0× "Filtrar por estado"
                                          # chips (BD: 248=1° … 253=6°): Todas, 6°(relevante, sin idFecha), 5°(252), 4°(251), 3°(250), 2°(249), 1°(248)
GET /fixture?idEdicion=10&idFecha=todas   # 200; sin bloque de acceso rápido; panel: todas→253…248 DESC; barra sticky: #fecha-253→#fecha-248 DESC
```

### Diff noise

`git diff --stat` vs `git diff -w --stat`: la vista mantiene el delta de 4 líneas preexistente de Ronda 5 (reindentado del guard `if (!window.location.hash)` en JS). Esta ronda NO agrega ruido: eliminación de bloque completo + 2 líneas `foreach` modificadas + notas de spec/progress. Ajenos (`.gitignore`, `AGENTS.md`, `opencode.json`, `sync-db.sh`) intactos.

### Pendiente (orquestador)

- Checklist Playwright de Ronda 5 sigue abierto (esta ronda no lo ejecuta: verificación del encargo = tests + humo HTTP). Impacto visual de la ronda: sin bloque teal de acceso rápido; fila 1 del panel y barra sticky con orden de fechas invertido.
