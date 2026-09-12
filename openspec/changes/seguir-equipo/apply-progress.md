# Apply Progress — seguir-equipo (#99)

Branch: `feat/seguir-equipo`. Ejecución T1→T11 con Strict TDD (RED → GREEN →
TRIANGULATE → REFACTOR). Entrega resuelta por el orquestador: **cadena de 2
PRs** (backend/frontend, commits separados a cargo del parent).

## Estado final

- **17/17 tareas de `tasks.md` marcadas `- [x]`** (verificadas por relectura del
  artefacto persistido). Ninguna unchecked.
- Suites relevantes en verde; suite completa 30F/169P con **0 fallos
  introducidos** (todos preexistentes, clasificados abajo).

## Contract JSON implementado (proposal §2.1)

`GET /equipo-seguido?nombre=&idEdicion=` → siempre `200`:

```json
{
  "disponible": true,
  "equipo": { "nombre": "Amigos F.c.", "idCategoria": 3 },
  "proximo": { "rival": "...", "esLocal": true, "fecha_nombre": "Fecha 5", "horario": "Sáb 14/09 · 15:30", "cancha": "Cancha 2" },
  "ultimo": { "rival": "...", "esLocal": false, "goles_favor": 2, "goles_contra": 6 },
  "links": { "fixture": "/fixture?idEdicion=10&equipo=Amigos%20F.c.", "tabla": "/tabla-de-posiciones?idEdicion=10&idCategoria=3&idGrupo=5" }
}
```

`{"disponible": false}` para nombre no resuelto en la edición; `proximo`/`ultimo`
independientes y nullable; `idGrupo` desde partido resuelto → fallback pivot
`equipos_grupos` (solo si no hay ningún partido) → omitir.

## Evidencia TDD

### T1 — RED

`docker compose exec app php artisan test --compact --filter=EquipoSeguido`
→ **8 failed (8 assertions)**: todos por 404 (ruta inexistente), ninguno por
error de sintaxis del test. Criterio de fin de RED cumplido.

### T2–T4 — GREEN

Tras `ShowRequest` + `EquipoSeguidoService` + `ControladorEquipoSeguido` + ruta:

```
Tests:    8 passed (28 assertions)
Duration: 9.47s
```

### T5 — TRIANGULATE + REFACTOR

Test nuevo: espaciado doble interno `"amigos  f.c."` resuelve igual que el
mutator (colapsa `\s+`). Resultado:

```
Tests:    9 passed (30 assertions)
```

Pint sobre archivos nuevos + rutas: **PASS (5 files)**. Nota operativa:
`pint --dirty` no disponible en el contenedor (sin git dentro); se corrió pint
por archivo explícito. ShowRequest requería `fully_qualified_strict_types`
(docblock con FQCN → import) y `routes/web.php` `ordered_imports`; ambos
corregidos manualmente (www-data no puede escribir archivos del host).

## Archivos tocados (superficies permitidas, todas respetadas)

| Archivo | Cambio | Líneas |
|---|---|---|
| `app/Http/Requests/EquipoSeguido/ShowRequest.php` | nuevo | 30 |
| `app/Services/EquipoSeguidoService.php` | nuevo | 176 |
| `app/Http/Controllers/ControladorEquipoSeguido.php` | nuevo | 38 |
| `routes/web.php` | +import +ruta pública `equipo-seguido` | +2 |
| `tests/Feature/EquipoSeguidoTest.php` | nuevo (9 casos) | 234 |
| `app/Http/Controllers/ControladorHome.php` | +`$edicionActual` +`$equiposDeLaEdicion` al compact | +9 |
| `resources/views/welcome.blade.php` | bloque selector+card post-hero | +37 |
| `resources/js/welcome.js` | IIFE al final (filtro/fetch/render/localStorage) | +237 |

Toolbox (fuera del repo, herramienta acordada AGENTS.md §8):
`~/.local/mitai-playwright/verificar-seguir-equipo.js` (nuevo),
`nav-links-seguir.js` (nuevo), `overflowcheck.js` (nuevo).

NO tocados: `.gitignore`, `opencode.json`, `sync-db.sh` (modificaciones
preexistentes del usuario, visibles en `git status` desde el inicio),
`NavegacionPublicaTest`, `TablaposicionesDefaultTest`, `FixtureExploradorTest`.

## Verificaciones (todas en Docker salvo Playwright)

- `--filter=EquipoSeguido` → **9 passed (30 assertions)**.
- `tests/Feature/NavegacionPublicaTest.php` → **8 passed (43 assertions)** (sin
  modificar el test; el bloque nuevo no usa `<nav>` ni `aria-current="page"`).
- Pint: nuevos+rutas **PASS**; `ControladorHome` **FAIL por deuda preexistente**
  (`new_with_parentheses`, `fully_qualified_strict_types`): verificado
  idéntico sobre `git show origin/main:app/Http/Controllers/ControladorHome.php`
  → 0 deuda introducida.
