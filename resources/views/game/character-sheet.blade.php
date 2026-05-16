<x-app-layout>
    <x-slot name="header">
        <span class="font-cinzel text-lg text-indigo-50">{{ __('Cultivation Path') }}</span>
    </x-slot>

    @if (!empty($migrationRequired))
        <div class="rounded-xl border border-amber-500/40 bg-amber-950/30 p-6 text-amber-100">
            <p class="font-semibold">{{ __('Character attributes are not installed yet.') }}</p>
            <p class="mt-2 text-sm text-amber-200/80">{{ __('Run database migrations: php artisan migrate') }}</p>
        </div>
    @else
    @php
        $build = $build ?? [];
        $primaries = $build['primaries'] ?? [];
        $derived = $build['derived'] ?? [];
        $points = (int) ($build['attribute_points'] ?? 0);
        $statConfig = $build['config']['stats'] ?? config('attributes.stats', []);
        $specs = $build['config']['specializations'] ?? [];
        $bodies = $build['config']['body_types'] ?? [];
        $synergies = $build['synergies'] ?? [];
        $final = $final ?? [];
        $maxBar = max(1, max($primaries ?: [1]));
        $barColors = [
            'rose' => 'from-rose-600/80 to-rose-400/90',
            'emerald' => 'from-emerald-600/80 to-emerald-400/90',
            'amber' => 'from-amber-600/80 to-amber-400/90',
            'violet' => 'from-violet-600/80 to-violet-400/90',
            'cyan' => 'from-cyan-600/80 to-cyan-400/90',
            'orange' => 'from-orange-600/80 to-orange-400/90',
            'indigo' => 'from-indigo-600/80 to-indigo-400/90',
        ];
        $textColors = [
            'rose' => 'text-rose-300',
            'emerald' => 'text-emerald-300',
            'amber' => 'text-amber-300',
            'violet' => 'text-violet-300',
            'cyan' => 'text-cyan-300',
            'orange' => 'text-orange-300',
            'indigo' => 'text-indigo-300',
        ];
    @endphp

    <div class="character-build" x-data="characterBuild(@js([
        'allocateUrl' => route('game.character.allocate'),
        'specUrl' => route('game.character.specialization'),
        'bodyUrl' => route('game.character.body-type'),
        'respecUrl' => route('game.character.respec'),
        'points' => $points,
        'primaries' => $primaries,
        'specialization' => $build['stat_specialization'] ?? null,
        'bodyType' => $build['body_type'] ?? null,
    ]))">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-cinzel text-2xl font-semibold text-amber-100/95">{{ __('Inner Foundation') }}</h2>
                <p class="mt-1 max-w-xl text-sm text-slate-400">{{ __('Shape your cultivation build with primary attributes, path specialization, and body affinity.') }}</p>
            </div>
            <div class="rounded-xl border border-violet-500/30 bg-violet-950/30 px-4 py-2 text-center">
                <p class="text-[10px] uppercase tracking-widest text-violet-300/70">{{ __('Unspent points') }}</p>
                <p class="font-cinzel text-2xl font-bold text-violet-200" x-text="points">{{ $points }}</p>
            </div>
        </div>

        <div x-show="toast" x-transition class="mb-4 rounded-lg border px-4 py-2 text-sm"
            :class="toastOk ? 'border-emerald-500/40 bg-emerald-950/40 text-emerald-200' : 'border-rose-500/40 bg-rose-950/40 text-rose-200'"
            x-text="toast" style="display: none;"></div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="xl:col-span-2 realm-glass rounded-2xl border border-indigo-500/20 p-6">
                <h3 class="font-cinzel mb-4 text-lg text-indigo-100">{{ __('Primary Attributes') }}</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($statConfig as $key => $meta)
                        @php
                            $val = (int) ($primaries[$key] ?? 5);
                            $pct = min(100, (int) round(100 * $val / max($maxBar, 30)));
                            $c = $meta['color'] ?? 'indigo';
                            $bar = $barColors[$c] ?? $barColors['indigo'];
                            $txt = $textColors[$c] ?? $textColors['indigo'];
                        @endphp
                        <div class="character-stat-card group rounded-xl border border-slate-700/60 bg-slate-950/40 p-4" data-stat="{{ $key }}">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl" aria-hidden="true">{{ $meta['icon'] ?? '◆' }}</span>
                                    <span class="font-medium text-slate-100">{{ __($meta['label'] ?? $key) }}</span>
                                </div>
                                <span class="character-stat-value font-cinzel text-lg {{ $txt }}">{{ $val }}</span>
                            </div>
                            <div class="character-stat-bar mb-2 h-2 overflow-hidden rounded-full bg-slate-800" title="{{ __($meta['description'] ?? '') }}">
                                <div class="character-stat-bar-fill h-full rounded-full bg-gradient-to-r {{ $bar }} transition-all duration-500" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="mb-3 text-xs text-slate-500 opacity-0 transition group-hover:opacity-100">{{ __($meta['description'] ?? '') }}</p>
                            <button type="button" class="w-full rounded-lg border border-indigo-500/30 bg-indigo-950/40 py-1.5 text-xs font-semibold text-indigo-200 hover:bg-indigo-900/50 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="allocate('{{ $key }}')" :disabled="points < 1 || loading">+1 {{ __('Allocate') }}</button>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="realm-glass rounded-2xl border border-amber-500/20 p-6">
                <h3 class="font-cinzel mb-4 text-lg text-amber-100">{{ __('Derived Power') }}</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-2 border-b border-slate-800/80 pb-2"><dt class="text-slate-400">{{ __('Attack') }}</dt><dd class="font-medium tabular-nums text-slate-100">{{ number_format((int) ($final['attack'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-2 border-b border-slate-800/80 pb-2"><dt class="text-slate-400">{{ __('Defense') }}</dt><dd class="font-medium tabular-nums text-slate-100">{{ number_format((int) ($final['defense'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-2 border-b border-slate-800/80 pb-2"><dt class="text-slate-400">{{ __('Qi capacity') }}</dt><dd class="font-medium tabular-nums text-slate-100">{{ number_format((int) ($final['max_chi'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-2 border-b border-slate-800/80 pb-2"><dt class="text-slate-400">{{ __('Crit rate') }}</dt><dd class="font-medium tabular-nums text-slate-100">{{ number_format((float) ($derived['crit_rate'] ?? 0) * 100, 1) }}%</dd></div>
                    <div class="flex justify-between gap-2 border-b border-slate-800/80 pb-2"><dt class="text-slate-400">{{ __('Crit damage') }}</dt><dd class="font-medium tabular-nums text-slate-100">{{ number_format((float) ($derived['crit_damage'] ?? 1.5) * 100, 0) }}%</dd></div>
                    <div class="flex justify-between gap-2 border-b border-slate-800/80 pb-2"><dt class="text-slate-400">{{ __('Dodge') }}</dt><dd class="font-medium tabular-nums text-slate-100">{{ number_format((float) ($derived['dodge'] ?? 0) * 100, 1) }}%</dd></div>
                    <div class="flex justify-between gap-2 border-b border-slate-800/80 pb-2"><dt class="text-slate-400">{{ __('Cultivation speed') }}</dt><dd class="font-medium tabular-nums text-emerald-300">+{{ number_format((float) ($derived['cultivation_speed_pct'] ?? 0) * 100, 1) }}%</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-400">{{ __('Tribulation resist') }}</dt><dd class="font-medium tabular-nums text-cyan-300">+{{ number_format((float) ($derived['tribulation_resistance_pct'] ?? 0) * 100, 1) }}%</dd></div>
                </dl>
                @if (count($synergies) > 0)
                    <div class="mt-5">
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-emerald-400/80">{{ __('Active synergies') }}</p>
                        <ul class="space-y-1 text-xs text-emerald-200/90">
                            @foreach ($synergies as $s)
                                <li>✦ {{ $s['label'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="realm-glass rounded-2xl border border-indigo-500/20 p-6">
                <h3 class="font-cinzel mb-3 text-lg text-indigo-100">{{ __('Path Specialization') }}</h3>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($specs as $key => $spec)
                        <button type="button" @click="setSpec('{{ $key }}')" class="rounded-xl border px-3 py-3 text-left text-sm transition"
                            :class="specialization === '{{ $key }}' ? 'border-amber-500/50 bg-amber-950/30 text-amber-100' : 'border-slate-700/60 bg-slate-950/30 text-slate-300 hover:border-indigo-500/40'">
                            <span class="font-semibold">{{ $spec['label'] ?? $key }}</span>
                            <p class="mt-1 text-xs opacity-80">{{ $spec['description'] ?? '' }}</p>
                        </button>
                    @endforeach
                </div>
            </section>
            <section class="realm-glass rounded-2xl border border-rose-500/20 p-6">
                <h3 class="font-cinzel mb-3 text-lg text-rose-100">{{ __('Cultivation Body') }}</h3>
                <div class="space-y-2">
                    @foreach ($bodies as $key => $body)
                        <button type="button" @click="setBody('{{ $key }}')" class="w-full rounded-xl border px-4 py-3 text-left text-sm transition"
                            :class="bodyType === '{{ $key }}' ? 'border-rose-500/50 bg-rose-950/30 text-rose-100' : 'border-slate-700/60 bg-slate-950/30 text-slate-300 hover:border-rose-500/30'">
                            <span class="font-semibold">{{ $body['label'] ?? $key }}</span>
                            <p class="mt-1 text-xs opacity-80">{{ $body['description'] ?? '' }}</p>
                        </button>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="mt-6 realm-glass rounded-2xl border border-slate-600/40 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="font-cinzel text-lg text-slate-200">{{ __('Respec Meridian') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Reset primaries to base and refund spent points.') }}</p>
                </div>
                <button type="button" class="rounded-xl border border-slate-600 bg-slate-900/60 px-5 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800 disabled:opacity-40" @click="respec()" :disabled="loading">{{ __('Respec') }}</button>
            </div>
        </section>

        @if (!empty($breakdown['steps']))
            <section class="mt-6 realm-glass rounded-2xl border border-indigo-500/15 p-6">
                <h3 class="font-cinzel mb-3 text-lg text-indigo-100">{{ __('Combat pipeline') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr class="text-slate-500"><th class="pb-2 pr-4">{{ __('Stage') }}</th><th class="pb-2 pr-4">{{ __('ATK') }}</th><th class="pb-2 pr-4">{{ __('DEF') }}</th><th class="pb-2">{{ __('Max Qi') }}</th></tr></thead>
                        <tbody class="text-slate-300">
                            @foreach ($breakdown['steps'] as $step)
                                <tr class="border-t border-slate-800/60">
                                    <td class="py-2 pr-4">{{ $step['label'] ?? '' }}</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ number_format((int) ($step['stats']['attack'] ?? 0)) }}</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ number_format((int) ($step['stats']['defense'] ?? 0)) }}</td>
                                    <td class="py-2 tabular-nums">{{ number_format((int) ($step['stats']['max_chi'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>

    @endif
