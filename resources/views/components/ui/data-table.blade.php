{{--
    x-ui.data-table — shared list/table shell (T3, panel-admin-redesign).

    Extracted from the ~20 hand-typed Panel/**/index.blade.php tables (e.g.
    Panel/equipo/index.blade.php, Panel/categoria/index.blade.php), each
    retyping the same bg-gray-200 header / border-collapse / mobile-stacked
    markup. This component owns the card shell, the filters bar, the
    header/body table structure, the empty state and the footer (pagination)
    area — a module only supplies its own <tr> rows through the `head` and
    `body` slots.

    API choice: a `head` + `body` slot PAIR, not a single default slot. A
    <table> needs a real <thead>/<tbody> split for accessibility and for the
    header-row styling below to apply only to the header; a single default
    slot would force the component to guess where one ends and the other
    begins from raw markup. Two named slots are unambiguous.

    Naming note — a real Blade collision, not a hypothetical one: the
    boolean `:empty` prop and the custom-empty-state slot are NOT both named
    "empty". Verified directly against
    Illuminate\View\Compilers\Concerns\CompilesComponents::compileProps():
    when a prop and a named slot share one name, the @props cleanup step
    either drops the boolean (the slot's ComponentSlot object wins, if
    "empty" is declared in @props) or unsets the slot variable entirely (if
    it is not declared in @props, because @props scrubs any variable whose
    name still matches an attribute key it did not claim). The slot is
    named `emptyState` instead so both stay usable together.

    Responsive: this only wraps the table in `overflow-x-auto` — a stacked
    mobile-card layout is explicitly a bonus, not required. This replaces
    the old per-cell "lg:hidden absolute" mobile-label hack some index
    pages used; that hack is a visual behavior change (horizontal scroll
    instead of stacked labels on narrow screens), not a functional loss —
    every column and action stays reachable.
--}}
@props([
    'empty' => false,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white overflow-hidden']) }}>
    @isset($filters)
        <div class="border-b border-gray-200 px-5 py-4">
            {{ $filters }}
        </div>
    @endisset

    @if ($empty)
        <div class="px-5 py-4">
            @isset($emptyState)
                {{ $emptyState }}
            @else
                <x-ui.empty-state message="No existen registros" />
            @endisset
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-left text-sm text-gray-700">
                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-600 [&>tr>th]:px-5 [&>tr>th]:py-3">
                    {{ $head ?? '' }}
                </thead>
                <tbody class="divide-y divide-gray-100 [&>tr>td]:px-5 [&>tr>td]:py-3">
                    {{ $body ?? '' }}
                </tbody>
            </table>
        </div>
    @endif

    @if (isset($footer) && $footer->isNotEmpty())
        <div class="border-t border-gray-200 px-5 py-4">
            {{ $footer }}
        </div>
    @endif
</div>
