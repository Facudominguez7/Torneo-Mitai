# Tasks — seguir-equipo (#99)

Fuente: `proposal.md` (decisiones cerradas §0, contrato §2.1) y
`specs/portal-publico/spec.md` (9 requirements ADDED). Strict TDD en todo lo
HTTP/service: RED → GREEN → TRIANGULATE → REFACTOR. Todas las validaciones
corren en Docker. Sin dependencias nuevas; JS vanilla; español de dominio.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~420–590 (backend ~170–240, frontend ~150–230, tests ~150–200) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 Backend (request + service + controller + ruta + tests Pest) → PR2 Frontend (ControladorHome + welcome.blade.php + welcome.js + Playwright) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

```text
Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High
```

Estimación supera el umbral de 400 líneas → estrategia `ask-on-risk`: antes de
implementar se pausa y se confirma con el usuario la cadena PR1 → PR2 (patrón
#98: implementación completa en la branch, 2 commits apilados backend/frontend,
un RDD sobre la cadena). `chain_strategy: pending` hasta que el usuario elija.

## Riesgos marcados (transversales)

- **R1 — Matching vs mutator Title Case:** la resolución por nombre debe usar la
  misma normalización que `setNombreAttribute` (colapsar espacios + Title Case
  mbstring). Verificado por RED-5.
- **R2 — Equipos sin partidos en la edición:** `proximo`/`ultimo` nulos
  independientes, nunca error. Verificado por RED-2/RED-3.
- **R3 — N+1 en rivales:** rivales resueltos con un solo `pluck('nombre','id')`
  batched (máx. 2 partidos); `with('fecha')` para el nombre de fecha.
- **R4 — Colisión de nombre entre categorías:** limitación conocida, primer
  match determinístico (`orderBy('id')->first()`). No bloquea; documentar en la
  PR. No testeado como caso de éxito (comportamiento no contractual).
- **R5 — No romper NavegacionPublicaTest:** el bloque nuevo no usa `<nav>` ni
  `aria-current="page"`; regresión explícita en T7.

## Fase 1 — Backend (PR1)

### T1 — RED: tests del endpoint contra el contrato JSON

Archivo: `tests/Feature/EquipoSeguidoTest.php` (nuevo, Pest, datos con
`::create()` siguiendo el patrón de `FixtureExploradorTest.php`; sin factories
de dominio en el repo).

Casos RED (todos `getJson('/equipo-seguido?...')`):

1. RED-1: equipo con próximo y último ⇒ `200`, `disponible: true`, `proximo` y
   `ultimo` presentes con rival/localía/horario/marcador, `links.fixture` y
   `links.tabla` con `idGrupo` cuando el partido lo tiene. *(Req: contrato,
   próximo, último, accesos)*
2. RED-2: equipo solo con último (sin pendientes) ⇒ `proximo: null`, `ultimo`
   presente, `200`. *(Req: próximo, último)*
3. RED-3: equipo sin ningún partido ⇒ `disponible: true`, ambos `null`,
   `links` igual presentes. *(Req: último escenario 2, accesos)*
4. RED-4: nombre inexistente en la edición ⇒ `200` + `{"disponible": false}`.
   *(Req: contrato)*
5. RED-5: `"amigos f.c."` vs `"Amigos F.c."` (R1) ⇒ resuelve el mismo equipo.
   *(Req: contrato, matching tolerante)*
6. RED-6: equipo existente en otra edición ⇒ `{"disponible": false}`, `200`.
   *(Req: contrato)*
7. RED-7: sin `nombre`, `idEdicion` no numérico o inexistente ⇒ `422`.
   *(Req: contrato, validación)*
8. RED-8: visitante sin sesión ⇒ `200` sin auth. *(Req: compatibilidad)*

- [x] Escribir `tests/Feature/EquipoSeguidoTest.php` con los 8 casos RED contra el contrato de proposal §2.1. <!-- sdd-owner: implementation -->
- [x] Implement and verify the behavior. <!-- sdd-owner: implementation -->

**Verificación RED:**
`docker compose exec app php artisan test --compact --filter=EquipoSeguido`
→ los 8 fallan (ruta 404 / clase no existe). Criterio de fin de RED: cada test
falla por ausencia de implementación, no por error de sintaxis del test.

