# Exploration — #98 Fixture explorer

Phase: SDD explore. No application code modified. Read-only static analysis.

**Scope limitation up front:** this executor has no shell tool, so the requested tinker DB dimensioning (dates per edition, matches per date) **could not be performed**. Section 3 gives the exact commands to run and the schema facts that constrain the answer. Treat the DOM numbers as un-measured.

## 1. `ControladorHome::fixture()` — anatomy

`app/Http/Controllers/ControladorHome.php:402-516`. No FormRequest, no service, everything inline.

### Input
| Param | How read | Default | Notes |
|---|---|---|---|
| `idEdicion` | `$request->idEdicion` (magic property) | none | Nothing loads if absent: `$partidos` stays an empty collection |
| `idCategoria` | `$request->idCategoria` (magic) | none | Optional; without it, matches of the whole edition are loaded |
| `idFecha` | `$request->idFecha` (magic) | none | **Already supported**; narrows to one date |
| `estado` | `$request->query('estado', 'todos')` | `todos` | `todos\|proximos\|finalizados`, applied in both query branches |
| `orden` | `$request->query('orden')` | `asc` if `estado=proximos`, else `desc` | Whitelisted to `asc\|desc`; applied to `partidos.horario_datetime` |

The magic-property reads also pick up POST/route data; `tablaVallas` uses `$request->query()` consistently. Normalising `fixture` to `->query()` is a cheap consistency win but changes nothing functionally for GET.

### Queries
1. `$categorias` — `Categoria::where('idEdicion', ...)` only when an edition is selected.
2. `$fechas` — only when a **category** is selected: `Fecha::where('idEdicion')` AND (`idCategoria = X` OR `idCategoria IS NULL`), `select('id','nombre')`, `orderByDesc('id')`, `distinct('nombre')` (this `distinct()` is a no-op in Eloquent — it does not dedupe by `nombre`). **When no category is selected, `$fechas` stays `null`.**
3. `$grupos` — by category, or all groups of the edition if no category.
4. `$partidos` — two branches:
   - **with `idFecha`**: joins `equipos` twice + `categorias`, `where idFechas = X`, filtered by `estado`, ordered by `horario_datetime`. Also resolves `$nombreFecha`.
   - **without `idFecha`** (the default, the bloat): **exactly the same query without the `idFechas` filter — i.e. every match of the edition (and category, if given) in one collection, no pagination, no limit.** Additionally joins `fechas as f` to bring `nombre_fecha`.

Both branches return `->get()` (Collection, never a paginator).

### Output to the view
`compact('nombreFecha','nombreCategoria','grupos','ediciones','EdicionSeleccionada','categorias','idCategoria','CategoriaSeleccionada','fechas','partidos')` — 10 variables.

Note: **`idEdicion`, `idFecha`, `estado` and `orden` are NOT passed to the view.** The view has no way to know the current state beyond `EdicionSeleccionada->id` and `idCategoria`. Any URL-building in the view today has to reconstruct it. This is the first thing the design must fix.

### N+1 alert
The view calls `$p->fecha->nombre` on every card and `$partidos` is never `with('fecha')`-eager-loaded. In the `idFecha` branch the `fecha` relation is not selected either. With "all dates" rendered this is one query per match. Rendering a single date shrinks this too, but eager loading should be explicit.

## 2. `resources/views/layouts/fixture.blade.php` — anatomy

~566 lines, monolithic, no components other than `<x-planilla-publica-modal />`.

