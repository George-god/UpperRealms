<x-app-layout :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <h1 class="font-cinzel text-3xl font-semibold tracking-tight text-transparent sm:text-4xl bg-gradient-to-r from-violet-300 via-fuchsia-400 to-amber-400 bg-clip-text drop-shadow-[0_0_24px_rgba(139,92,246,0.35)]">
                    {{ $dungeon['name'] ?? __('Dungeon') }}
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    data-realm-audio-toggle
                    class="realm-audio-toggle rounded-lg border border-violet-500/30 bg-slate-900/80 px-3 py-2 text-lg"
                    aria-pressed="true"
                    title="{{ __('Sound on') }}"
                >
                    <span data-realm-audio-icon aria-hidden="true">🔊</span>
                </button>
                <a
                    href="{{ route('game.dungeons.index') }}"
                    class="rounded-lg border border-violet-500/30 bg-slate-900/90 px-4 py-2 text-sm text-violet-200 transition hover:bg-slate-800"
                >
                    {{ __('Dungeons') }}
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-500/45 bg-emerald-950/35 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-500/50 bg-red-950/40 px-4 py-3 text-sm text-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-6 rounded-xl border border-violet-500/25 bg-[rgba(20,25,45,0.72)] p-6 shadow-[0_0_40px_-12px_rgba(99,102,241,0.25)] backdrop-blur-xl">
            <p class="mb-2 text-slate-400">
                {{ __('Region') }}: <span class="font-medium text-white">{{ $dungeon['region_name'] ?? '' }}</span>
            </p>
            <p class="mb-2 text-slate-400">
                {{ __('Difficulty') }}: <span class="font-medium text-white">{{ (int) ($dungeon['difficulty'] ?? 1) }}</span>
            </p>
            <p class="mb-2 text-slate-400">
                {{ __('Boss') }}: <span class="font-medium text-white">{{ $dungeon['boss_name'] ?? '' }}</span>
            </p>
            <p class="mb-2 text-slate-400">
                {{ __('Requires') }}: <span class="font-medium text-white">{{ $dungeon['min_realm_name'] ?? __('Qi Refining') }}</span>
            </p>
            <p class="text-sm text-slate-500">{{ __('Daily runs remaining:') }} {{ $runsRemaining }} / 3</p>
        </div>

        <div class="mb-6 rounded-xl border border-slate-700/80 bg-slate-950/50 p-6 backdrop-blur-md">
            <h2 class="mb-4 text-xl font-semibold tracking-tight text-violet-200">{{ __('Dungeon path') }}</h2>
            <p class="mb-3 text-xs text-slate-500">
                {{ __('Treasure caches and ward-stones react as you push deeper. Clearing a stage lights the way forward.') }}
            </p>
            <div class="space-y-3">
                @php
                    $p = $activeRun ? (int) $activeRun['progress'] : 0;
                    $nr = $nextRoomIndex ?? 1;
                @endphp
                <div
                    data-dc-room="1"
                    class="dc-room dc-room--corridor flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3 transition-all {{ $p > 0 ? 'border-emerald-500/35 bg-emerald-950/25' : ($nr === 1 && $activeRun ? 'dc-room--enter border-violet-500/40 bg-violet-950/30' : 'border-slate-700 bg-slate-950/50') }}"
                >
                    <div>
                        <div class="font-semibold text-white">{{ __('I — Threshold hall') }}</div>
                        <div class="text-xs text-slate-500">{{ __('Normal guardian · subtle ward') }}</div>
                    </div>
                    <div class="flex gap-2 text-lg opacity-80" aria-hidden="true">
                        <span class="transition-transform hover:scale-110">📜</span>
                        <span class="transition-transform hover:scale-110">✦</span>
                    </div>
                </div>
                <div
                    data-dc-room="2"
                    class="dc-room dc-room--corridor flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3 transition-all {{ $p > 1 ? 'border-emerald-500/35 bg-emerald-950/25' : ($nr === 2 && $activeRun ? 'dc-room--enter border-violet-500/40 bg-violet-950/30' : 'border-slate-700 bg-slate-950/50') }}"
                >
                    <div>
                        <div class="font-semibold text-white">{{ __('II — Inner sanctum') }}</div>
                        <div class="text-xs text-slate-500">{{ __('Elite warden · trapped tiles') }}</div>
                    </div>
                    <div class="flex gap-2 text-lg opacity-80" aria-hidden="true">
                        <span class="text-amber-400/90 transition-transform hover:scale-110">⚠</span>
                        <span class="transition-transform hover:scale-110">💎</span>
                    </div>
                </div>
                <div
                    data-dc-room="3"
                    class="dc-room dc-room--corridor flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3 transition-all {{ $p > 2 ? 'border-emerald-500/35 bg-emerald-950/25' : ($nr === 3 && $activeRun ? 'dc-room--enter border-amber-500/40 bg-amber-950/25' : 'border-slate-700 bg-slate-950/50') }}"
                >
                    <div>
                        <div class="font-semibold text-amber-100/95">{{ __('III — Heart of the dungeon') }}</div>
                        <div class="text-xs text-slate-500">{{ __('Boss arena · environmental qi storm') }}</div>
                    </div>
                    <div class="flex gap-2 text-lg opacity-90" aria-hidden="true">
                        <span class="animate-pulse">👹</span>
                    </div>
                </div>
            </div>
        </div>

        @if ($battleResult && ! empty($battleResult['battle']))
            @php
                $battle = $battleResult['battle'];
                $wonBattle = (($battle['winner'] ?? '') === 'user');
                $outcomeClass = $wonBattle ? 'dc-outcome--win' : 'dc-outcome--lose';
                $outcomeLabel = $wonBattle ? __('Victory') : __('Defeat');
            @endphp
            <div id="dungeon-combat-mount" class="mb-6">
                <script type="application/json" data-dungeon-battle>
                    {!! json_encode($dungeonBattleJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
                </script>
                <div class="dc-root dc-theme--ancient-ruins">
                    <div class="dc-bg-stack" aria-hidden="true">
                        <div class="dc-bg-base"></div>
                        <div class="dc-bg-fog"></div>
                        <div class="dc-bg-particles"></div>
                        <div class="dc-bg-pulse"></div>
                    </div>
                    <div class="dc-distort" aria-hidden="true"></div>
                    <div class="dc-vignette" aria-hidden="true"></div>
                    <div class="dc-flash-overlay" aria-hidden="true"></div>
                    <div class="dc-fx-layer" aria-hidden="true"></div>
                    <div class="dc-float-root" aria-hidden="true"></div>
                    <div class="dc-arena-inner">
                        <header class="dc-header">
                            <div>
                                <div class="dc-stage-pill">{{ $battleResult['stage_name'] ?? __('Battle') }}</div>
                                <p class="dc-outcome {{ $outcomeClass }} mt-1">{{ $outcomeLabel }}</p>
                                <p class="mt-1 max-w-xl text-sm text-slate-500">
                                    @if ($wonBattle)
                                        {{ __('You won this fight. Rewards below are already applied on the server.') }}
                                    @else
                                        {{ __('You were forced back. Your run remains — challenge this stage again when ready.') }}
                                    @endif
                                </p>
                            </div>
                        </header>
                        <div class="dc-combat-grid">
                            <div class="dc-panel dc-panel--player">
                                <div class="dc-panel-head">
                                    <div>
                                        <div class="dc-panel-title">{{ __('Cultivator') }}</div>
                                        <div class="dc-name" data-dc-name-player>—</div>
                                    </div>
                                    <div class="dc-enemy-portrait border-indigo-500/30 bg-indigo-950/80" aria-hidden="true">🜂</div>
                                </div>
                                <div class="dc-bar-label">
                                    <span>{{ __('Spirit (chi)') }}</span><span data-dc-player-pct>—</span>
                                </div>
                                <div class="dc-bar-track"><div class="dc-bar-fill dc-bar-fill--player" style="width:100%"></div></div>
                            </div>
                            <div class="dc-panel dc-panel--enemy dc-rarity-common">
                                <div class="dc-panel-head">
                                    <div>
                                        <div class="dc-panel-title">{{ __('Adversary') }}</div>
                                        <div class="dc-name" data-dc-name-enemy>—</div>
                                    </div>
                                    <div class="dc-enemy-portrait" aria-hidden="true">⚔️</div>
                                </div>
                                <div class="dc-bar-label">
                                    <span>{{ __('Vitality') }}</span><span data-dc-npc-pct>—</span>
                                </div>
                                <div class="dc-bar-track"><div class="dc-bar-fill dc-bar-fill--enemy" style="width:100%"></div></div>
                            </div>
                        </div>
                        <div class="dc-skill-strip" aria-live="polite">
                            <strong>{{ __('Technique rhythm:') }}</strong> {{ __('playback reveals your Dao exchanges.') }}
                        </div>
                        <div class="dc-playback-bar">
                            <button type="button" class="dc-btn" data-dc-play>{{ __('Replay combat') }}</button>
                            <button type="button" class="dc-btn" data-dc-skip>{{ __('Skip to end') }}</button>
                            <div class="dc-progress" aria-hidden="true"><span></span></div>
                        </div>
                        <div class="dc-log" role="log" aria-label="{{ __('Combat log') }}"></div>
                        @if (! empty($battleResult['rewards']))
                            @php
                                $rw = $battleResult['rewards'];
                                $lootLegendary = ! empty($rw['manual']) && in_array(strtolower((string) ($rw['manual']['rarity'] ?? '')), ['legendary', 'mythic', 'ancient'], true);
                            @endphp
                            <div class="dc-loot {{ $lootLegendary ? 'dc-loot--legendary' : '' }}">
                                <div class="mb-2 text-sm font-semibold tracking-wide text-amber-200/90">{{ __('Spoils') }}</div>
                                <div class="dc-loot-card">
                                    <span class="dc-loot-pill">🪙 {{ (int) ($rw['gold'] ?? 0) }} {{ __('gold') }}</span>
                                    <span class="dc-loot-pill dc-loot-pill--spirit">✧ {{ (int) ($rw['spirit_stones'] ?? 0) }} {{ __('spirit stones') }}</span>
                                    @if (! empty($rw['manual']))
                                        <div class="dc-loot-manual">
                                            {{ __('Manual:') }}
                                            <strong>{{ $rw['manual']['name'] ?? '' }}</strong>
                                            <span class="text-violet-300">({{ ucfirst((string) ($rw['manual']['rarity'] ?? '')) }})</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <noscript>
                    <div class="mt-4 rounded-xl border border-slate-600 bg-slate-950/80 p-4 text-sm text-slate-400">
                        <p class="mb-2 font-medium text-slate-300">{{ __('Combat log (enable JavaScript for cinematic playback)') }}</p>
                        @foreach (($battle['battle_log'] ?? []) as $row)
                            <div>
                                {{ ($row['attacker'] ?? '') === 'user' ? __('You') : ($battle['npc_name'] ?? __('Enemy')) }}
                                — {{ (int) ($row['damage'] ?? 0) }} {{ __('damage') }}
                            </div>
                        @endforeach
                    </div>
                </noscript>
            </div>
        @endif

        <div class="rounded-xl border border-slate-700/80 bg-slate-950/50 p-6 shadow-inner backdrop-blur-md">
            @if ($locked)
                <p class="text-amber-300">
                    {{ __('Requires') }} {{ $dungeon['min_realm_name'] ?? __('Qi Refining') }} {{ __('to enter.') }}
                </p>
            @elseif ($activeRun)
                <h2 class="mb-2 text-xl font-semibold text-violet-300">{{ $stagePreview['label'] ?? __('Next stage') }}</h2>
                <p class="mb-4 text-sm text-slate-400">
                    {{ __('Enemy:') }} <span class="text-slate-200">{{ $stagePreview['enemy_name'] ?? '' }}</span>
                </p>
                <form method="POST" action="{{ route('game.dungeon.advance', ['dungeon' => $dungeonId]) }}">
                    @csrf
                    <input type="hidden" name="run_id" value="{{ (int) $activeRun['id'] }}">
                    <button
                        type="submit"
                        class="rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 px-5 py-3 text-sm font-semibold text-white shadow-[0_0_24px_-8px_rgba(139,92,246,0.55)] transition hover:from-violet-500 hover:to-fuchsia-500"
                    >
                        {{ __('Challenge stage') }}
                    </button>
                </form>
            @elseif ($runsRemaining > 0)
                <h2 class="mb-2 text-xl font-semibold text-violet-300">{{ __('Begin a new run') }}</h2>
                <p class="mb-4 text-sm text-slate-400">{{ __('Three stages await inside this hidden dungeon.') }}</p>
                <form method="POST" action="{{ route('game.dungeon.start', ['dungeon' => $dungeonId]) }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 px-5 py-3 text-sm font-semibold text-white shadow-[0_0_24px_-8px_rgba(139,92,246,0.55)] transition hover:from-violet-500 hover:to-fuchsia-500"
                    >
                        {{ __('Start dungeon run') }}
                    </button>
                </form>
            @else
                <p class="text-amber-300">{{ __('You have used all dungeon runs for today.') }}</p>
            @endif
        </div>
    </div>

    @push('scripts')
        @unless (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            <script src="{{ asset('js/dungeon-combat.js') }}"></script>
        @endunless
        <script>
            (function () {
                function boot() {
                    if (window.DungeonCombat && document.getElementById('dungeon-combat-mount')) {
                        DungeonCombat.initFromMount('#dungeon-combat-mount');
                    }
                    @if (! empty($pulseRoomIndex))
                    var pulse = {{ (int) $pulseRoomIndex }};
                    var room = document.querySelector('[data-dc-room="' + pulse + '"]');
                    if (room) {
                        room.classList.add('dc-room--pulse');
                        setTimeout(function () {
                            room.classList.remove('dc-room--pulse');
                        }, 1200);
                    }
                    @endif
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            })();
        </script>
    @endpush
</x-app-layout>