### T2 — GREEN: FormRequest de validación

Archivo: `app/Http/Requests/EquipoSeguido/ShowRequest.php` (nuevo; convención
del repo: FormRequest por dominio, ver `app/Http/Requests/Equipo/`).

Reglas: `nombre` → `required|string|max:50`; `idEdicion` →
`required|integer|exists:ediciones,id`.

- [x] Crear `app/Http/Requests/EquipoSeguido/ShowRequest.php` con las reglas de proposal §2.1. <!-- sdd-owner: implementation -->
- [x] Implement and verify the behavior. <!-- sdd-owner: implementation -->

**Verificación:** `docker compose exec app php artisan test --compact
--filter="EquipoSeguido"` → RED-7 pasa; resto sigue fallando.

### T3 — GREEN: EquipoSeguidoService

Archivo: `app/Services/EquipoSeguidoService.php` (nuevo).
`resolver(string $nombre, int $idEdicion): array`:

- Normaliza el nombre con la misma función del mutator `setNombreAttribute`
  (extraer a método estático reusable si no lo es) — R1.
- Match determinístico: `Equipo::where('idEdicion',...)` + nombre
  normalizado, `orderBy('id')->first()` — R4.
- Próximo/último con la semántica de `FixtureExploradorService` (proposal §2.3),
  `whereNotNull('horario_datetime')`, `with('fecha')`, rivales vía un solo
  `pluck('nombre','id')` — R2, R3.
- Horario formateado con `translatedFormat` dentro del servicio (sin cast en
  `Partido`).
- Links: fixture `/fixture?idEdicion=&equipo=`; tabla con `idGrupo` del partido
  resuelto → fallback pivot `equipos_grupos` → omitir (proposal §2.4).
- `['disponible' => false]` cuando no resuelve.

- [x] Crear `app/Services/EquipoSeguidoService.php` con la lógica de resolución, próximo/último, formateo y links. <!-- sdd-owner: implementation -->
- [x] Implement and verify the behavior. <!-- sdd-owner: implementation -->

**Verificación:** RED-1 a RED-6 y RED-8 pasan.

### T4 — GREEN: ControladorEquipoSeguido + ruta

Archivos: `app/Http/Controllers/ControladorEquipoSeguido.php` (nuevo,
`show(ShowRequest, EquipoSeguidoService): JsonResponse`, controlador fino,
`try/catch` + `Log::error` + 500 solo para fallos de infra, molde
`ControladorPlanillaPublica`) y `routes/web.php` (ruta pública
`Route::get('/equipo-seguido', ...)->name('equipo-seguido')`).

- [x] Crear `ControladorEquipoSeguido::show()` y registrar la ruta pública en `routes/web.php`. <!-- sdd-owner: implementation -->
- [x] Implement and verify the behavior. <!-- sdd-owner: implementation -->

**Verificación GREEN completa:**
`docker compose exec app php artisan test --compact --filter=EquipoSeguido`
→ los 8 casos pasan.

### T5 — TRIANGULATE + REFACTOR backend

- [x] Triangular: caso de nombre con espaciado doble interno (`"amigos  f.c."`) normalizado igual que el mutator (si el mutator no colapsa espacios internos, ajustar la expectativa al contrato real del mutator y documentarlo). <!-- sdd-owner: implementation -->
- [x] Refactor: extraer normalización compartida, revisar queries (contar ≤ 5 queries por request), `docker compose exec app ./vendor/bin/pint --dirty`. <!-- sdd-owner: implementation -->

**Verificación:** suite EquipoSeguido verde tras refactor + `pint --dirty`
sin cambios pendientes.
`docker compose exec app php artisan test --compact
tests/Feature/NavegacionPublicaTest.php` → verde (aún sin tocar vistas).

## Fase 2 — Frontend (PR2)

### T6 — ControladorHome pasa equipos de la edición

Archivo: `app/Http/Controllers/ControladorHome.php` (método `index`).

- Agregar `$equiposDeLaEdicion = Equipo::where('idEdicion', $edicionActual?->id)->orderBy('nombre')->get(['id','nombre','idCategoria'])` al `compact()` (~2–4 líneas; mismo `$edicionActual`, no se re-deriva).