### Structure
1. `@include('head')`, background image, `x-data="crearPlanillaPublica()"` on the root wrapper (the only Alpine state in the page — used exclusively by `abrirPlanilla(url)` on the "Ver planilla" buttons), `@include('layouts.navigation')`.
2. **Hero** — title `Fixture {{ $nombreCategoria }}`, a chip showing `$nombreFecha ?? 'Seleccioná una fecha'`, and a link to `instancias-finales` with `idEdicion`. The hero copy literally promises "filtrá por categoría, fecha o estado".
3. `@php $partidosPorFecha = $partidos->groupBy('idFechas'); $fechasNavegacion = ... @endphp` — `$fechasNavegacion` is `$fechas` filtered to those that actually have matches; when `$fechas` is null it is synthesised from the grouped matches.
4. **Sticky dates bar** (`#fixture-fechas-bar`) — a horizontally scrollable chip row: a "Todas" chip pointing at `#fixture-contenido`, then one chip per date with `href="#fecha-{{ $f->id }}"`. **Pure anchors. No `?idFecha=` link anywhere in the view.**
5. **Grupos quick-access** — two variants (mobile chip strip + desktop card panel) linking to `tabla-posiciones` with `idCategoria`/`idGrupo`/`idEdicion`. This is the "accesos a tabla de posiciones" the issue says to preserve, and it is duplicated markup.
6. **Content** (`#fixture-contenido`) — `@foreach ($fechasNavegacion as $fechaNavegacion)` → per date a `<section id="fecha-N" class="scroll-mt-28">` → `@foreach ($grupos as $g)` → cards; then a second, **almost fully duplicated ~120-line block** for interzonal matches (`whereNull('idGrupo')`) with amber styling. The two card templates are copy-paste twins differing only in colours and an "Interzonal" badge.
7. `@else` empty state with three messages (no category / no groups / no matches).

### Card contract (already useful)
Each `<article class="partido-card" id="partido-{{ $p->id }}">` carries `data-jugado`, `data-horario` (ISO8601), `data-local`, `data-visitante` (both `strtolower`). **A client-side team search needs zero backend change** — the hooks exist. `id="partido-N"` is an existing deep-link anchor target.

Status badge is computed in Blade per card: `Finalizado` if `jugado`, else `Próximo` if `horario_datetime` is future, else `Pendiente`. So the three-way distinction the acceptance criteria ask for **already exists visually**; what is missing is filtering by it.

### JS at the bottom (no Alpine, vanilla, inline `<script>`)
- `fijarBarraFechas` / `soltarBarraFechas` / `sincronizarAlturaSpacer` + an `IntersectionObserver` on `#fixture-fechas-sentinel` toggling `fixed inset-x-0 top-0` on the bar and syncing a spacer's height. Resize-bound.
- `window.scrollTo(0,0)` twice (immediate + 100ms timeout).
- **The "relevant date" hack**: collect `.partido-card`, keep `dataset.jugado === '0'`, parse `dataset.horario`, keep future ones, sort ascending, `scrollIntoView({behavior:'smooth', block:'center'})` on the first, plus a 3s ring highlight. Falls back to the first unplayed card.
- `pageshow` + `event.persisted` → `scrollTo(0,0)`.

**Correction to the umbrella note:** the active date is *not* marked on scroll — there is no scroll-spy. The bar highlights only the static "Todas" chip. There is no `IntersectionObserver` over the date sections. So less sticky machinery to preserve than feared; the observer is only about pinning the bar.

### What does NOT exist in the view
- **No category filter UI at all.** No `<select>`, no chips, no form. `$categorias` and `$ediciones` are passed to the view and **never used**. The category arrives exclusively from the incoming link (welcome's strip, `tabla-posiciones`' back-link). The issue's "mantener filtros por categoría y fecha" is really **"build them"**.
- No `estado`/`orden` control.
- No team search input.
- No `idFecha` link, no prev/next.

## 3. DB dimensioning — NOT MEASURED (blocked)

### Schema facts that matter more than the counts
`database/migrations/2025_09_14_114210_create_fechas_table.php`: the `fechas` table is `id`, `idCategoria` (nullable), `idEdicion`, `nombre` (string 250). **There is no date/order column.** A "fecha" is a label like `'1° Fecha'`.

Consequences for the design, and these are hard constraints:
1. **"Previous / next date" has no natural ordering.** Options: (a) `fechas.id` (what the controller already uses, `orderByDesc('id')`), (b) `MIN(partidos.horario_datetime)` per fecha, (c) parse `nombre`. Only (b) is semantically a calendar order; (a) is insertion order and is what the current chip strip shows (newest first).
2. **"Relevant date" must also be derived from `partidos.horario_datetime`**, not from `fechas`. Natural definition: the fecha owning the earliest unplayed match with `horario_datetime >= now()`; fallback, the fecha owning the most recent played match. That is exactly the JS hack's logic, moved server-side.
3. `fechas` rows with `idCategoria IS NULL` are shared across categories, so the same fecha can appear in several categories' navigation.

