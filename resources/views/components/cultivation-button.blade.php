@props([
    'variant' => 'primary',
    'type' => 'submit',
])

@php
    $base = 'inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold tracking-wide transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:opacity-45 disabled:pointer-events-none';
    $styles = match ($variant) {
        'secondary' => 'border border-white/20 bg-white/5 text-violet-100 hover:bg-white/10 hover:border-amber-400/30 focus-visible:ring-amber-400/50',
        default => 'border border-amber-500/40 bg-gradient-to-r from-amber-600/90 via-amber-500/85 to-amber-600/90 text-slate-950 shadow-[0_0_24px_-4px_rgba(251,191,36,0.55)] hover:shadow-[0_0_32px_-2px_rgba(251,191,36,0.65)] hover:from-amber-500 hover:via-amber-400 hover:to-amber-500 focus-visible:ring-amber-400',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $base.' '.$styles]) }}>
    {{ $slot }}
</button>
