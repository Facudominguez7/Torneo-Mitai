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
