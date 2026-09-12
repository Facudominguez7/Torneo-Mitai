# Tasks — fixture-explorador (#98)

Basado en `proposal.md` y `specs/portal-publico/spec.md` (8 requirements / 17 scenarios). No existe `design.md`; la solución técnica de referencia es §2–§4 de la propuesta.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 340–460 (T1+T2 ≈ 180–240; T3–T7 ≈ 160–220) |
| 400-line budget risk | Medium |
| Chained PRs recommended | Yes |
| Suggested split | PR1 (backend: T1–T2 + tests T3) → PR2 (vista: T4–T7) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

```text
Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: Medium
```

Si el total real de PR1 queda debajo de 250 líneas y la vista neta (chips + form + borrado del hack JS − líneas eliminadas) baja de ~200, puede pedirse excepción single-PR; por defecto, cadena de 2 PRs apiladas.

---

## Fase 1 — Backend (PR1)

### T1 — Service `FixtureExploradorService` (fecha relevante + orden de fechas)

Archivos: `app/Services/FixtureExploradorService.php` (nuevo), `tests/Feature/FixtureExploradorTest.php` (nuevo, solo casos unitarios/feature del service).

- [x] RED: en `tests/Feature/FixtureExploradorTest.php`, escribir 3 tests que llamen `resolverFechaRelevante(int $idEdicion, ?int $idCategoria)`: (a) con partido no jugado futuro ⇒ devuelve su `idFechas`; (b) todos jugados ⇒ `idFechas` del `MAX(horario_datetime)` jugado; (c) sin partidos ⇒ primera fecha por `id` asc. `docker compose exec app php artisan test --compact --filter=FixtureExplorador` falla (class no existe). <!-- sdd-owner: implementation -->
- [x] GREEN: crear `app/Services/FixtureExploradorService.php` con `resolverFechaRelevante()` y `ordenarFechas(Collection $fechas, int $idEdicion, ?int $idCategoria): Collection` (orden por `MIN(horario_datetime)` asc, fallback `id` asc), queries tipadas sobre `partidos.horario_datetime` y `jugado`. Tests anteriores en verde. <!-- sdd-owner: implementation -->
- [x] TRIANGULATE: agregar caso de colisión/ambigüedad — dos fechas cuyo `MIN(horario_datetime)` es idéntico ⇒ orden determinista por `id`; y categoría con `idCategoria` que filtra fechas propias + compartidas (`NULL`). Tests en verde. <!-- sdd-owner: implementation -->
- [x] Verificación: `docker compose exec app ./vendor/bin/pint --test app/Services/FixtureExploradorService.php` ⇒ 0 errores. <!-- sdd-owner: implementation -->

Riesgos: contract `idFecha=todas` vs una fecha cuyo nombre/id pueda colisionar con el string `todas` — validar en T2 que la rama `todas` se dispare solo con el literal exacto y que ningún `id` numérico pueda producirla; documentar en el service.

Mapeo: Req "Fecha relevante por defecto" (3 de 4 scenarios aquí; el 4.º en T2).

### T2 — Controlador `ControladorHome::fixture()` (ramas `todas`, `equipo`, N+1, compact)

Archivos: `app/Http/Controllers/ControladorHome.php` (método `fixture`), `tests/Feature/FixtureExploradorTest.php`.

- [x] RED: tests HTTP en `tests/Feature/FixtureExploradorTest.php`: (1) GET sin `idFecha` ⇒ 200 y solo los `partido-card` de la fecha relevante; (2) `idFecha=<otro>` ⇒ exactamente esa fecha; (3) `idFecha=todas` ⇒ cantidad de cards = total de partidos; (4) `equipo=<parcial>` ignorando `idFecha` y agrupando solo fechas con match; (5) `equipo` + `estado=finalizados`; (6) término con `%` literal no trae falsos positivos; (7) bare `route('fixture')` sin `idEdicion` sigue 200. Fallan. <!-- sdd-owner: implementation -->
- [x] GREEN: inyectar `FixtureExploradorService` en `fixture()`; default = `resolverFechaRelevante()` cuando `idFecha` ausente; rama `idFecha === 'todas'` = comportamiento actual sin filtro; `->when($equipo, ...)` con `addcslashes($termino, '%_\\')` + `ESCAPE '\\'` en ambas ramas, ignorando `idFecha` cuando hay término; agregar `with('fecha')`/ajustar `select` para eliminar el N+1 de `$p->fecha->nombre`; `compact()` con `idFecha`, `estado`, `equipo` (+ `orden` si la vista lo necesita). Tests en verde. <!-- sdd-owner: implementation -->
- [x] TRIANGULATE: escenario `idFecha=todas` + `equipo` (búsqueda tiene precedencia sobre "todas"), y escenario "sin partidos cargados ⇒ primera fecha" vía HTTP. Tests en verde. <!-- sdd-owner: implementation -->
- [x] Defaults de vista (defense-in-depth para `PlanillaPublicaTest`): render de `layouts/fixture.blade.php` tolera variables nuevas ausentes. Preferir defaults en Blade (`?? null` / `?? 'todos'`) ANTES que tocar el test; recién si el test sigue rompiendo, ajustar `tests/Feature/PlanillaPublicaTest.php` solo con defaults de variables nuevas, sin tocar su array existente. <!-- sdd-owner: implementation -->
- [x] Verificación: `docker compose exec app php artisan test --compact tests/Feature/PlanillaPublicaTest.php tests/Feature/NavegacionPublicaTest.php` ⇒ ambos pasan sin cambios de intención. `docker compose exec app ./vendor/bin/pint --test app/Http/Controllers/ControladorHome.php` ⇒ limpio. <!-- sdd-owner: implementation -->

