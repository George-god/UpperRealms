@props([
    'label',
    'value',
    'sub' => null,
    'accent' => 'indigo',
])

@php
    $accentStyles = match ($accent) {
        'gold' => 'border-amber-500/30 shadow-[0_0_20px_-8px_rgba(232,184,74,0.25)] text-amber-100',
        'red' => 'border-red-500/30 shadow-[0_0_20px_-8px_rgba(248,113,113,0.2)] text-red-100',
        'green' => 'border-emerald-500/30 shadow-[0_0_20px_-8px_rgba(74,222,128,0.2)] text-emerald-100',
        'blue' => 'border-sky-500/30 shadow-[0_0_20px_-8px_rgba(56,189,248,0.2)] text-sky-100',
        default => 'border-indigo-500/30 shadow-[0_0_20px_-8px_rgba(99,102,241,0.25)] text-indigo-100',
    };
@endphp

<div {{ $attributes->merge([
    'class' => 'rounded-xl border bg-[rgba(20,25,45,0.55)] p-4 backdrop-blur-md realm-transition hover:border-opacity-50 '.$accentStyles,
]) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ $label }}</p>
            <p class="mt-1 truncate text-xl font-bold tabular-nums text-white sm:text-2xl">{{ $value }}</p>
            @if ($sub)
                <p class="mt-0.5 text-xs text-slate-500">{{ $sub }}</p>
            @endif
        </div>
        @isset($icon)
            <div class="shrink-0 text-2xl opacity-90" aria-hidden="true">{{ $icon }}</div>
        @endisset
    </div>
</div>