### Commands to run before the proposal (owner: whoever has the shell)
```bash
docker compose exec app php artisan tinker --execute="
\$e = DB::table('partidos')->select('idEdicion')->groupBy('idEdicion')->orderByDesc(DB::raw('COUNT(*)'))->limit(3)->pluck('idEdicion');
foreach (\$e as \$id) {
  \$r = DB::table('partidos')->where('idEdicion',\$id)
      ->select('idFechas', DB::raw('COUNT(*) as n'))->groupBy('idFechas')->pluck('n');
  echo \"edicion={\$id} fechas={\$r->count()} partidos_total={\$r->sum()} min={\$r->min()} max={\$r->max()} avg=\".round(\$r->avg(),1).PHP_EOL;
}"
```
Also worth checking per (edicion, categoria), since that is the real shared-link shape:
```bash
docker compose exec app php artisan tinker --execute="
DB::table('partidos')->select('idEdicion','idCategoria', DB::raw('COUNT(DISTINCT idFechas) as fechas'), DB::raw('COUNT(*) as partidos'))
 ->groupBy('idEdicion','idCategoria')->orderByDesc('partidos')->limit(10)->get()->each(fn(\$r)=>print_r((array)\$r));"
```
The proposal must not state a DOM-reduction figure until these run. Order of magnitude from the markup alone: each `<article>` is ~60 lines of HTML with 2 `<img>` and duplicated mobile/desktop blocks, so `partidos_total` is a direct multiplier on both DOM nodes and image requests — which is the mobile cost the user cares about.

## 4. Incoming links — the contract not to break

