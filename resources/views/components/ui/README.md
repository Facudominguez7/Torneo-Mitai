# `x-ui.*` components

Shared, tokenized Blade components for issue #110 (design system). This
directory is the "minimum documented set of reusable components" the issue's
acceptance criteria require. Keep this file short: one section per
component, props/usage only.

All components in this family follow the same contract, established by
`x-ui.button` (T1.1):

- **Anonymous Blade components**, declared with `@props([...])`. Props the
  component itself needs (`variant`, `size`, `tone`, `href`, `disabled`, ...)
  are declared there, which extracts them out of the attribute bag. Anything
  else the caller passes (`class`, `id`, `data-*`, `aria-*`, `wire:*`, plain
  HTML attributes) is untouched and reaches `$attributes`.
- **Attribute bag merge**: the component always renders
  `{{ $attributes->merge([...]) }}` on its root element. `class` is
  concatenated (caller classes are added, never replaced); every other key
  (e.g. `type`) only fills in when the caller did not already pass it. This
  is the same pattern already used by the Breeze `x-primary-button` /
  `x-secondary-button` / `x-danger-button` components — those are unchanged
  and out of scope for this phase.
- **Brand tokens only**: colors come from the Phase 0 tokens
  (`brand-navy`, `brand-teal`, `brand-gold`, alpha-capable via
  `bg-brand-navy/50` etc.) or Tailwind's default palette (`red-*`,
  `emerald-*`). No new hardcoded hex.
- **Tested by rendering**, never by reading the component source as text
  (see the Phase 0 lesson in `odd/tasks/design-system-tokens.md`). Tests use
  `Illuminate\Support\Facades\Blade::render()` and assert on the produced
  HTML string.

## `x-ui.button`

```blade
<x-ui.button>Save</x-ui.button>
<x-ui.button variant="danger" size="sm">Delete</x-ui.button>
<x-ui.button href="{{ route('equipo.show', $equipo) }}" tone="panel">Detail</x-ui.button>
<x-ui.button tone="portal" variant="success">Filter by category</x-ui.button>
<x-ui.button :disabled="$equipo->locked">Save</x-ui.button>
<x-ui.button class="w-full" data-testid="save-team" wire:click="save">Save</x-ui.button>
```

### Props

| Prop       | Values                                   | Default   | Notes |
|------------|-------------------------------------------|-----------|-------|
| `variant`  | `primary` \| `secondary` \| `danger` \| `success` | `primary` | Semantic action, not a color name. |
| `size`     | `sm` \| `md` \| `lg`                     | `md`      | Padding + font-size scale. |
| `tone`     | `panel` \| `portal`                      | `panel`   | Surface treatment. See "Tone decision" below. |
| `href`     | any string, or omitted                   | `null`    | When present, renders `<a>` instead of `<button>`. |
| `disabled` | boolean                                  | `false`   | See "Disabled `<a>`" below. Use `:disabled="$expr"` for a dynamic value, not the bare string `disabled="false"`. |

An unknown `variant` or `tone` falls back to `primary` / `panel` respectively,
rather than rendering unstyled — a visibly-wrong button is a smaller failure
than an invisible one.

### Element and type

Renders `<button>` by default. `type` is **not** a declared prop — it flows
through the attribute bag like any other HTML attribute, and defaults to
`type="button"` only when the caller doesn't supply one. This is a
deliberate default: a generic, reusable button should not silently submit an
enclosing form. Pass `type="submit"` explicitly for a submit button.

Passing `href` switches the root element to `<a href="...">`. There is no
mode where a `div` or `span` stands in for either — both real elements keep
native keyboard/AT semantics.

### Disabled `<a>` is not a real disabled control

HTML has no native "disabled" state for `<a>`. Removing `href` leaves a
link-shaped element with no destination and inconsistent semantics; keeping
`href` with `aria-disabled`/`tabindex="-1"` still lets some browsers and
assistive technologies activate it — a control that looks disabled but
isn't is a trap, not an accessible affordance.

`x-ui.button` resolves this by priority: **when both `href` and `disabled`
are given, `disabled` wins** and the component renders a real
`<button type="button" disabled>`, dropping the href entirely. A disabled
action is a `<button>`, never a link.

### States

- **Hover**: variant-specific (`hover:bg-brand-navy/90`, `hover:brightness-110`, ...).
- **Focus-visible**: always present regardless of variant/tone/size —
  `focus:outline-none focus-visible:ring-2` plus a variant-matched ring
  color (e.g. `focus-visible:ring-brand-navy/50`).
