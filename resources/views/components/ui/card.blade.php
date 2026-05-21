@props([
    'padding' => 'p-6 sm:p-8',
    'glow' => 'indigo',
])

@php
    $glowRing = match ($glow) {
        'gold' => 'shadow-[0_0_32px_-8px_rgba(232,184,74,0.35)]',
        'red' => 'shadow-[0_0_32px_-8px_rgba(248,113,113,0.3)]',
        'green' => 'shadow-[0_0_32px_-8px_rgba(74,222,128,0.3)]',
        'blue' => 'shadow-[0_0_32px_-8px_rgba(56,189,248,0.3)]',
        default => 'shadow-[0_0_36px_-10px_rgba(99,102,241,0.35)]',
    };
@endphp

<div {{ $attributes->merge([
    'class' => 'relative overflow-hidden rounded-2xl border border-indigo-500/20 bg-[rgba(20,25,45,0.7)] backdrop-blur-xl realm-transition hover:border-indigo-400/30 '.$glowRing.' '.$padding,
]) }}>
    <div class="pointer-events-none absolute -right-12 -top-12 h-32 w-32 rounded-full bg-indigo-500/10 blur-2xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-10 -left-10 h-28 w-28 rounded-full bg-amber-500/5 blur-2xl" aria-hidden="true"></div>
    <div class="relative z-10">
        {{ $slot }}
    </div>
</div>