| Source | URL shape |
|---|---|
| `welcome.blade.php:133` and `:233` (hero CTA + "Ver fixture") | `route('fixture', ['idEdicion' => X])` |
| `welcome.blade.php:242` (category strip) | `route('fixture', ['idEdicion' => X, 'idCategoria' => Y])` |
| `layouts/navigation.blade.php:5,33,121` ("Partidos", PR #114) | `route('fixture', ['idEdicion' => X])` |
| `layouts/tabla-posiciones.blade.php` (back-link) | `route('fixture', ['idEdicion' => X, 'idCategoria' => Y])` |
| `Panel/suscriptores-email.blade.php:12` (push/email composer) | `route('fixture', ['idEdicion' => X])` |
| `Notifications/ResultadoCargado.php:81` | `/fixture?idEdicion=X` |
| `Notifications/NuevoPartidoProgramado.php:79` | `/fixture` — **bare, no params: renders the "elegí una categoría" empty state today** |
| `ControladorNotificacionesEmail.php:1352` → `emails/resumen-resultados-fecha` | `/fixture?idEdicion=X` |

**Nobody links with `idFecha`.** Every in-repo link is `idEdicion` (+ sometimes `idCategoria`) — i.e. exactly the shape whose meaning the default change would alter. The out-of-repo population is WhatsApp shares of those same shapes, plus `#partido-N` / `#fecha-N` fragment links if anyone ever copied one from the address bar (the anchors never appear in the URL unless clicked, so `#fecha-N` links are plausible, `#partido-N` less so — but `id="partido-N"` must survive either way).

Two things must keep working regardless of the default decision:
- `id="partido-{{ $p->id }}"` and `id="fecha-{{ $f->id }}"` must still exist on whatever is rendered.
- A link that lands on a date that is no longer in the default view must still reach the match. If the default narrows, a `#fecha-N` fragment link silently lands on a page that does not contain that section. That is a real regression the proposal must handle (e.g. read the fragment client-side and redirect to `?idFecha=N`, or accept it).

## 5. Reusable patterns

1. **Chip strip, mobile-first** — the existing dates bar (`fixture.blade.php:69-88`) *is* the pattern: `-mx-4 overflow-x-auto px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden` wrapping `flex w-max gap-3`. It is used twice more in this same file (the mobile grupos strip) and matches the tabla-posiciones chips. The explorer should be the same gramática: swap `href="#fecha-N"` for `route('fixture', [...merge..., 'idFecha' => N])` and add an active state (`border-[#f7f280]/45 bg-[#f7f280]/10 text-[#f7f280]` — the "Todas" chip already carries exactly those classes as the active look).
2. **`$queryBase` URL-first idiom** — `tabla-vallas.blade.php:107`: `$queryBase = array_filter(request()->except(['page']))` then `route('...', array_merge($queryBase, ['sort'=>...]))`. Directly applicable to every chip/filter link here so `idEdicion`/`idCategoria`/`estado` survive navigation.
3. **`array_filter([...])` appends** in `tablaVallas` (`ControladorHome.php:372-379`) — the convention for dropping empty params.
4. **Edition-preserving link idiom** — `navigation.blade.php:4-9`.
5. **Duplicate card markup** — the normal and interzonal `<article>` blocks are ~90% identical and repeated mobile/desktop. Extracting `<x-partido-card>` would cut the file roughly in half and is the single highest-leverage refactor here. But it is a big diff on its own: worth proposing as its own work unit *before* the behaviour change, so the explorer diff stays readable.

## 6. URL-first status

Already navigable: `idEdicion`, `idCategoria`, `idFecha`, `estado`, `orden` — the full param set the explorer needs **already exists server-side**. Nothing new is required in the query layer for dates, state filter or ordering.

Missing:
- The view never receives `idEdicion`, `idFecha`, `estado`, `orden`, so it cannot render current state or build links. **Add them to the `compact()`, with Blade-level defaults (see §7).**
- Prev/next need `$fechaAnterior` / `$fechaSiguiente` computed server-side from the ordered `$fechasNavegacion`-equivalent, which today is computed **in the view**. That derivation should move to the controller (or a small service) so prev/next and the default are consistent.
- **Team search has no param.** It can be purely client-side over the already-present `data-local`/`data-visitante` — but only within the rendered date. If the default is one date, "buscar un equipo sin recorrer todo el fixture" (an explicit acceptance criterion) **requires a server-side search across dates**, i.e. a new param (`equipo` or `q`) and a query branch that ignores `idFecha`. This is the tension the design has to resolve, and it is the strongest argument that the explorer is not just "narrow the default".

## 7. Tests

- **`tests/Feature/PlanillaPublicaTest.php:471-501`** — `test_fixture_renderiza_sin_explotar_si_falta_public_token` renders `view('layouts.fixture', [...])` with an **exhaustive 10-key array** exactly matching the current `compact()`: `nombreFecha`, `nombreCategoria`, `grupos`, `ediciones`, `EdicionSeleccionada`, `categorias`, `idCategoria`, `CategoriaSeleccionada`, `fechas`, `partidos`. Asserts `Planilla no disponible` present and `planillas-publicas/partido/` absent. **Any new required view variable breaks it.** Mitigation: default every new variable inside the view (`$estado ?? 'todos'`, `$idFecha ?? null`, `$fechaAnterior ?? null`, …). Cheap, keeps the test green, and is the project's existing habit in this file.
- **`tests/Feature/NavegacionPublicaTest.php`** (new, from PR #114) — hits `route('fixture')` bare (line 61), with `idEdicion` (80, 114, 146) and asserts `assertOk()` plus nav contents. **The bare `route('fixture')` case is the one to watch:** any server-side default resolution must tolerate a missing `idEdicion` without exploding.
- No test asserts fixture *content* (dates rendered, cards present, filters). The explorer needs new Pest feature tests: default renders one date, `?idFecha=` renders that date, `estado=finalizados` excludes unplayed, prev/next links carry `idCategoria`, search finds a team.
- Pre-existing: `pint --test app/Http/Controllers/ControladorHome.php` already fails on `main`. Scope to `--dirty`.

## 8. Design options for the default (decision NOT taken here)

| Option | Behaviour of an existing `?idEdicion=X&idCategoria=Y` link | Pros | Cons |
|---|---|---|---|
| **A. Narrow silently** — no `idFecha` ⇒ relevant date | Shows one date instead of all | Meets every acceptance criterion literally; biggest DOM win; kills the scroll hack | Changes what every already-shared WhatsApp link displays; `#fecha-N` fragments break |
| **B. Keep "all" as default**, add explicit date nav + a "Ver sólo la fecha relevante" affordance | Unchanged | Zero breakage | Fails "carga inicial con UNA fecha o conjunto acotado"; no DOM win for the default path; the issue is essentially not solved |
| **C. Narrow by default + `?idFecha=todas`** as the escape hatch, and keep old links working by redirecting `#fecha-N` client-side to `?idFecha=N` | Narrows, but "all" stays reachable in one tap and old fragments self-heal | Meets criteria, escape hatch preserved, fragment regression handled | Slightly more machinery; `todas` is a magic value in a numeric param (alternatively a separate `vista=todas`) |
| **D. Narrow, but "acotado" = relevant date ± 1** | Shows ~3 dates | Softer change; prev/next context visible | Weaker DOM win; ambiguous active state |

My read, for the requirements phase to accept or reject: **C** is the only one that satisfies both the acceptance criteria and the shared-link risk the user flagged. But the choice is genuinely product, and it depends on the §3 numbers — if a typical edition/category has 3-4 dates and 20 matches, the whole premise weakens and B becomes defensible; if it has 12 dates and 150 matches, A/C is obviously right. **Run the queries before deciding.**

Mobile-first note: whatever the default, the chip strip is already horizontally scrollable and the cards already have a dedicated mobile layout. The mobile cost today is not layout, it is **payload** — two `<img>` per card × every match of the edition, plus an N+1 on `$p->fecha`. Narrowing the default is primarily an image/DOM/latency win on phones, which is the user's stated priority.

## 9. Open questions for the proposal

1. **The default** (§8) — A, B, C or D? Blocked on the §3 numbers.
2. **Ordering of dates.** `fechas` has no date column. Order by `id` (current, descending) or by `MIN(partidos.horario_datetime)`? Prev/next semantics depend on this, and the chip strip currently shows newest-first, which reads backwards for an "agenda".
3. **"Relevant date" definition.** Earliest unplayed future match (mirrors the JS hack) — but what when the whole edition is finished? Fall back to the last played date, or to "all"?
4. **Team search scope.** Client-side within the rendered date (cheap, but fails the "without scrolling the whole fixture" criterion) or server-side across all dates with a new param (meets the criterion, new query branch, new empty state)? I lean server-side; the acceptance criterion is explicit.
5. **Category filter.** The issue says "mantener filtros por categoría" but **there is none in the view** — is building one in scope for #98, or does it belong to the nav work? `$categorias` is already passed and unused, so it is cheap.
6. **`#fecha-N` fragment links.** Accept the breakage, or add the client-side fragment→`?idFecha=` redirect?
7. **`<x-partido-card>` extraction.** Separate preparatory work unit, or inline in the explorer PR? The duplicated normal/interzonal markup makes the explorer diff much larger if not extracted first.
8. **Scroll hack removal.** Once the server picks the date, delete `scrollIntoView` + the double `scrollTo(0,0)` + the `pageshow` handler entirely? The sticky-bar `IntersectionObserver` is independent and should stay.
9. **Bare `/fixture` (no params)** from `NuevoPartidoProgramado` renders an empty state today. Should the explorer default `idEdicion` to the latest edition, like `instanciasFinales` does? Out of scope for #98 strictly, but it is a one-line quality win.

## Verification performed

Read-only. No commands executed (no shell available in this phase). Files read: `ControladorHome.php` (fixture method), `layouts/fixture.blade.php` (full), `layouts/navigation.blade.php`, `PlanillaPublicaTest.php` (fixture test), `create_fechas_table.php`, `create_partidos_table.php`, plus repo-wide greps for `route('fixture')` and `layouts.fixture`. The only file written is this note.
