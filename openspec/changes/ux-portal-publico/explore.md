# Exploration — UX portal público (issues #96–#104)

Phase: SDD explore. No application code was modified.

## 1. Current state per public surface

Routes (all `GET`, all in `ControladorHome`, no middleware, `routes/web.php:47-58`):

| Route name | Path | Controller method | Main view |
|---|---|---|---|
| `home` | `/` | `index` | `welcome.blade.php` |
| `fixture` | `/fixture` | `fixture` | `layouts/fixture.blade.php` |
| `tabla-posiciones` | `/tabla-de-posiciones` | `tablaPosiciones` | `layouts/tabla-posiciones.blade.php` |
| `tabla-goleadores` | `/tabla-de-goleadores` | `tablaGoleadores` | `layouts/tabla-goleadores.blade.php` + `partials/goleadores-resultados.blade.php` |
| `tabla-vallas` | `/tabla-de-vallas` | `tablaVallas` | `layouts/tabla-vallas.blade.php` |
| `instancias-finales` | `/instancias-finales` | `instanciasFinales` | `layouts/instancias-finales.blade.php` |
| `campeones` / `subcampeones` / `goleadores` / `vallas` | — | historical pages, `idEdicion` only | — |

Observed sizes: `welcome.blade.php` ~440 lines, `layouts/fixture.blade.php` ~566 lines, `layouts/tabla-vallas.blade.php` ~325 lines, `layouts/tabla-goleadores.blade.php` ~205 lines, `partials/goleadores-resultados.blade.php` ~115 lines, `layouts/navigation.blade.php` ~100 lines.

### `index` (home) — `ControladorHome.php:26-54`
Loads only `ediciones`, `ultimaEdicion`, `EdicionSeleccionada`, `categorias`, `idEdicion`, `idCategoria`, `roles`. **No matches are loaded at all.** The home has a hero, an edition `<select>` that submits on change, a horizontal category strip linking to the fixture, and a large institutional "Historial" block (Campeones / Subcampeones / Vallas / Goleadores cards). `welcome.blade.php` also carries its own inline `<footer>` instead of `@include('layouts.footer')` like the other public views.

Consequence for **#100**: the "próximos partidos" block is new data, so #100 is not view-only — it needs `index()` to load upcoming matches. Same for **#99**, which needs a team list for the selector.

### `fixture` — `ControladorHome.php:402-516`
Reads `idEdicion`, `idCategoria`, `idFecha` (via magic property `$request->idEdicion`, not `->query()`), plus `estado` (`todos|proximos|finalizados`, default `todos`) and `orden` (`asc|desc`, defaulting to `asc` for `proximos`, `desc` otherwise). The `estado` filter is fully implemented server-side on both query branches.

**The single most useful finding for #98: `estado` and `orden` have no UI whatsoever.** There is no filter control, no team search input, no `data-filtro` hook in `fixture.blade.php`. The backend for "próximos / finalizados / todos" already exists and is unreachable.

Also already present:
- `?idFecha=N` is supported server-side and loads only that date's matches.
- The "Fechas" bar (`fixture.blade.php:54-92`) navigates with **anchor links** (`href="#fecha-{{ $f->id }}"`), not URLs, so the page must render every date to make them work: `@foreach ($fechasNavegacion as $fechaNavegacion)` at line 162 renders all jornadas, all groups, all cards. That is the DOM bloat #98 targets.
- "Relevant date" today is a **client-side scroll hack** (`fixture.blade.php:~540-560`): it collects `.partido-card`, filters `dataset.jugado === '0'`, sorts by `dataset.horario` and calls `scrollIntoView`. Server-side defaulting of `idFecha` replaces this hack.
- Every card already carries `data-jugado`, `data-horario`, `data-local`, `data-visitante` (lowercased), so a client-side team search needs no backend change.

So #98 ≈ swap anchors for real `?idFecha=` links + expose the existing `estado`/`orden` params in the UI + server-side default for `idFecha`. Much smaller than the issue text implies.