- [x] Agregar `$equiposDeLaEdicion` al `compact()` de `ControladorHome::index()`. <!-- sdd-owner: implementation -->

**Verificación:** `docker compose exec app php artisan test --compact
tests/Feature/NavegacionPublicaTest.php` → verde (regresión home).

### T7 — Selector + card en welcome.blade.php

Archivo: `resources/views/welcome.blade.php`. Bloque acotado (post-hero,
propuesta §2.5/§2.6):

- Selector: input + `<ul>` con los 37 equipos (datos vía `data-*` o
  `<script type="application/json">`), solo si hay edición activa
  (`@if(isset($equiposDeLaEdicion))` + fallback `collect()`).
- Contenedores de card con los 4 estados (a–d) manejados por JS; salida
  escapada con `{{ }}`; cero lógica de negocio en Blade.
- Prohibido `<nav>` y `aria-current="page"` en el bloque (R5).

- [x] Agregar el bloque selector + card a `welcome.blade.php` sin tocar hero/categorías existentes. <!-- sdd-owner: implementation -->

**Verificación:** `docker compose exec app php artisan test --compact
tests/Feature/NavegacionPublicaTest.php` → verde; y Pest del spec
"sin edición activa no hay selector" (si se agrega caso a
`EquipoSeguidoTest`): render sin `$equiposDeLaEdicion` no incluye el bloque.

### T8 — welcome.js: filtro, fetch, card, localStorage

Archivo: `resources/js/welcome.js` (JS vanilla, ya cargado por Vite,
sin dependencias).

- Filtro client-side en memoria (sin requests al filtrar).
- Elegir equipo → `localStorage['mitai_equipo_seguido'] = {"nombre": ...}` +
  `fetch('/equipo-seguido?nombre=&idEdicion=')` + render de card según estado.
- Al cargar la página: leer `localStorage` y fetch automático (persistencia).
- Botones "Quitar" (borra clave, estado sin card) y reabrir selector
  (cambiar equipo).
- Estados: sin selección (sin card) / disponible con próximo y/o último /
  sin partidos ("Todavía no hay partidos cargados...") / no disponible
  ("X no participa en esta edición") + links fixture/tabla.

- [x] Implementar en `resources/js/welcome.js` el filtro, fetch, render de card y manejo de localStorage. <!-- sdd-owner: implementation -->
- [x] Implement and verify the behavior. <!-- sdd-owner: implementation -->

**Verificación HTTP base:** `docker compose exec app php artisan test
--compact --filter=EquipoSeguido` sigue verde; `npm run build` sin errores
(fuera de Docker, build de Vite) o `docker compose exec app npm run build`
si aplica en el contenedor.

## Fase 3 — Verificación Playwright (AGENTS.md §8, obligatoria)

Toolbox: `/home/facundo/.local/mitai-playwright` (`shot.js` para capturas;
scripts Node con `page.evaluate` para aserciones de DOM/estado). Verificar en
desktop 1280 y mobile 390. Revisar cada captura con `read` antes de reportar.

### T9 — Persistencia y flujo del selector

Script: `~/.local/mitai-playwright/verificar-seguir-equipo.js` (nuevo, fuera
del repo):

1. Cargar home → no hay card (estado a); inspeccionar
   `localStorage.getItem('mitai_equipo_seguido')` → `null`.
2. Escribir término parcial en el selector → lista filtrada en memoria;
   contar `request` events → 0 requests nuevos al filtrar.
3. Elegir equipo → card visible sin recarga; `localStorage` contiene
   `{"nombre": ...}` solo con el nombre.
4. `page.reload()` → card aparece sola (persistencia). *(Req: persistencia)*
5. Elegir otro equipo → card y `localStorage` reflejan el nuevo. *(Req:
   cambiar/quitar, escenario 2)*
6. "Quitar" → card desaparece, `localStorage` sin la clave. *(Req:
   cambiar/quitar, escenario 1)*
7. Presembrar `localStorage` con un nombre inexistente en la edición activa →
   estado (d): mensaje "no participa en esta edición" + acciones. *(Req:
   estados, escenario d)*
