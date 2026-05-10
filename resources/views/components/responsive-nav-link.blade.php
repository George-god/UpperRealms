@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2.5 border-l-4 border-amber-400/70 text-start text-base font-semibold text-amber-100 bg-white/5 focus:outline-none transition duration-200'
            : 'block w-full ps-3 pe-4 py-2.5 border-l-4 border-transparent text-start text-base font-medium text-violet-200/85 hover:text-slate-100 hover:bg-white/5 hover:border-violet-500/30 focus:outline-none transition duration-200';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