- **Disabled**: `disabled:opacity-50 disabled:cursor-not-allowed`, plus the
  real `disabled` HTML attribute on `<button>`.

## Tone decision (evidence)

Per the selected Option A (`odd/tasks/design-system-tokens.md`), the portal
keeps its dark glassmorphism look and the Panel keeps its light Flowbite
look. The real markup on both sides is different enough that one neutral
treatment would look off-brand on at least one side:

- **Panel** (`resources/views/Panel/equipo/index.blade.php:10,44,49-50,137-152`,
  `resources/views/Panel/admin.blade.php:799`): flat solid background,
  `rounded-lg`, darker-shade hover, a plain `focus:ring-2` in an
  ad hoc color per view (`blue-500`, `indigo-500`, `gray-200`, ...). The
  legacy `.btn`/`.btn-editar`/`.btn-eliminar`/`.btn-detalle` pattern (32
  uses, out of scope here, migrated in T2.A) is exactly this: flat color,
  no border, no gradient.
- **Portal** (`resources/views/layouts/navigation.blade.php:63-64,111-112`,
  `resources/views/layouts/tabla-goleadores.blade.php:64-65,131-132`,
  `resources/views/layouts/tabla-vallas.blade.php:57-58`): `rounded-full`
  pill, gradient background between two brand hues, a `border-*/40` ring in
  a third accent color, `hover:brightness-110`, and
  `focus-visible:ring-2 focus-visible:ring-*/40`.

A `tone` prop (`panel` default, `portal` opt-in) is the minimal mechanism
that keeps one component and one prop surface while producing markup that
matches what already exists on each side — including a same-hue,
already-precedented mapping for `success` (`tone="portal" variant="success"`
reproduces the exact emerald gradient at
`resources/views/layouts/tabla-vallas.blade.php:57-58`) and `danger`
(`tone="portal" variant="danger"` reproduces the red pill at
`resources/views/layouts/tabla-goleadores.blade.php:131-132`).

**Cost**: the prop surface is `variant × tone × size` (4 × 2 × 3 = 24
rendered combinations) instead of 12. This is judged worth it because the
alternative — one shared treatment — would require either flattening the
portal's gradient identity or adding a border/pill shape to every Panel
button, both of which are explicitly out of scope (no visual redesign,
Option A). The two sides are not merged into two separate components: it
stays one `x-ui.button`, one prop, one contract.

## `x-ui.badge`

Non-interactive status pill, extracted from the repeated `rounded-full ...
text-xs font-semibold` markup hand-typed across the Panel
(`Panel/jugador/consulta.blade.php`, `Panel/admin.blade.php`,
`Panel/suscriptores-email.blade.php`) and the portal's match-status span
(`layouts/instancias-finales.blade.php:185`). Always renders a `<span>` —
never a control, so it carries no focus-visible state.

```blade
<x-ui.badge variant="success">Verificado</x-ui.badge>
<x-ui.badge variant="danger">No computa para ese equipo</x-ui.badge>
<x-ui.badge tone="portal" variant="info">Próximo</x-ui.badge>
<x-ui.badge :variant="$estadoGolesClase">{{ $estadoGolesLabel }}</x-ui.badge>
```

| Prop      | Values                                              | Default   | Notes |
|-----------|------------------------------------------------------|-----------|-------|
| `variant` | `neutral` \| `success` \| `warning` \| `danger` \| `info` | `neutral` | Semantic status, not a color name. |
| `tone`    | `panel` \| `portal`                                  | `panel`   | Surface treatment, same decision as `x-ui.button`. |

An unknown `variant` or `tone` falls back to `neutral` / `panel`, same
reasoning as `x-ui.button`.

## `x-ui.select`

Labeled `<select>`, extracted from the 79 hand-styled `<select>` elements
across `resources/views/Panel/**/_form.blade.php` (e.g.
`Panel/equipogrupo/_form.blade.php`, `Panel/valla/_form.blade.php`), each
retyping the same border/rounded/focus classes. `@tailwindcss/forms` is
active and already resets native `<select>` styling — this component layers
the design-system border/focus treatment on top of that reset rather than
fighting it. The caller still owns the `<option>` list through the default
slot (its own `@foreach`, `old()`/`selected` logic, etc.).

