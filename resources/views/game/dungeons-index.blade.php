<x-app-layout :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-6xl">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-cinzel text-3xl font-semibold tracking-tight text-transparent sm:text-4xl bg-gradient-to-r from-violet-300 via-fuchsia-400 to-amber-400 bg-clip-text">
                    {{ __('Hidden Dungeons') }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">
                    {{ __('Three stages per run: guardian, elite, and boss. Daily limit applies.') }}
                </p>
            </div>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-500/40 bg-red-950/35 px-4 py-3 text-sm text-red-200">
                {{ session('error') }}
            </div>
        @endif
        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-6 rounded-xl border border-violet-500/25 bg-[rgba(20,25,45,0.72)] p-5 shadow-[0_0_36px_-12px_rgba(99,102,241,0.25)] backdrop-blur-xl">
            <p class="text-slate-300">
                {{ __('Daily dungeon runs remaining:') }}
                <span class="font-semibold text-white">{{ $runsRemaining }} / 3</span>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($dungeons as $d)
                @php
                    $locked = ! empty($d['locked']);
                    $activeRun = $d['active_run'] ?? null;
                    $isHighlight = (int) ($d['id'] ?? 0) === ($highlightId ?? 0);
                @endphp
                <div
                    class="rounded-xl border p-6 backdrop-blur-md transition-shadow {{ $isHighlight ? 'border-violet-400 shadow-[0_0_32px_-8px_rgba(139,92,246,0.45)]' : ($locked ? 'border-slate-700/80 bg-slate-950/40 opacity-75' : 'border-violet-500/25 bg-[rgba(20,25,45,0.72)] shadow-[0_0_28px_-12px_rgba(99,102,241,0.2)]') }}"
                >
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-semibold {{ $locked ? 'text-slate-500' : 'text-white' }}">
                                {{ $d['name'] ?? '' }}
                            </h3>
                            <p class="text-sm text-slate-500">
                                {{ $d['region_name'] ?? '' }} · {{ __('Difficulty') }} {{ (int) ($d['difficulty'] ?? 1) }}
                            </p>
                        </div>
                        @if ($activeRun)
                            <span class="rounded-lg border border-violet-500/40 bg-violet-950/40 px-2 py-1 text-xs font-semibold text-violet-200">
                                {{ __('In progress') }}
                            </span>
                        @endif
                    </div>
                    <p class="mb-2 text-sm text-slate-400">
                        {{ __('Boss:') }} <span class="text-slate-200">{{ $d['boss_name'] ?? '' }}</span>
                    </p>
                    <p class="mb-4 text-sm {{ $locked ? 'text-amber-300' : 'text-slate-500' }}">
                        {{ __('Requires') }} {{ $d['min_realm_name'] ?? __('Qi Refining') }}
                    </p>
                    @if ($activeRun)
                        <p class="mb-4 text-sm text-cyan-300">
                            {{ __('Progress') }}: {{ __('Stage') }} {{ (int) $activeRun['progress'] + 1 }} / 3
                        </p>
                    @endif
                    @if ($locked)
                        <span class="block w-full rounded-lg bg-slate-800 py-2.5 text-center text-sm font-semibold text-slate-500">
                            {{ __('Locked') }}
                        </span>
                    @else
                        <a
                            href="{{ route('game.dungeon.show', ['dungeon' => (int) ($d['id'] ?? 0)]) }}"
                            class="block w-full rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 py-2.5 text-center text-sm font-semibold text-white shadow-[0_0_24px_-8px_rgba(139,92,246,0.55)] transition hover:from-violet-500 hover:to-fuchsia-500"
                        >
                            {{ $activeRun ? __('Continue run') : __('Enter dungeon') }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