Riesgos: (a) cambio de significado de enlaces legacy sin `idFecha` (mitigado: chip "Todas" en T4); (b) LIKE sin escape ya cubierto por test RED 6; (c) `estado` sin escapar en rama `todas` — cubrir con assert en TRIANGULATE.

Mapeo: Req "Fecha relevante por defecto" (scenario `idFecha` explícito), "Vista completa opcional", "Búsqueda de equipo multi-fecha" (queries), "Compatibilidad con enlaces y pruebas existentes".

## Fase 2 — Vista (PR2)

### T4 — Chips de fecha reales (`route()` con `idFecha`) y chip "Todas"

Archivos: `resources/views/layouts/fixture.blade.php`.

- [x] RED: test en `FixtureExploradorTest`: fixture de fecha N ⇒ chip de esa fecha con `aria-current="page"`, los demás sin; cada chip de fecha incluye `idFecha` propio y preserva `estado` y `equipo` activos; chip "Todas" apunta a `idFecha=todas`. Falla. <!-- sdd-owner: implementation -->
- [x] GREEN: reescribir los anchors `#fecha-N` como `route('fixture', [...])` con `array_merge($queryBase, ['idFecha' => $f->id])` (idiom de `tabla-vallas.blade.php:107`), en orden devuelto por `ordenarFechas`; reescribir chip "Todas" de `#fixture-contenido` a `idFecha=todas`. Tests en verde. <!-- sdd-owner: implementation -->
- [x] Verificación: `docker compose exec app php artisan test --compact --filter=FixtureExplorador` ⇒ verde. <!-- sdd-owner: implementation -->

Mapeo: Req "Navegación por chips de fecha", "Vista completa opcional" (chip).

### T5 — Chips de categoría (reset de filtros) y chips de estado

Archivos: `resources/views/layouts/fixture.blade.php`.

- [x] RED: tests: chip de categoría B enlaza solo con `idEdicion`+`idCategoria` (sin `idFecha`/`estado`/`equipo`); tras seguirlo la respuesta muestra la fecha relevante de B y chips de fecha de B; chips de estado (`todos|proximos|finalizados`) preservan `idFecha`, `idCategoria`, `equipo`. Fallan. <!-- sdd-owner: implementation -->
- [x] GREEN: chips de categoría reutilizando gramática exacta de `tabla-posiciones.blade.php` (mismas clases activo/inactivo); chips de estado con `$queryBase` + `array_merge`. Tests en verde. <!-- sdd-owner: implementation -->
- [ ] Verificación: comando anterior ⇒ verde; revisión visual manual mobile-first (ver sección "Verificación manual"). <!-- sdd-owner: implementation -->

Mapeo: Req "Chips de categoría con reinicio de filtros", "Chips de estado".

### T6 — Buscador de equipo (form GET + agrupado por fecha)

Archivos: `resources/views/layouts/fixture.blade.php`.

- [x] RED: test HTML: existe `<form method="GET">` con `<input type="search" name="equipo">` y hidden inputs de `idEdicion`/`idCategoria`/`estado`; con `equipo` activo la respuesta renderiza solo fechas con match, agrupadas por fecha. Falla. <!-- sdd-owner: implementation -->
- [x] GREEN: form con patrón de `Panel/tabla_goleador/index.blade.php:19-30`; loop de agrupado por `idFechas` cuando `equipo` presente; debounce opcional vanilla (`setTimeout` + `location.assign`) si se agrega, sin Alpine. Tests en verde. <!-- sdd-owner: implementation -->
- [x] Verificación: comando anterior ⇒ verde; `grep -n "scrollIntoView" resources/views/layouts/fixture.blade.php` ⇒ 0 resultados recién en T7 (aquí puede convivir). <!-- sdd-owner: implementation -->