```blade
<x-ui.select label="Categoría" name="idCategoria" id="idCategoria">
    <option value="">Todas las Categorías</option>
    @foreach ($categorias as $categoria)
        <option value="{{ $categoria->id }}">{{ $categoria->nombreCategoria }}</option>
    @endforeach
</x-ui.select>

<x-ui.select tone="portal" label="Equipo" name="idEquipo" required>
    ...
</x-ui.select>
```

| Prop       | Values             | Default | Notes |
|------------|--------------------|---------|-------|
| `label`    | string, or omitted | `null`  | Renders a real `<label for="...">`. Omitted entirely when not given — pass an `aria-label` via the attribute bag instead if a visible label is not wanted. |
| `name`     | string, or omitted | `null`  | Also used to derive `id` when `id` is not given. |
| `id`       | string, or omitted | `name`  | Explicit override; otherwise derived from `name` so the label stays associated. |
| `tone`     | `panel` \| `portal` | `panel` | Surface treatment. |
| `required` | boolean            | `false` | Renders the real `required` attribute plus `aria-required="true"`. |

Known limitation: native `<option>` elements render with the browser's own
color scheme regardless of the select's classes — the dark-glass options in
`Panel/valla/_form.blade.php` work around this today with inline
`style="background-color: ...; color: ...;"` on each `<option>`. This
component does not attempt to solve that; callers on the `portal` tone that
need styled options still set that inline style themselves.

## `x-ui.search`

Labeled search input. Only ~10 occurrences exist and they are stylistically
split: the Panel uses plain gray inputs
(`Panel/equipo/index.blade.php`, `Panel/tabla_goleador/index.blade.php`),
the portal uses pill-shaped dark-glass inputs
(`layouts/fixture.blade.php`, `layouts/tabla-vallas.blade.php`) — exactly
what `tone` is for.

```blade
<x-ui.search label="Buscar equipo" name="nombreEquipo" placeholder="Buscar equipo por nombre" tone="panel" />

<x-ui.search label="Buscar equipo" labelHidden name="equipo" placeholder="Buscar equipo..." tone="portal" />
```

| Prop          | Values              | Default | Notes |
|---------------|----------------------|---------|-------|
| `label`       | string, or omitted   | `null`  | Renders a real `<label for="...">`. |
| `labelHidden` | boolean              | `false` | Keeps the label for accessibility but visually hides it (`sr-only`) — for the icon-only search boxes like `layouts/tabla-vallas.blade.php`. |
| `name`        | string, or omitted   | `null`  | Also used to derive `id`. |
| `id`          | string, or omitted   | `name`  | Explicit override. |
| `tone`        | `panel` \| `portal`  | `panel` | Surface treatment. |
| `placeholder` | string, or omitted   | `null`  | Also used as the `aria-label` fallback (see below). |
| `value`       | string, or omitted   | `null`  | Keeps the input populated after a GET round-trip. |

**Accessible name, in priority order**: a `label` prop (visible, or
visually-hidden via `labelHidden`), otherwise `aria-label` built from
`placeholder`, so the control is never left without an accessible name —
issue #103 is explicit about not adding to that debt.

## `x-ui.page-header`

**HONEST NOTE**: unlike the other `ui.*` components, there is no existing
shared pattern here. The codebase has 50 raw `<h1>` tags with inconsistent
styling (`text-3xl font-bold text-white` on the portal vs. `text-2xl
font-bold text-gray-800` on the Panel vs. unstyled elsewhere), and zero
`Panel/**` views use a header component — only `dashboard.blade.php` uses
Breeze's `<x-slot name="header">`. This component **creates** the
convention rather than extracting one, kept deliberately minimal.

```blade
<x-ui.page-header title="Gestión de Instituciones" />

<x-ui.page-header title="Equipos" subtitle="Listado de equipos inscriptos" tone="panel">
    <x-slot name="actions">
        <x-ui.button variant="primary">Nuevo equipo</x-ui.button>
    </x-slot>
</x-ui.page-header>
```

| Prop       | Values              | Default  | Notes |
|------------|----------------------|----------|-------|
| `title`    | string (required)    | —        | Rendered inside a real `<h1>`. |
| `subtitle` | string, or omitted   | `null`   | Optional secondary line. |
| `tone`     | `panel` \| `portal`  | `panel`  | Surface treatment. |

Deliberately left out: breadcrumbs, back links, tabs, and icon slots — none
of the audited views use them consistently enough to justify a prop yet.
Add them when a real call site needs one, not speculatively.

## `x-ui.empty-state`

