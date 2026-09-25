@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-700'])

@php
switch ($align) {
    case 'left':
        $alignmentClasses = 'ltr:origin-top-left rtl:origin-top-right start-0';
        break;
    case 'top':
        $alignmentClasses = 'origin-top';
        break;
    case 'right':
    default:
        $alignmentClasses = 'ltr:origin-top-right rtl:origin-top-left';
        break;
}

// Permitir varios tamaños; fallback a w-48
switch ((string) $width) {
    case '40': $width = 'w-40'; break;
    case '56': $width = 'w-56'; break;
    case '64': $width = 'w-64'; break;
    case '72': $width = 'w-72'; break;
    case '80': $width = 'w-80'; break;
    case '48':
    default:
        $width = 'w-48';
        break;
}
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-1 {{ $width }} rounded-2xl shadow-xl {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
            <div class="origin-top-right absolute right-0 {{ $width }} overflow-hidden rounded-2xl border border-brand-gold/50 bg-gradient-to-br from-brand-navy/95 via-brand-teal/95 to-brand-navy/90 py-2 text-white shadow-[0_18px_45px_-20px_rgba(var(--brand-navy)_/_0.65)] ring-1 ring-brand-gold/30 focus:outline-none transition ease-out duration-100">
            {{ $content }}
        </div>
    </div>
</div>