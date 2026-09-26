{{--
    x-ui.form-card — white card wrapper for Panel forms (T3,
    panel-admin-redesign).

    Extracted from the ~15 Panel/**/_form.blade.php partials, each rendered
    with no real container (a bare centered <div class="max-w-[550px]">) and
    text-white labels left over from the old dark shell — invisible now
    that T1 moved the Panel to a light shell.

    This component owns only the visual shell: the card, section
    separators, and a footer action bar. It never renders a <form> element
    itself, so the caller's own <form method="POST" ...> stays outside it —
    that keeps this component reusable for read-only content too, and never
    risks a form nested inside a form.

    "Sections separated by border-b": rather than a fixed number of named
    section slots, the default slot's wrapper uses `divide-y` so any direct
    child element the caller passes (typically one <div> per logical
    section) gets an automatic top border from its next sibling, with no
    slot-per-section API to keep in sync. `[&>*]:p-6` gives every direct
    child the same padding without the caller retyping it.
--}}
@props([])

<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-3xl overflow-hidden rounded-xl border border-gray-200 bg-white']) }}>
    <div class="divide-y divide-gray-100 [&>*]:p-6">
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
            {{ $actions }}
        </div>
    @endisset
</div>