### `tablaPosiciones` — `ControladorHome.php:518-547`
Fully URL-first: `idCategoria`, `idGrupo`, `idEdicion` via `$request->query()`. No pagination, no search, no session. Lowest-risk surface.

### `tablaVallas` — `ControladorHome.php:322-401` ⭐ reference implementation
This is the **URL-first pattern already established in this repo**, and it is exactly the shape goleadores needs:
- controller: `idEdicion`, `idCategoria`, `sort`, `dir` all from `$request->query()`; `$request->has('dir')` for the default direction;
- pagination state preserved via `$equipos->appends($appendParams)` with `array_filter([...])` (`ControladorHome.php:372-379`);
- view: `$queryBase = array_filter(request()->except(['page']))` and sort links built as `route('tabla-vallas', array_merge($queryBase, ['sort' => ..., 'dir' => ...]))` (`tabla-vallas.blade.php:107,132-146`);
- an `updateUrl(params)` JS helper (`tabla-vallas.blade.php:296-306`) — but note it ends in `window.location.href = newUrl`, i.e. a **full reload, not History API**. There is no `pushState` anywhere in the repo, so that part of #101 is genuinely new.
- it has an `#sin-coincidencias` empty-state node, a precedent for #102's better empty message.

### `tablaGoleadores` — `ControladorHome.php:549-663` — the only offender
PRG on session key `tabla_goleadores_filtros`, keys `['idEdicion','idCategoria','sort','dir','search_value','page']`:
1. if `! empty($request->query())` → merge into session;
2. filter change (anything but `page`) resets `page` to 1;
3. if not `$request->ajax()` → `redirect()->route('tabla-goleadores')` (bare, no params);
4. state is then read **only** from session.

Downstream consequences in the views, all of which must change together with the controller:
- `goleadores-resultados.blade.php:19-58`: sort links are `route('tabla-goleadores', ['sort' => ..., 'dir' => ...])` — they **drop** `idEdicion`, `idCategoria` and `search_value` because session holds them. Needs the vallas `$queryBase` treatment.
- `goleadores-resultados.blade.php:112`: `{{ $goleadores_t->links() }}` with **no `appends()`/`withQueryString()`** in the controller, so pagination links emit only `?page=N`.
- `tabla-goleadores.blade.php:139-176`: `loadResults()` sends only the changed param and relies on server-side session merge. Under URL-first, each AJAX call must send the **full** state.

### `instanciasFinales` — `ControladorHome.php:664-745`
URL-first, but with **inconsistent param names**: `idEdicion`, then `categoria`, `copa`, `fase` — no `id` prefix, unlike every other surface. Also the only surface that defaults `idEdicion` to `Edicion::latest('id')->first()->id`.

