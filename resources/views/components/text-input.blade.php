@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/40 shadow-inner shadow-black/10 outline-none transition focus:border-yellow-300 focus:ring focus:ring-yellow-300/30 disabled:cursor-not-allowed disabled:opacity-60']) !!}>