8. Presembrar `localStorage` con equipo sin partidos (o verificar estado c si
   hay uno real en la edición) → mensaje "Todavía no hay partidos cargados" +
   links. *(Req: estados, escenario c)*

- [x] Escribir y correr el script Playwright de flujo completo en desktop 1280 y mobile 390. <!-- sdd-owner: implementation -->

**Comandos:**
```
node ~/.local/mitai-playwright/verificar-seguir-equipo.js <url-home> 1280
node ~/.local/mitai-playwright/verificar-seguir-equipo.js <url-home> 390
```
**Resultado esperado:** todas las aserciones en verde en ambos viewports;
salida con los estados transitados.

### T10 — Capturas y card con próximo/último + links

- Card con próximo y último visible (equipo real de la edición activa con
  fixture cargado) en desktop y mobile. *(Req: estados b, accesos)*
- Click en link fixture de la card → `/fixture?equipo=...` ya filtrado.
- Click en link tabla → tabla de la categoría del equipo (con `idGrupo` si
  corresponde).

- [x] Capturar y revisar: `node ~/.local/mitai-playwright/shot.js <url-home> equipo-seguido-desktop.png 1280 900` y `node ~/.local/mitai-playwright/shot.js <url-home> equipo-seguido-mobile.png 390 900`; verificar links con `page.evaluate` (href) + navegación. <!-- sdd-owner: implementation -->

**Verificación:** capturas leídas con `read`; card legible en 390 (sin
overflow horizontal); links apuntan a las URLs esperadas.

## Fase 4 — Cierre (Verifier)

### T11 — Suite completa + pint + regresión

- [x] Correr en Docker: `docker compose exec app ./vendor/bin/pint --test` (o `--dirty` previo), `docker compose exec app php artisan test --compact --filter=EquipoSeguido`, `docker compose exec app php artisan test --compact tests/Feature/NavegacionPublicaTest.php` y la suite completa `docker compose exec app php artisan test --compact`. <!-- sdd-owner: implementation -->

**Criterio:** todo verde sin modificar `NavegacionPublicaTest.php` (R5).

## Mapeo tarea ↔ requirement (9)

| Requirement (spec) | Tareas |
|---|---|
| 1. Selección de equipo sin autenticación | T6, T7, T8, T9 (filtro sin red), T11 |
| 2. Persistencia local del equipo seguido | T8, T9 (escenarios 3–4) |
| 3. Contrato del endpoint | T1 (RED-1,4,5,6,7), T2, T3, T4, T5 |
| 4. Próximo partido | T1 (RED-1, RED-2), T3 |
| 5. Último resultado | T1 (RED-1, RED-3), T3 |
| 6. Estados de la card | T7, T8, T9 (escenarios 1, 7, 8), T10 |
| 7. Accesos directos fixture/tabla | T1 (RED-1, RED-3), T3, T10 |
| 8. Cambiar o quitar | T8, T9 (escenarios 5–6) |
| 9. Compatibilidad navegación / sin sesión | T1 (RED-8), T7, T11 |

## Forecast por tarea

| Tarea | Líneas est. | Riesgo |
|---|---|---|
| T1 tests RED | ~150–200 | Bajo |
| T2 ShowRequest | ~15–25 | Bajo |
| T3 Service | ~80–110 | Medio (R1, R2, R3) |
| T4 Controller + ruta | ~30–45 | Bajo |
| T5 Triangulate/Refactor | ±20 | Bajo |
| T6 ControladorHome | ~2–4 | Bajo |
| T7 welcome.blade.php | ~60–100 | Medio (R5) |
| T8 welcome.js | ~90–130 | Medio |
| T9–T10 Playwright | scripts fuera del repo | — |
| **Total** | **~430–640** | **High vs 400** |

## Entrega

Umbral 400 superado en estimación → `ask-on-risk`: **antes de implementar**,
confirmar con el usuario:

1. Cadena PR1 (backend) → PR2 (frontend), patrón #98 (2 commits apilados,
   RDD sobre la cadena), o
2. `size:exception` explícito si prefiere una única PR (~430–640 líneas).