### `navigation.blade.php`
**There is no section navigation today.** The nav contains only the logo/edition title link, the admin button (`@auth` + `rol === 'admin'`), and the user dropdown or the login button. So `Inicio | Partidos | Posiciones | Goleadores | Finales` (#97) is a greenfield addition to a single ~100-line file.

Good news: the edition-preserving pattern already exists there — `$edicionActiva = $EdicionSeleccionada ?? $ultimaEdicion ?? null` and `$homeUrl = optional($edicionActiva)->id ? route('home', ['idEdicion' => ...]) : route('home')` (lines 4-9). Blade `??` tolerates undefined variables, so this keeps working across views that pass different variable sets. Reuse this idiom for the new links.

Caveat: `tabla-posiciones` and `tabla-goleadores` links need `idCategoria`/`idGrupo` to show anything useful, and the nav only has the edition. Linking to `tabla-posiciones` with only `idEdicion` renders "Categoría no encontrada" with an empty table. **This is an open question the proposal must answer.**

## 2. Session-dependency inventory

Searched `session(`, `redirect()->route(`, `$request->query(` across `ControladorHome.php`:

- **`tablaGoleadores` is the only public surface using session or PRG.** Lines 556-579 are the only hits; there is no other `redirect()->route(` in any public method.
- Every other public method (`index`, `campeones`, `subcampeones`, `goleadores`, `vallas`, `tablaVallas`, `fixture`, `tablaPosiciones`, `instanciasFinales`) reads state from `$request->query()` (or the magic property, in `fixture`).
- `welcome.blade.php` reads `session('warning'|'success'|'error'|'info'|'status')`, but those are flash messages for alerts, not filter state. Out of scope.

**Conclusion: #101 is a point fix, not a transversal refactor.** The transversal part of #101 is not "remove session" — it is only:
(a) port goleadores to the existing vallas URL-first pattern;
(b) add History API URL sync where AJAX exists (goleadores only; vallas reloads the page);
(c) optionally normalize the `instancias-finales` param names.

That last one is the only place where "stable, readable names" implies a rename, and it breaks existing shared links. Flagged below.

## 3. URL param convention already in place

| Surface | Params in use |
|---|---|
| `home` | `idEdicion`, `idCategoria` |
| `campeones` / `subcampeones` / `goleadores` / `vallas` | `idEdicion` |
| `fixture` | `idEdicion`, `idCategoria`, `idFecha`, `estado`, `orden` |
| `tabla-posiciones` | `idEdicion`, `idCategoria`, `idGrupo` |
| `tabla-vallas` | `idEdicion`, `idCategoria`, `sort`, `dir` |
| `tabla-goleadores` | `idEdicion`, `idCategoria`, `sort`, `dir`, `search_value`, `page` (session-bound today) |
| `instancias-finales` | `idEdicion`, `categoria`, `copa`, `fase` ← **inconsistent** |

Established and consistent: `idEdicion`, `idCategoria`, `idGrupo`, `idFecha` (legacy DB naming, matches the project convention), plus `sort`/`dir`/`page`/`estado`/`orden`. `search_value` is snake_case and the only one of its kind, but it is already public in `tabla-goleadores` links and asserted in a test.

**Recommendation: do not rename anything in the first pass.** Adopt the existing set as the stable contract and treat `instancias-finales` (`categoria`→`idCategoria`, etc.) as a separate, optional unit with backwards-compatible reads if it is done at all.

## 4. Reusable Blade components

`resources/views/components/` (18 anonymous components, Breeze-derived):

| Component | Relevance |
|---|---|
| `dropdown.blade.php` | Alpine dropdown with `trigger`/`content` slots. **Already used by the goleadores category filter and by the nav user menu.** #103 fixes the *contents* of the slot, not this component. |
| `dropdown-link.blade.php` | Proper `<a>` for dropdown items — the natural replacement for the clickable `div`s. |
| `nav-link.blade.php`, `responsive-nav-link.blade.php` | Breeze nav links with an `:active` prop. **Direct candidates for #97**, though their styling is default Breeze and does not match the dark/`#f7f280` public theme; expect a restyle. |
| `loader.blade.php` | Existing loader; check before writing a #102 skeleton. |
| `text-input.blade.php`, `primary-button.blade.php`, `secondary-button.blade.php`, `danger-button.blade.php` | Generic form controls. |
| `modal.blade.php`, `modal-promo.blade.php`, `planilla-publica-modal.blade.php` | `planilla-publica-modal` is included by `fixture.blade.php` and `instancias-finales.blade.php`. |
| `application-logo`, `auth-session-status`, `input-error`, `input-label`, `input-label-profile`, `primary-button-profile` | Not relevant. |

There is **no** card, table, badge, skeleton or empty-state component. Home/goleadores card markup is inlined and duplicated.

For #103 concretely: the goleadores category dropdown items are `<div class="cursor-pointer ..." data-id="..." data-nombre="...">` (`tabla-goleadores.blade.php:74-86`) — not focusable, not keyboard-operable. **`tabla-vallas.blade.php:67-73` has the identical defect.** The issue text only mentions goleadores; whether vallas is in scope is an open question.

## 5. Existing tests

`tests/Feature/` root has 5 files (`ExampleTest`, `PlanillaPublicaTest`, `ProfileTest`, `TestingDatabaseIsolationTest`, `WebPushRemovalTest`) plus `tests/Feature/Panel/`. Public-surface coverage is thin.

### `tests/Feature/Panel/TablaGoleadoresBusquedaTest.php` — the safety net, and probably already red
Two Pest tests hitting `route('tabla-goleadores', [...])` **with query params** and asserting `assertOk()`. The second also asserts `assertSee('search_value=ulises', false)`.

Static reading of `ControladorHome.php:556-575`: a non-AJAX request carrying a query string returns `redirect()->route('tabla-goleadores')`, i.e. **302**. `assertOk()` asserts 200. And `search_value=ulises` cannot appear in the HTML, because sort links drop `search_value` and the paginator has no `appends()`.

**Hypothesis (must be confirmed, I have no shell in this phase): both tests in this file are currently failing on `main`.** They read as tests written *for* the URL-first behaviour of the reverted #96. If confirmed, they are a ready-made red-to-green acceptance gate for the first work unit — no new test needed to justify it.

Verification command for the next phase:
`docker compose exec app php artisan test --compact --filter=TablaGoleadoresBusqueda`

### `tests/Feature/PlanillaPublicaTest.php` — the fragile coupling
`test_fixture_renderiza_sin_explotar_si_falta_public_token` (line 486) and `test_instancias_finales_renderiza_sin_explotar_si_falta_public_token` (line 519) call `view('layouts.fixture', [...])->render()` and `view('layouts.instancias-finales', [...])->render()` with an **explicit, exhaustive variable array**.

Any new *required* variable in either view breaks these tests. #98 will want `$estado`, `$fechaAnterior`, `$fechaSiguiente`, `$fechaActual`. **Mitigation: give every new view variable a Blade-level default (`$estado ?? 'todos'`) rather than assuming the controller always provides it.** Cheap, and keeps these two tests green.

### Other
`ImprimirFixtureCompletoA4Test`, `AdminEquiposSinAsistenciaTest` hit `admin.imprimir-*` routes — admin-only, unaffected. No test covers `home`, `navigation`, `tabla-posiciones` or `tabla-vallas`.

### Pre-existing debt (not a regression)
`./vendor/bin/pint --test app/Http/Controllers/ControladorHome.php` already fails on `origin/main` due to fully-qualified imports (`\Illuminate\Support\Facades\DB::raw` inline at lines 596 and 605 while `DB` is already imported at the top). Verify with `--dirty` scoped to touched files and do not let it be mistaken for new breakage.

## 6. Issue ↔ file coupling matrix

| File | #96/#101 | #97 | #98 | #99 | #100 | #102 | #103 | #104 |
|---|---|---|---|---|---|---|---|---|
| `ControladorHome::tablaGoleadores` | ●● | | | | | ○ | | |
| `ControladorHome::fixture` | ○ | | ●● | | | | | |
| `ControladorHome::index` | | | | ●● | ●● | | | |
| `layouts/navigation.blade.php` | | ●● | | | | | ○ | |
| `layouts/tabla-goleadores.blade.php` | ●● | | | | | ●● | ●● | |
| `partials/goleadores-resultados.blade.php` | ●● | | | | | ●● | ○ | ●● |
| `layouts/fixture.blade.php` | | | ●● | | | | ○ | |
| `welcome.blade.php` | | | | ●● | ●● | | ○ | |
| `layouts/tabla-vallas.blade.php` | | | | | | | ○ | |
| `layouts/instancias-finales.blade.php` | ○ | | | | | | | |

●● = primary owner · ○ = incidental / optional touch

**Hotspots:**
1. **`tabla-goleadores.blade.php` + `goleadores-resultados.blade.php`** are contended by #101, #102, #103 and #104. These four cannot go in parallel. Worse, they are not merely co-located: #101 changes how sort/pagination links and the AJAX payload are built, and #102/#104 rewrite the very blocks those links live in. #101 must land first or the others get rewritten twice.
2. **`ControladorHome.php`** is touched by #96/#101 (`tablaGoleadores`), #98 (`fixture`) and #99/#100 (`index`) — three disjoint methods, so parallel work is mergeable, but rebases on a 1200-line file are noisy. Prefer sequential.
3. **`welcome.blade.php`** is contended by #99 and #100. #100 restructures the page that #99 injects into. Do #100 first, then #99 drops into the CTA slot #100 created.

## 7. Recommended sequence

Threshold is 400 lines per PR. Estimates are changed lines including tests.

| # | Unit | Issues | Files | Est. lines |
|---|---|---|---|---|
| 1 | **Goleadores URL-first.** Port the `tablaVallas` pattern verbatim: read all filters from `$request->query()`, `appends()` on the paginator, `$queryBase` for sort links, full state in each AJAX call. Delete the session key and the redirect. | #96, #101 | `ControladorHome::tablaGoleadores`, `layouts/tabla-goleadores.blade.php`, `partials/goleadores-resultados.blade.php`, `TablaGoleadoresBusquedaTest` | ~120-180 |
| 2 | **History API sync + Back/Forward.** `pushState` on filter/sort/page change, `popstate` re-renders from the URL. Depends on unit 1. | #101 | `layouts/tabla-goleadores.blade.php` | ~80-120 |
| 3 | **Goleadores states.** Skeleton, mutually exclusive states, recoverable error with Retry, empty message quoting the search term, `aria-live`, focus retention. | #102 | both goleadores views (+ maybe a new `x-skeleton`) | ~150-220 |
| 4 | **Goleadores mobile ranking + a11y.** 1-2-3 podium hierarchy, compact list from 4th, `div`→`button` in the category dropdown, focus-visible, review `text-[10px]`/`text-[11px]` and `tracking-[0.35em]`, contrast. No change to the goleadores calculation. | #104, #103 | both goleadores views, optionally `tabla-vallas.blade.php` | ~200-300 |
| 5 | **Public navigation.** 5 sections, active highlight, edition carried through, mobile compact/bottom nav, keyboard + focus. | #97 | `layouts/navigation.blade.php`, maybe `nav-link.blade.php` | ~120-200 |
| 6 | **Fixture explorer — navigation.** Real `?idFecha=` links replacing anchors, prev/current/next, server-side default to the relevant date, render one date only. Removes the scroll hack. | #98a | `ControladorHome::fixture`, `layouts/fixture.blade.php` | ~200-300 |
| 7 | **Fixture explorer — filters + search.** Expose the existing `estado`/`orden` in the UI, client-side team search over the existing `data-local`/`data-visitante`. | #98b | `layouts/fixture.blade.php` | ~120-180 |
| 8 | **Home restructure.** Hero, primary/secondary CTAs, upcoming-matches block (needs `index()` to load them), Posiciones/Goleadores/Finales demoted to secondary, institutional history moved down. | #100 | `ControladorHome::index`, `welcome.blade.php` | ~250-380 |
| 9 | **Follow my team.** Team selector, `localStorage` persistence, next match + last result, shortcuts, handling a team missing from the newly selected edition. | #99 | `ControladorHome::index`, `welcome.blade.php`, new partial | ~250-350 |

**9 chained PRs.** Hard ordering: 1 → 2 → 3 → 4 (same two files, escalating); 8 → 9 (same file); 6 → 7 (same file). Unit 5 is independent of everything and is the natural parallel track if two people work at once.

Note that #98 splits in two because the single issue would otherwise be ~350-450 lines in a 566-line file — right at the threshold. Unit 8 is the one most at risk of exceeding 400; if it does, split the upcoming-matches block out.

## 8. Risks

1. **#98 changes the meaning of existing shared links.** `/fixture?idEdicion=X&idCategoria=Y` currently renders every date; welcome's category strip (`welcome.blade.php`, "Accesos directos") emits exactly that shape, and those URLs are presumably shared in WhatsApp. If the default becomes "relevant date only", every existing link silently narrows. Mitigation: keep `idFecha` absent ⇒ relevant date, and add an explicit "Todas las fechas" affordance so the old behaviour stays reachable. The proposal must state which one wins.
2. **#98 vs `PlanillaPublicaTest`.** Explicit `view(...)` var arrays; new required variables break it. Default every new variable inside the view.
3. **#98 removes the scroll hack.** `window.scrollTo(0,0)` + `scrollIntoView` + the `pageshow`/`persisted` handler are entangled with the sticky dates bar (`IntersectionObserver` on `#fixture-fechas-sentinel`, plus a spacer whose height is synced on resize). Deleting the "render everything" loop also invalidates the anchor `href="#fecha-N"` links and the "Todas" anchor. Expect sticky-bar breakage, not just a smaller DOM.
4. **#99 `localStorage` + edition change.** A favourite is meaningless across editions: `equipos` rows are per `idEdicion`/`idCategoria`, so the "same" club is a *different* row with a different `id` in each edition. Storing a bare `idEquipo` breaks on every edition switch. Store the team *name* (or resolve by name within the active edition) and degrade gracefully to "your team is not in this edition". No personal data — a team id/name is fine, but do not store DNI, emails or anything from the planilla flows.
5. **#101 History API + AJAX.** Today the AJAX call sends only the changed param and lets the server merge from session. URL-first requires the client to hold and resend full state; a partial payload will silently reset filters. This is the most likely functional regression in unit 1.
6. **`TablaGoleadoresBusquedaTest` may be red on `main`.** If confirmed, unit 1 is a red-to-green fix and the team should not mistake the current red for something it introduced. If it is *green*, my static reading of the PRG branch is wrong and unit 1's assumptions need re-checking before design.
7. **`pint --test` on `ControladorHome.php` fails on `main`.** Pre-existing. Scope formatting checks to `--dirty` or the PR's files.
8. **Local data trap for manual validation.** Editions 2, 3 and 6 have `tabla_goleadores` rows but zero survive the INNER JOINs against `jugadores`/`equipos`/`categorias`, so they legitimately render "No existen registros". Validate with editions 4, 5, 7, 8, 9 or 10 (edition 10 has 242 joinable rows).
9. **#103's scope is ambiguous.** The clickable-`div` dropdown defect is identical in `tabla-vallas.blade.php`. Fixing only goleadores leaves a known a11y bug one file over; fixing both grows the diff. The `text-[10px]`/`text-[11px]`/`tracking-[0.35em]` pattern is repo-wide across every public view — a blanket sweep would be enormous.

## 9. Open questions for the proposal

1. **Nav targets without a category (#97).** `tabla-posiciones` needs `idCategoria` + `idGrupo`; `goleadores`/`instancias-finales` work with `idEdicion` alone. Does "Posiciones" link to a category picker, to the first category, or is it a landing page that has to be built?
2. **Fixture default (#98).** Does an existing `?idEdicion=X&idCategoria=Y` link keep rendering all dates, or narrow to the relevant one? What defines "relevant" — the next unplayed match by `horario_datetime`, or the most recent date with results?
3. **Param rename (#101).** Leave `instancias-finales` on `categoria`/`copa`/`fase`, or normalize to `idCategoria`/`idCopa`/`idFase` with backwards-compatible reads? Likewise, is `search_value` kept or renamed to `q`? Both renames break existing shared links.
4. **#103 blast radius.** Goleadores only, or also the identical `tabla-vallas` dropdown? Is the typography/tracking review scoped to goleadores or portal-wide?
5. **Favourite team identity (#99).** Store name or id? What is the exact copy when the favourite does not exist in the selected edition?
6. **Skeleton (#102).** New `x-skeleton` component, or inline? Does the existing `components/loader.blade.php` already cover it?
7. **`TablaGoleadoresBusquedaTest` status.** Confirm red/green on `main` before designing unit 1.
8. **Bottom nav (#97).** Fixed bottom nav on mobile would overlap the fixture's sticky dates bar and the paginator. Is a compact top nav acceptable instead?

## Verification performed

Read-only. No commands executed (no shell in this phase); no application files created or modified. The only file written is this exploration note.
