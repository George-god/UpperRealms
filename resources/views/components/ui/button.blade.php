@props([
    'variant' => 'primary',
    'type' => 'submit',
    'href' => null,
])

@php
    $base = 'realm-sound-click realm-hover-lift realm-btn inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold tracking-wide realm-transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B0F1A] disabled:pointer-events-none disabled:opacity-45';
    $styles = match ($variant) {
        'secondary' => 'border border-indigo-400/25 bg-indigo-950/40 text-indigo-100 hover:border-amber-400/35 hover:bg-indigo-900/50 hover:text-amber-100/95 focus-visible:ring-indigo-400/60',
        'danger' => 'border border-red-500/40 bg-red-600/80 text-white shadow-[0_0_20px_-6px_rgba(248,113,113,0.55)] hover:bg-red-500 hover:shadow-[0_0_28px_-4px_rgba(248,113,113,0.5)] focus-visible:ring-red-400',
        default => 'border border-indigo-400/40 bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shadow-[0_0_24px_-6px_rgba(99,102,241,0.55)] hover:from-indigo-500 hover:to-indigo-400 hover:shadow-[0_0_32px_-4px_rgba(129,140,248,0.45)] focus-visible:ring-indigo-400',
    };
    $class = $base.' '.$styles;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</button>
@endif