**HONEST NOTE**: same as `x-ui.page-header`, no existing shared pattern.
~15 scattered "no results" messages exist, each a bare `<p>` with different
classes (`Panel/estadisticas-jugadores/index.blade.php`,
`Panel/suscriptores-email.blade.php`, `Panel/institucion/index.blade.php`).
This component **creates** the convention, modeled on the closest existing
example (`Panel/suscriptores-email.blade.php:182` — a centered icon, dashed
border, muted message).

```blade
<x-ui.empty-state message="No se encontraron usuarios con esos filtros" />

<x-ui.empty-state
    message="No hay jugadores registrados"
    description="Probá cambiar la edición seleccionada."
    tone="panel"
>
    <x-slot name="actions">
        <x-ui.button size="sm" variant="secondary">Limpiar filtros</x-ui.button>
    </x-slot>
</x-ui.empty-state>
```

| Prop          | Values              | Default | Notes |
|---------------|----------------------|---------|-------|
| `message`     | string (required)    | —       | Primary message. |
| `description` | string, or omitted   | `null`  | Optional secondary line. |
| `tone`        | `panel` \| `portal`  | `panel` | Surface treatment. |

A default magnifying-glass-style icon renders unless the caller overrides
the `icon` slot. Deliberately left out: illustrations, multiple icon
variants — kept to the minimum that the audited call sites actually need.

## `x-ui.filter-chip`

Toggle-style filter pill, extracted from the real `$clasesChip` closure at
`layouts/fixture.blade.php:71-77`, reused at lines 102, 115 and 132 for the
date/category filter bars. The `portal` tone below is that closure verbatim
(in terms of resolved brand tokens).

```blade
<x-ui.filter-chip href="{{ $armarUrlFixture($parametrosChipTodas) }}" :active="$esVistaTodas" tone="portal">
    Todas
</x-ui.filter-chip>

<x-ui.filter-chip tone="panel" :active="$categoriaActiva" wire:click="filtrar({{ $categoria->id }})">
    {{ $categoria->nombre }}
</x-ui.filter-chip>
```

| Prop     | Values              | Default | Notes |
|----------|----------------------|---------|-------|
| `active` | boolean              | `false` | Sets `aria-current="page"` plus the active-state classes. |
| `tone`   | `panel` \| `portal`  | `panel` | Surface treatment. |
| `href`   | string, or omitted   | `null`  | When present, renders `<a>`; otherwise `<button type="button">`. Same real-element-only rule as `x-ui.button`. |

**Evidence gap, disclosed**: the `panel` tone has no direct one-to-one
precedent — the Panel's closest equivalent
(`Panel/instancia_final/index.blade.php:51,87`) is a `<select>`-based
filter group, not a toggle chip, so there was nothing to extract 1:1 there.
The `panel`-tone classes follow the same tone-split convention established
by `x-ui.button` (flat solid Panel look vs. bordered/gradient portal pill)
rather than inventing an unrelated shape.

## `x-ui.data-table`

Shared list/table shell (T3, panel-admin-redesign), extracted from the ~20
hand-typed `Panel/**/index.blade.php` tables (e.g.
`Panel/equipo/index.blade.php`, `Panel/categoria/index.blade.php`), each
retyping the same `bg-gray-200` header / `border-collapse` markup. Owns the
card shell, the filters bar, the header/body table structure, the empty
state and the footer (pagination) area — a module only supplies its own
`<tr>` rows.

```blade
<x-ui.data-table :empty="$equipos->isEmpty()">
    <x-slot name="filters">
        <form method="GET">...</form>
    </x-slot>

    <x-slot name="head">
        <tr><th>Nombre</th><th>Acción</th></tr>
    </x-slot>

    <x-slot name="body">
        @foreach ($equipos as $equipo)
            <tr><td>{{ $equipo->nombre }}</td><td>...</td></tr>
        @endforeach
    </x-slot>

    <x-slot name="footer">
        {{ $equipos->links() }}
    </x-slot>
</x-ui.data-table>
```

| Prop    | Values  | Default | Notes |
|---------|---------|---------|-------|
| `empty` | boolean | `false` | When true, renders the `emptyState` slot (or `x-ui.empty-state` by default) instead of the table. |

**API choice**: a `head` + `body` slot **pair**, not one default slot. A
`<table>` needs a real `<thead>`/`<tbody>` split for accessibility, and the
header-row styling below only applies to the header — a single default slot
would force the component to guess where one ends and the other begins from
raw markup. Two named slots are unambiguous.

