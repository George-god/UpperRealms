@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-lg px-2 py-1 text-sm font-semibold text-amber-100 ring-1 ring-amber-400/45 bg-white/10'
            : 'inline-flex items-center rounded-lg px-2 py-1 text-sm font-medium text-violet-200/80 transition duration-200 hover:bg-white/5 hover:text-amber-100/90';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
