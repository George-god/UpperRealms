{{--
  Reusable combat HUD markup (Tailwind). Mirror in legacy PHP where classic game runs.
  Props: prefix (e.g. explore-), playerName
--}}
@props([
    'prefix' => 'explore-',
    'playerName' => 'Cultivator',
])

@php
    $p = rtrim($prefix, '-') . '-';
@endphp

<div
    id="{{ $p }}battle-panel"
    {{ $attributes->class('mb-6 hidden rounded-2xl border border-indigo-500/25 bg-[rgba(20,25,45,0.82)] p-4 shadow-[0_0_40px_-12px_rgba(99,102,241,0.35)] backdrop-blur-xl sm:p-6') }}
>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-indigo-500/15 pb-3">
        <h2 class="font-serif text-lg font-semibold text-amber-100/95 sm:text-xl">
            {{ __('Encounter') }}:
            <span id="{{ $p }}battle-npc-name" class="text-indigo-200"></span>
        </h2>
        <span class="rounded-lg border border-indigo-500/20 bg-[#0B0F1A]/60 px-2 py-1 text-[10px] font-medium uppercase tracking-wider text-slate-500">{{ __('Playback') }}</span>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
        {{-- Player (left) --}}
        <div
            id="{{ $p }}battle-player-card"
            class="realm-combat-card rounded-2xl border border-indigo-400/25 bg-[#0B0F1A]/55 p-4 shadow-[inset_0_0_24px_-8px_rgba(99,102,241,0.2)] transition-all duration-200"
        >
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-300/70">{{ __('You') }}</p>
                    <p id="{{ $p }}battle-user-name" class="text-lg font-semibold text-white">{{ $playerName }}</p>
                </div>
                <div id="{{ $p }}battle-user-buffs" class="flex flex-wrap justify-end gap-1" aria-label="{{ __('Your effects') }}"></div>
            </div>
            <p class="mb-1 text-[11px] text-slate-500">{{ __('Spirit (Chi)') }}</p>
            <div class="mb-1 h-3 overflow-hidden rounded-full bg-black/50 ring-1 ring-indigo-500/25">
                <div
                    id="{{ $p }}battle-user-bar"
                    class="h-full rounded-full bg-gradient-to-r from-indigo-500 via-sky-400 to-cyan-300 shadow-[0_0_12px_-4px_rgba(56,189,248,0.6)] transition-[width] duration-500 ease-out"
                    style="width: 100%"
                ></div>
            </div>
            <p id="{{ $p }}battle-user-text" class="mb-3 text-right text-[11px] tabular-nums text-slate-400">— / —</p>
            <p class="mb-1 text-[11px] text-slate-500">{{ __('Focus') }}</p>
            <div class="h-2 overflow-hidden rounded-full bg-black/40 ring-1 ring-amber-500/20">
                <div
                    id="{{ $p }}battle-user-stamina-bar"
                    class="h-full rounded-full bg-gradient-to-r from-amber-600/90 to-amber-400/70 transition-[width] duration-300 ease-out"
                    style="width: 100%"
                ></div>
            </div>
        </div>

        {{-- Enemy (right) --}}
        <div
            id="{{ $p }}battle-enemy-card"
            class="realm-combat-card rounded-2xl border border-red-500/25 bg-[#0B0F1A]/55 p-4 shadow-[inset_0_0_24px_-8px_rgba(248,113,113,0.15)] transition-all duration-200"
        >
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-red-300/70">{{ __('Foe') }}</p>
                    <p id="{{ $p }}battle-enemy-title" class="text-lg font-semibold text-red-100/90">
                        <span id="{{ $p }}battle-npc-label"></span>
                    </p>
                </div>
                <div id="{{ $p }}battle-enemy-buffs" class="flex flex-wrap justify-end gap-1" aria-label="{{ __('Enemy effects') }}"></div>
            </div>
            <p class="mb-1 text-[11px] text-slate-500">{{ __('Vitality') }}</p>
            <div class="mb-1 h-3 overflow-hidden rounded-full bg-black/50 ring-1 ring-red-500/25">
                <div
                    id="{{ $p }}battle-npc-bar"
                    class="h-full rounded-full bg-gradient-to-r from-red-600 via-orange-500 to-amber-500 shadow-[0_0_12px_-4px_rgba(248,113,113,0.5)] transition-[width] duration-500 ease-out"
                    style="width: 100%"
                ></div>
            </div>
            <p id="{{ $p }}battle-npc-text" class="mb-3 text-right text-[11px] tabular-nums text-slate-400">— / —</p>
            <p class="mb-1 text-[11px] text-slate-500">{{ __('Malice') }}</p>
            <div class="h-2 overflow-hidden rounded-full bg-black/40 ring-1 ring-red-500/15">
                <div
                    id="{{ $p }}battle-enemy-pressure-bar"
                    class="h-full rounded-full bg-gradient-to-r from-rose-700/80 to-red-500/60 transition-[width] duration-300 ease-out"
                    style="width: 100%"
                ></div>
            </div>
        </div>
    </div>

    <div
        id="{{ $p }}battle-log"
        class="realm-combat-log mt-4 max-h-48 overflow-y-auto rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/65 p-3 text-sm shadow-inner backdrop-blur-sm sm:max-h-56"
    ></div>

    <div id="{{ $p }}battle-result" class="mt-3 hidden rounded-xl border border-indigo-500/20 bg-indigo-950/30 px-4 py-3 text-center text-sm font-semibold"></div>
    <div id="{{ $p }}battle-chi-display" class="mt-2 hidden text-center text-sm text-cyan-300">
        {{ __('Chi') }}: <span id="{{ $p }}battle-chi-value"></span>
    </div>

    <div
        id="{{ $p }}combat-actions"
        class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4"
        role="group"
        aria-label="{{ __('Combat actions') }}"
    >
        <button
            type="button"
            class="realm-combat-action rounded-xl border border-indigo-400/30 bg-indigo-950/40 px-3 py-3 text-xs font-semibold text-indigo-100 shadow-[0_0_16px_-8px_rgba(99,102,241,0.5)] transition-all duration-200 hover:border-indigo-300/50 hover:shadow-[0_0_24px_-6px_rgba(129,140,248,0.45)] disabled:pointer-events-none disabled:opacity-40"
            disabled
        >
            {{ __('Attack') }}
        </button>
        <button
            type="button"
            class="realm-combat-action rounded-xl border border-sky-400/25 bg-sky-950/30 px-3 py-3 text-xs font-semibold text-sky-100 transition-all duration-200 hover:border-sky-300/45 hover:shadow-[0_0_20px_-8px_rgba(56,189,248,0.35)] disabled:pointer-events-none disabled:opacity-40"
            disabled
        >
            {{ __('Technique') }}
        </button>
        <button
            type="button"
            class="realm-combat-action rounded-xl border border-amber-400/25 bg-amber-950/25 px-3 py-3 text-xs font-semibold text-amber-100 transition-all duration-200 hover:border-amber-300/45 hover:shadow-[0_0_20px_-8px_rgba(232,184,74,0.3)] disabled:pointer-events-none disabled:opacity-40"
            disabled
        >
            {{ __('Use item') }}
        </button>
        <button
            type="button"
            class="realm-combat-action rounded-xl border border-emerald-400/25 bg-emerald-950/25 px-3 py-3 text-xs font-semibold text-emerald-100 transition-all duration-200 hover:border-emerald-300/45 hover:shadow-[0_0_20px_-8px_rgba(74,222,128,0.3)] disabled:pointer-events-none disabled:opacity-40"
            disabled
        >
            {{ __('Defend') }}
        </button>
    </div>
    <p class="mt-2 text-center text-[10px] text-slate-500">{{ __('Encounters resolve automatically — actions light up for future turns.') }}</p>
</div>