- Suite completa: **30 failed, 169 passed (1093 assertions)**. Clasificación de
  los 30 (todos preexistentes, baseline documentado ~30F):
  Auth/Profile sesión-419 (18: Authentication 4, EmailVerification 1,
  PasswordConfirmation 2, PasswordReset 4, PasswordUpdate 2, Registration 1,
  Profile 4×419), Panel (11: EliminaTablaPosiciones 2, BuscarDni 2
  QueryException, ValidadorReglasJugador 1, PlanillaConfirmacion 3,
  AdminEquiposSinAsistencia 3), PlanillaPublica 1 (zona horaria: espera
  `20:15` recibe `19:45`). **Introducidos: 0.**
- Playwright (T9): 8 escenarios PASS en desktop 1280 y mobile 390:
  1. sin selección → selector visible, card oculta, `localStorage` null;
  2. filtrar "Academia" → lista filtrada en memoria, **0 requests fetch/xhr**;
  3. elegir equipo → card sin recarga, `localStorage` = `{"nombre": ...}` solo;
  4. reload → card sola (selector oculto);
  5. cambiar equipo → card + `localStorage` reflejan el nuevo;
  6. quitar → card fuera, selector visible, clave eliminada;
  7. favorito inexistente → estado (d) "no participa en esta edición" + cambiar/quitar;
  8. favorito sin partidos → estado (c) "Todavía no hay partidos cargados" + links.
- Playwright (T10): navegación real de links — fixture
  `/fixture?idEdicion=10&equipo=Academia%20Intersport` aterriza filtrado (nombre
  visible en página); tabla
  `/tabla-de-posiciones?idEdicion=10&idCategoria=68&idGrupo=120` correcta.
  Capturas leídas con `read`: `/tmp/equipo-seguido-desktop.png`,
  `/tmp/equipo-seguido-mobile.png` (full page), y por estado
  `/tmp/seguir-equipo-e{1,3,7,8}-*.png`. Mobile 390: `scrollWidth=390`,
  sin overflow horizontal del bloque (`overflowcheck.js`).

## Desvíos y decisiones documentadas

1. **`max:50` (no `max:100`)** para `nombre`: el mapa del prompt decía
   `max:100`, pero `tasks.md` T2 y `proposal` §2.1 (autoridad) fijan
   `required|string|max:50` (límite de `equipos.nombre` string(50)). Manda el
   spec.
2. **Normalización replicada en el service** (`EquipoSeguidoService::normalizarNombre()`),
   no extraída a `Equipo`: el modelo está fuera de las superficies de edición
   permitidas. Lógica idéntica al mutator (colapso `\s+` + Title Case mbstring +
   siglas CA/AF/FC/RC). Deuda de deduplicación futura si el modelo se habilita.
3. **Queries**: `with('fecha')` (mandato R3) añade 2 eager loads acotados;
   core queries = 1 equipo + 2 partidos + 1 rivales batched (+1 pivot solo en
   el caso sin ningún partido). Sin N+1; el objetivo anti-N+1 de T5 se cumple
   aunque el conteo total supere la heurística "≤5" si se cuentan los eager.
4. **Sin equipo real con próximo+último** en la edición activa (fixture
   finalizado: todos jugados): el escenario Playwright 3 aserta "próximo **o**
   último", consistente con el spec (información disponible de cada uno de
   forma independiente). La cobertura de ambos simultáneos vive en Pest (RED-1).
5. **`pint --dirty`** no usable en el contenedor (sin git): sustituido por pint
   explícito por archivo; documentado en T11.
6. El bloque Blade usa `<script type="application/json" id="seguir-equipo-datos">`
   con `@json` (datos del selector) y `data-id-edicion` en el contenedor; el JS
   renderiza con DOM APIs (`textContent`) — sin XSS y sin lógica de negocio en
   Blade.
7. Fallos conocidos de nombres duplicados en la edición real (Nottingham,
   Deportivo Morón, etc. en varias categorías): comportamiento contractual R4
   (primer match por `id`); el script evita duplicados en escenarios.

## Workload / PR boundary (cadena 2 PRs, decisión del orquestador)

- **PR1 Backend**: ShowRequest (30) + Service (176) + Controller (38) + routes
  (+2) + tests (234) = **480 líneas** — supera 400; insalvable sin partir el
  service o los tests (el tests son el contract RED del change). Recomendación:
  mantener cohesivo; si el maintainer exige <400, mover
  `EquipoSeguidoTest.php` a PR intermedia.
- **PR2 Frontend**: ControladorHome (+9) + welcome.blade.php (+37) + welcome.js
  (+237) = **283 líneas** — dentro de presupuesto.
- Los diffs de `.gitignore`, `opencode.json`, `sync-db.sh` en el working tree
  son preexistentes del usuario y NO forman parte de esta change.

## Estado estructurado

- `actionContext.mode: repo-local`, edit roots respetados (todas las rutas
  dentro de `/home/facundo/Documentos/Torneo-Mitai-Laravel` + toolbox acordado
  fuera del repo).
- Status nativo marcaba cambio ambiguo; el parent resolvió explícitamente
  `seguir-equipo` → bloqueo no aplica.
- `skill_resolution: paths-injected` (`laravel-blade/SKILL.md`). Su workflow de
  subagentes no aplica (prohibido para este executor); reglas de contenido
  aplicadas (escape `{{ }}`, `@json`, sin componentes nuevos).