**Naming note — a real Blade collision, not a hypothetical one**: the
`emptyState` slot is deliberately **not** named `empty`, even though the
boolean prop is `:empty`. Verified directly against
`Illuminate\View\Compilers\Concerns\CompilesComponents::compileProps()`:
when a prop and a named slot share one name, the `@props` cleanup step
either drops the boolean (the slot's `ComponentSlot` object wins, if
`empty` is declared in `@props`) or unsets the slot variable entirely (if it
is not declared in `@props`, because `@props` scrubs any variable whose
name still matches an attribute key it did not claim). Naming the slot
`emptyState` keeps both usable together.

Styling is centralized rather than left to callers: the `<thead>` carries
`[&>tr>th]:px-5 [&>tr>th]:py-3` and the `<tbody>` carries
`[&>tr>td]:px-5 [&>tr>td]:py-3` (plus `divide-y divide-gray-100`), so a
caller's `head`/`body` slot only supplies `<tr><th>`/`<tr><td>` markup, never
hand-typed padding classes on every cell.

**Responsive**: wraps the table in `overflow-x-auto` — the only requirement
is that a table never breaks the layout below `md`. A stacked mobile-card
layout was left out; it is an explicit bonus, not a requirement, and this
replaces the old per-cell `lg:hidden absolute` mobile-label hack some index
pages used (a visual behavior change — horizontal scroll instead of stacked
labels on narrow screens — not a functional loss; every column and action
stays reachable).

## `x-ui.form-card`

White card wrapper for Panel forms (T3, panel-admin-redesign), extracted
from the ~15 `Panel/**/_form.blade.php` partials, each rendered with no real
container (a bare centered `<div class="max-w-[550px]">`) and `text-white`
labels left over from the old dark shell — invisible now that T1 moved the
Panel to a light shell.

```blade
<form action="{{ route('equipo.store') }}" method="POST" enctype="multipart/form-data">
    <x-ui.form-card>
        <div>
            @include('Panel.equipo._form')
        </div>

        <x-slot name="actions">
            <x-ui.button type="submit" variant="primary">Agregar</x-ui.button>
        </x-slot>
    </x-ui.form-card>
</form>
```

This component owns only the visual shell: the card, section separators,
and the footer action bar. **It never renders a `<form>` element itself** —
the caller's own `<form method="POST" ...>` stays outside it, so
`x-ui.form-card` can also wrap read-only content, and never risks a form
nested inside a form.

"Sections separated by `border-b`": rather than a fixed number of named
section slots, the default slot's wrapper uses `divide-y` so any direct
child element the caller passes (typically one `<div>` per logical section)
gets an automatic top border from its next sibling, with no
slot-per-section API to keep in sync. `[&>*]:p-6` gives every direct child
the same padding without the caller retyping it.

## `x-ui.confirm-delete`

Delete control with a real confirmation step (T3, panel-admin-redesign),
replacing the 12-of-14 raw `<form method="post">@method('DELETE')@csrf`
delete forms across the Panel, which submit immediately with no
confirmation.

```blade
<x-ui.confirm-delete
    action="{{ route('equipo.destroy', $equipo->id) }}"
    entity="el equipo {{ $equipo->nombre }}"
    :fields="['idEdicion' => $EdicionSeleccionada->id]"
/>
```

| Prop      | Values                    | Default      | Notes |
|-----------|---------------------------|--------------|-------|
| `action`  | string (required)         | —            | The delete route. |
| `label`   | string                    | `'Eliminar'` | Button text. |
| `message` | string, or omitted        | `null`       | Explicit confirmation wording; overrides the `entity`-built default. |
| `entity`  | string, or omitted        | `null`       | Used to build the default message ("¿Eliminar {entity}? ..."). |
| `fields`  | array `[name => value]`   | `[]`         | Extra hidden inputs, for controllers that need more than the model id (e.g. `ControladorEquipo::destroy` reads `idEdicion` from the request). |
| `size`    | `sm` \| `md` \| `lg`     | `md`         | Forwarded to the inner `x-ui.button`; use `sm` next to other row actions. |

The `<form>` here is real — real `POST`, real `DELETE` method spoofing, real
CSRF — so it works with JavaScript disabled; only the confirmation *step* is
JS-driven (event delegation on `[data-confirm-delete]`, registered once in
`resources/js/admin/confirm-delete.js`, imported from
`resources/js/admin/admin.js`). It never depends on Alpine: it uses
`window.Swal` when SweetAlert2 is present, falling back to the native
`window.confirm()` otherwise, so a delete is never left unprotected.
