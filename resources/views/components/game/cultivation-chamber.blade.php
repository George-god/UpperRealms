{{--
  Interactive cultivation chamber (orb + ring + timing UI). Wire with cultivation.js pattern in legacy game.php.
--}}
@props([
    'chiPercent' => 0,
    'canCultivate' => true,
    'cooldownRemaining' => 0,
])

@php
    $r = 52;
    $c = 2 * M_PI * $r;
    $pct = max(0, min(100, (float) $chiPercent));
    $offset = $c * (1 - $pct / 100);
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-indigo-500/25 bg-[rgba(20,25,45,0.75)] p-6 shadow-[0_0_36px_-10px_rgba(99,102,241,0.35)] backdrop-blur-xl sm:p-8']) }}>
    <h3 class="mb-6 text-center font-serif text-xl font-semibold text-indigo-100">{{ __('Spirit chamber') }}</h3>

    <div id="cultivation-orb-stage" class="relative mx-auto mb-6 flex h-52 w-52 items-center justify-center sm:h-56 sm:w-56">
        <svg class="absolute h-full w-full -rotate-90 text-indigo-500/30" viewBox="0 0 120 120" aria-hidden="true">
            <circle cx="60" cy="60" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="6"></circle>
            <circle
                cx="60"
                cy="60"
                r="{{ $r }}"
                fill="none"
                stroke="url(#chiGrad)"
                stroke-width="6"
                stroke-linecap="round"
                stroke-dasharray="{{ $c }}"
                stroke-dashoffset="{{ $offset }}"
                class="transition-[stroke-dashoffset] duration-700 ease-out"
            />
            <defs>
                <linearGradient id="chiGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#6366f1"></stop>
                    <stop offset="100%" stop-color="#38bdf8"></stop>
                </linearGradient>
            </defs>
        </svg>
        <div
            id="cultivation-orb"
            class="relative flex h-32 w-32 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 via-violet-600 to-cyan-500 shadow-[0_0_32px_-4px_rgba(99,102,241,0.75),inset_0_0_20px_rgba(255,255,255,0.12)] transition-transform duration-200 sm:h-36 sm:w-36"
        >
            <span class="pointer-events-none absolute inset-2 rounded-full bg-gradient-to-t from-white/10 to-transparent"></span>
            <span class="relative text-2xl font-bold text-white/95 drop-shadow-lg sm:text-3xl" id="chi-percent">{{ number_format($pct, 1) }}%</span>
        </div>
    </div>

    <div id="cultivation-timing-wrap" class="mb-6 hidden w-full max-w-md mx-auto">
        <p class="mb-2 text-center text-sm text-indigo-200/90">{{ __('Align the needle in the golden band, then seal.') }}</p>
        <div
            id="cultivation-timing-track"
            class="relative h-6 cursor-pointer overflow-hidden rounded-full border border-indigo-500/35 bg-[#0B0F1A]/80 shadow-inner"
        >
            <div
                class="pointer-events-none absolute inset-y-0 left-[35%] right-[35%] rounded-full bg-gradient-to-b from-amber-400/35 to-amber-600/25 ring-1 ring-amber-400/40"
                aria-hidden="true"
            ></div>
            <div
                class="pointer-events-none absolute inset-y-0 left-[45%] right-[45%] rounded-full bg-amber-300/50 ring-1 ring-amber-200/80"
                aria-hidden="true"
            ></div>
            <div
                id="cultivation-timing-needle"
                class="pointer-events-none absolute top-0 bottom-0 w-1 rounded-full bg-amber-200 shadow-[0_0_14px_3px_rgba(251,191,36,0.9)]"
                style="left: 0%"
            ></div>
        </div>
        <p id="cultivation-timing-status" class="mt-2 text-center text-xs text-slate-400"></p>
    </div>

    <div class="text-center">
        <button
            type="button"
            id="cultivate-btn"
            data-cooldown-remaining="{{ (int) $cooldownRemaining }}"
            @if (! $canCultivate) disabled @endif
            class="inline-flex min-w-[12rem] items-center justify-center rounded-xl border border-indigo-400/40 bg-gradient-to-r from-indigo-600 to-indigo-500 px-8 py-3.5 text-sm font-semibold text-white shadow-[0_0_24px_-6px_rgba(99,102,241,0.65)] transition-all duration-200 hover:from-indigo-500 hover:to-indigo-400 hover:shadow-[0_0_32px_-4px_rgba(129,140,248,0.55)] disabled:cursor-not-allowed disabled:opacity-55"
        >
            @if ($canCultivate)
                {{ __('Begin cultivation') }}
            @else
                {{ __('Cooldown') }}: {{ (int) $cooldownRemaining }}s
            @endif
        </button>
        <div id="cultivate-status" class="mt-3 hidden text-sm"></div>
    </div>
</div>