Riesgo DOM: el buscador multi-fecha cambia el contenedor de las cards (de una fecha a N grupos) — verificar que el `IntersectionObserver` de la barra sticky (se conserva intacto) no dependa de la estructura eliminada.

Mapeo: Req "Búsqueda de equipo multi-fecha" (render/agrupado).

### T7 — Self-heal JS de `#fecha-N` y remoción del hack de scroll

Archivos: `resources/views/layouts/fixture.blade.php`.

- [x] RED (máximo posible): los tests de feature no ejecutan JS; verificar via salida HTML que el bloque JS del hack (`scrollIntoView`, doble `scrollTo(0,0)`, `pageshow`) ya no se emite: `docker compose exec app php artisan test --compact --filter="FixtureExplorador.*no emite"` con assert `->assertDontSee('scrollIntoView')`. Falla. <!-- sdd-owner: implementation -->
- [x] GREEN: eliminar el bloque JS del hack conservando intacto el `IntersectionObserver` de la barra sticky; agregar script vanilla: si `location.hash` matchea `/#fecha-(\d+)/` y no hay `idFecha` en la query ⇒ `history.replaceState` a `?idFecha=N` + `location.reload()` (comportamiento observable del spec: reemplaza entrada de historial, no agrega). <!-- sdd-owner: implementation -->
- [ ] Verificación manual concreta (self-heal es JS, no cubierto por Pest): (1) `docker compose exec app php artisan serve` o URL del contenedor; abrir `GET /fixture?idEdicion=<real>#fecha-<idReal>` ⇒ la barra de direcciones pasa a contener `idFecha=<idReal>` sin entrada nueva en historial (botón atrás no reaparece el hash) y se muestran los partidos de esa fecha; (2) abrir con `?idFecha=5#fecha-7` ⇒ prevalece `idFecha=5`, sin reload loop; (3) abrir con hash inexistente `#fecha-9999` ⇒ sin error, queda fecha relevante. <!-- sdd-owner: implementation -->
- [x] Verificación final PR2: `docker compose exec app ./vendor/bin/pint --test` + `docker compose exec app php artisan test --compact tests/Feature/FixtureExploradorTest.php tests/Feature/PlanillaPublicaTest.php tests/Feature/NavegacionPublicaTest.php` ⇒ todo verde. `npm run build` si la vista cambió assets. <!-- sdd-owner: implementation -->

Riesgo: enlaces WhatsApp legacy cambian de "todas" a "relevante" — mitigado por chip "Todas" siempre visible (T4) + self-heal (T7).

Mapeo: Req "Self-heal de anchors legacy de fecha", "Compatibilidad con enlaces y pruebas existentes" (regresión final).

## Notas de ajuste de PlanillaPublicaTest

Preferencia estricta: los defaults viven en la vista (`$idFecha ?? null`, `$estado ?? 'todos'`, `$equipo ?? null`). `tests/Feature/PlanillaPublicaTest.php` se edita SOLO si el test del token público sigue fallando con esos defaults; en ese caso se agregan exclusivamente las claves nuevas al array de variables, sin reordenar ni tocar las existentes.

## Mapeo tarea ↔ requirement

| Requirement | Tareas |
|---|---|
| Fecha relevante por defecto | T1, T2 |
| Vista completa opcional (`idFecha=todas`) | T2, T4 |
| Navegación por chips de fecha | T4 |
| Chips de categoría con reinicio de filtros | T5 |
| Chips de estado | T2 (query), T5 (UI) |
| Búsqueda de equipo multi-fecha | T2 (query/escape), T6 (UI/agrupado) |
| Self-heal de anchors legacy | T7 |
| Compatibilidad con enlaces y pruebas existentes | T2, T7 (regresión) |

## Forecast por tarea

| Tarea | Líneas est. | PR |
|---|---|---|
| T1 service | 60–90 | PR1 |
| T2 controlador + defaults | 50–70 | PR1 |
| T3 tests backend | 70–90 | PR1 |
| T4 chips fecha/Todas | 40–60 | PR2 |
| T5 chips categoría/estado | 40–60 | PR2 |
| T6 buscador | 40–60 | PR2 |
| T7 self-heal + borrado hack JS | −40 borrado / +25 | PR2 |
| **Total** | **340–460** | 2 PRs |

PR1 ≈ 180–240 líneas (bajo umbral). PR2 ≈ 120–180 netas (bajo umbral). Total conjunto superaría 400 ⇒ cadena recomendada; decisión final con `ask-on-risk` al cierre de PR1.
