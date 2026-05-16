@php
    $classic = static fn (string $p): string => route('game.classic', ['page' => str_ends_with($p, '.php') ? $p : $p.'.php']);
    $u = auth()->user();

    $chi = (int) ($u->chi ?? 0);
    $maxChi = (int) ($u->max_chi ?? max(1, $chi));
    $chiPct = $maxChi > 0 ? min(100, (int) round(100 * $chi / $maxChi)) : 0;

    $realmId = (int) $u->realm_id;
    $realmDisplay = \App\Support\RealmDisplay::forUser($u);
    $level = (int) $u->level;
    $daoInsight = min(100, $realmId * 11 + min(40, $level % 10 * 4));

    $menus = [
        [
            'id' => 'cultivation',
            'label' => __('Cultivation & spirit'),
            'summary' => __('Dashboard, meditate, breakthrough, Dao'),
            'items' => [
                ['href' => $classic('game.php'), 'icon' => '🏯', 'label' => __('Spirit hall'), 'desc' => __('Meditate, breakthrough, and tend your cultivation core')],
                ['href' => $classic('cultivate_action.php'), 'icon' => '☯️', 'label' => __('Cultivate'), 'desc' => __('Meditation / chi actions')],
                ['href' => $classic('tribulation.php'), 'icon' => '⛈️', 'label' => __('Tribulations'), 'desc' => __('Heavenly tribulation runs')],
                ['href' => $classic('dao_paths.php'), 'icon' => '☯️', 'label' => __('Dao paths'), 'desc' => __('Path selection & bonuses')],
                ['href' => $classic('cultivation_manuals.php'), 'icon' => '📚', 'label' => __('Cultivation manuals'), 'desc' => __('Manuals & recipes')],
                ['href' => $classic('cave.php'), 'icon' => '🗻', 'label' => __('Cultivation cave'), 'desc' => __('Formations & cave bonuses')],
                ['href' => $classic('notifications.php'), 'icon' => '🔔', 'label' => __('Notifications'), 'desc' => __('Messages & alerts')],
            ],
        ],
        [
            'id' => 'character',
            'label' => __('Character & gear'),
            'summary' => __('Sheet, inventory, bloodline, artifacts'),
            'items' => [
                ['href' => route('game.character'), 'icon' => '📋', 'label' => __('Character sheet'), 'desc' => __('Attributes, builds, combat breakdown')],
                ['href' => route('game.codex'), 'icon' => '📚', 'label' => __('Codex'), 'desc' => __('Lore archive — realms, foes, relics, history')],
                ['href' => $classic('inventory.php'), 'icon' => '🎒', 'label' => __('Inventory'), 'desc' => __('Items, gear & cultivation manuals')],
                ['href' => $classic('equipment.php'), 'icon' => '🛡️', 'label' => __('Equipment'), 'desc' => __('Equip gear')],
                ['href' => $classic('bloodline.php'), 'icon' => '🩸', 'label' => __('Bloodline'), 'desc' => __('Awakening & evolution')],
                ['href' => $classic('artifacts.php'), 'icon' => '✦', 'label' => __('Artifacts'), 'desc' => __('Relics & auras')],
                ['href' => $classic('titles.php'), 'icon' => '📜', 'label' => __('Titles'), 'desc' => __('Unlock & equip titles')],
                ['href' => $classic('professions.php'), 'icon' => '⚒️', 'label' => __('Professions'), 'desc' => __('Crafting skills')],
                ['href' => $classic('runes.php'), 'icon' => '✒️', 'label' => __('Runes'), 'desc' => __('Rune crafting')],
            ],
        ],
        [
            'id' => 'combat',
            'label' => __('Combat & exploration'),
            'summary' => __('PvE, PvP, bosses, dungeons, map'),
            'items' => [
                ['href' => $classic('world_map.php'), 'icon' => '🧭', 'label' => __('World map'), 'desc' => __('Explore regions & NPCs')],
                ['href' => $classic('battles.php'), 'icon' => '⚔️', 'label' => __('Battles'), 'desc' => __('PvP challenges')],
                ['href' => route('game.dungeons.index'), 'icon' => '🏯', 'label' => __('Dungeons'), 'desc' => __('Hidden dungeons & cinematic runs')],
                ['href' => $classic('world_boss.php'), 'icon' => '👹', 'label' => __('World boss'), 'desc' => __('Raid boss')],
                ['href' => $classic('pve_replay.php'), 'icon' => '▶️', 'label' => __('PvE replays'), 'desc' => __('Replay logs')],
                ['href' => $classic('battle_replay.php'), 'icon' => '▶️', 'label' => __('Battle replays'), 'desc' => __('PvP replays')],
            ],
        ],
        [
            'id' => 'sect',
            'label' => __('Sect & world'),
            'summary' => __('Guild, territories, diplomacy, seasons'),
            'items' => [
                ['href' => $classic('sect.php'), 'icon' => '🏛️', 'label' => __('Sect'), 'desc' => __('Your sect hub')],
                ['href' => $classic('sect_base.php'), 'icon' => '🏗️', 'label' => __('Sect base'), 'desc' => __('Buildings & upgrades')],
                ['href' => $classic('sect_library.php'), 'icon' => '📖', 'label' => __('Sect library'), 'desc' => __('Shared manuals')],
                ['href' => $classic('sect_missions.php'), 'icon' => '📜', 'label' => __('Sect missions'), 'desc' => __('Missions')],
                ['href' => $classic('sect_leaderboard.php'), 'icon' => '📊', 'label' => __('Sect leaderboard'), 'desc' => __('Internal ranks')],
                ['href' => $classic('alliance.php'), 'icon' => '🤝', 'label' => __('Alliances'), 'desc' => __('Sect alliances')],
                ['href' => $classic('diplomacy.php'), 'icon' => '🕊️', 'label' => __('Diplomacy'), 'desc' => __('Relations')],
                ['href' => $classic('territories.php'), 'icon' => '🗺️', 'label' => __('Territories'), 'desc' => __('Map control & wars')],
                ['href' => $classic('hall_of_legends.php'), 'icon' => '⭐', 'label' => __('Hall of legends'), 'desc' => __('Era history')],
                ['href' => $classic('era_details.php'), 'icon' => '📅', 'label' => __('Era details'), 'desc' => __('Current era')],
                ['href' => $classic('leaderboard.php'), 'icon' => '🏆', 'label' => __('Leaderboard'), 'desc' => __('Global rankings')],
                ['href' => $classic('season_rankings.php'), 'icon' => '🏅', 'label' => __('Season rankings'), 'desc' => __('Season ladder')],
            ],
        ],
        [
            'id' => 'economy',
            'label' => __('Crafting & market'),
            'summary' => __('Trade, alchemy, forge, herbs'),
            'items' => [
                ['href' => $classic('marketplace.php'), 'icon' => '🏪', 'label' => __('Marketplace'), 'desc' => __('Buy & sell')],
                ['href' => $classic('alchemy.php'), 'icon' => '⚗️', 'label' => __('Alchemy'), 'desc' => __('Pills & elixirs')],
                ['href' => $classic('blacksmith.php'), 'icon' => '🔨', 'label' => __('Blacksmith'), 'desc' => __('Weapons & armor')],
                ['href' => $classic('herbalist.php'), 'icon' => '🌿', 'label' => __('Herb plot'), 'desc' => __('Grow herbs')],
            ],
        ],
        [
            'id' => 'meta',
            'label' => __('Activities & records'),
            'summary' => __('Tasks, lore, reports'),
            'items' => [
                ['href' => $classic('activities.php'), 'icon' => '📋', 'label' => __('Activities'), 'desc' => __('Daily & weekly tasks')],
                ['href' => $classic('fate_records.php'), 'icon' => '📖', 'label' => __('Fate records'), 'desc' => __('Event history')],
                ['href' => $classic('dao_petition.php'), 'icon' => '🕯️', 'label' => __('Dao petition'), 'desc' => __('Submit petitions')],
                ['href' => $classic('report_anomaly.php'), 'icon' => '🌌', 'label' => __('Report anomaly'), 'desc' => __('Bug reports')],
            ],
        ],
    ];

    $onboardingActive = $onboardingActive ?? false;

    $pickHubItemsByHref = static function (array $items, array $fragments): array {
        return array_values(array_filter($items, static function (array $item) use ($fragments): bool {
            foreach ($fragments as $f) {
                if (str_contains($item['href'], $f)) {
                    return true;
                }
            }

            return false;
        }));
    };

    $hubMenus = $menus;
    if ($onboardingActive) {
        $hubMenus = [
            [
                'id' => 'cultivation',
                'label' => __('Cultivation & spirit'),
                'summary' => __('Begin in the spirit hall — meditate when you are ready.'),
                'items' => $pickHubItemsByHref($menus[0]['items'], ['game.php', 'cultivate_action.php']),
            ],
            [
                'id' => 'combat',
                'label' => __('Combat & exploration'),
                'summary' => __('First trials and the open map.'),
                'items' => $pickHubItemsByHref($menus[2]['items'], ['world_map.php', 'battles.php', 'game/dungeons']),
            ],
            [
                'id' => 'sect',
                'label' => __('Sect & world'),
                'summary' => __('Your hall and the wider ranks.'),
                'items' => $pickHubItemsByHref($menus[3]['items'], ['sect.php', 'sect_base.php', 'leaderboard.php']),
            ],
        ];
    }

    $onboardingTrailSteps = [
        ['id' => 'sect', 'label' => __('Report to the outer court'), 'hint' => __('Open your sect hall.'), 'href' => $classic('sect.php')],
        ['id' => 'cultivate', 'label' => __('Still the breath'), 'hint' => __('Spirit hall — open your cultivation core.'), 'href' => $classic('game.php')],
        ['id' => 'combat', 'label' => __('Win a trial'), 'hint' => __('Battles, dungeons, or a map encounter.'), 'href' => $classic('battles.php')],
        ['id' => 'explore', 'label' => __('Walk the boundary'), 'hint' => __('Open the world map.'), 'href' => $classic('world_map.php')],
        ['id' => 'social', 'label' => __('See the wider world'), 'hint' => __('Leaderboard or sect ranks.'), 'href' => $classic('leaderboard.php')],
        ['id' => 'omen', 'label' => __('Read the heavens'), 'hint' => __('Scroll to world events — a rumor is enough.'), 'href' => url('/game').'#realm-world-events'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <span class="font-cinzel tracking-wide text-amber-100/95">{{ __('Realm command') }}</span>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-950/25 px-4 py-3 text-sm text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($onboardingActive)
            <div
                class="rounded-2xl border border-amber-500/35 bg-[rgba(20,25,45,0.65)] p-5 shadow-[0_0_32px_-12px_rgba(245,158,11,0.25)] backdrop-blur-md sm:p-6"
                x-data="realmOnboardingTracker({ storageKey: 'realm_path_{{ $u->id }}', steps: @js($onboardingTrailSteps) })"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-cinzel text-lg font-semibold text-amber-100">{{ __('Path of the Outer Disciple') }}</h2>
                        <p class="mt-1 max-w-xl text-sm text-slate-400">
                            {{ __('Optional trail — check off steps as you go. Links open in this window; the hall stays light until you finish orientation.') }}
                        </p>
                        <div class="mt-3 h-1.5 max-w-md overflow-hidden rounded-full bg-black/40 ring-1 ring-amber-500/20">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-amber-600 to-amber-400 transition-all duration-500"
                                :style="'width: ' + progress() + '%'"
                            ></div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('onboarding.complete') }}" class="shrink-0">
                        @csrf
                        <button
                            type="submit"
                            class="w-full rounded-xl border border-indigo-400/40 bg-indigo-600/80 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_0_20px_-8px_rgba(99,102,241,0.5)] transition hover:bg-indigo-500 sm:w-auto"
                        >
                            {{ __('Finish orientation') }}
                        </button>
                    </form>
                </div>
                <ul class="mt-5 grid gap-2 sm:grid-cols-2">
                    <template x-for="step in steps" :key="step.id">
                        <li class="flex items-start gap-3 rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/50 px-3 py-3 text-sm">
                            <button
                                type="button"
                                @click="toggle(step.id)"
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border text-xs font-bold transition"
                                :class="isDone(step.id) ? 'border-emerald-500/50 bg-emerald-500/20 text-emerald-200' : 'border-slate-600 bg-black/30 text-slate-500'"
                                :aria-pressed="isDone(step.id)"
                            >
                                <span x-text="isDone(step.id) ? '✓' : ''"></span>
                            </button>
                            <div class="min-w-0 flex-1">
                                <a
                                    :href="step.href"
                                    class="font-medium text-indigo-100 underline-offset-2 hover:text-amber-100 hover:underline"
                                ><span x-text="step.label"></span></a>
                                <p class="mt-0.5 text-xs text-slate-500" x-text="step.hint"></p>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card glow="gold" padding="p-6 sm:p-7">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 text-xl font-bold text-white shadow-[0_0_20px_-6px_rgba(99,102,241,0.65)]">
                            {{ strtoupper(substr($u->username, 0, 1)) }}
                        </div>
                        <div>
                            <h2 class="font-cinzel text-lg font-semibold text-white sm:text-xl">{{ $u->username }}</h2>
                            <p class="mt-1 text-sm text-slate-400">
                                {{ __('Welcome back — your aura stirs the veil between realms.') }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-lg border border-indigo-500/25 bg-indigo-950/30 px-2.5 py-1 text-indigo-200/90">{{ $realmDisplay }}</span>
                                <span class="rounded-lg border border-amber-500/25 bg-amber-950/20 px-2.5 py-1 text-amber-100/90">{{ __('Level') }} {{ $level }}</span>
                                <span class="rounded-lg border border-sky-500/25 bg-sky-950/20 px-2.5 py-1 text-sky-100/90">{{ __('Rating') }} {{ $u->rating }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="shrink-0 sm:text-right">
                        <x-ui.button variant="secondary" :href="$classic('game.php')" class="w-full sm:w-auto">
                            {{ __('Spirit hall') }}
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card glow="indigo" padding="p-6 sm:p-7">
                <h3 class="font-cinzel text-sm font-semibold uppercase tracking-wider text-indigo-200/80">{{ __('Cultivation progress') }}</h3>
                <p class="mt-1 text-sm text-slate-400">{{ __('Spirit flow and Dao resonance — a glimpse of your path.') }}</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-slate-400">
                            <span>{{ __('Spirit (chi)') }}</span>
                            <span class="tabular-nums">{{ $chi }} / {{ $maxChi }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-black/45 ring-1 ring-indigo-500/20">
                            <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 via-violet-400 to-sky-400 realm-transition" style="width: {{ $chiPct }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-slate-400">
                            <span>{{ __('Dao insight') }}</span>
                            <span class="tabular-nums">{{ $daoInsight }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-black/45 ring-1 ring-amber-500/15">
                            <div class="h-full rounded-full bg-gradient-to-r from-amber-600/90 to-amber-400/80 shadow-[0_0_12px_-4px_rgba(232,184,74,0.45)] realm-transition" style="width: {{ $daoInsight }}%"></div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div>
            <h3 class="mb-3 font-cinzel text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Vitals') }}</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <x-ui.stat-box :label="__('Realm')" :value="$realmDisplay" accent="indigo" />
                <x-ui.stat-box :label="__('Level')" :value="(string) $level" accent="gold" />
                <x-ui.stat-box :label="__('Attack')" :value="(string) $u->attack" accent="red" />
                <x-ui.stat-box :label="__('Defense')" :value="(string) $u->defense" accent="blue" />
                <x-ui.stat-box :label="__('Wins')" :value="(string) $u->wins" accent="green" :sub="__('Arena record')" />
                <x-ui.stat-box :label="__('Losses')" :value="(string) $u->losses" accent="indigo" :sub="__('Trials endured')" />
            </div>
        </div>

        <x-ui.card padding="p-6 sm:p-7">
            <h3 class="font-cinzel text-sm font-semibold uppercase tracking-wider text-indigo-200/80">{{ __('Venture forth') }}</h3>
            <p class="mt-1 text-sm text-slate-400">{{ __('Quick paths into conflict and discovery.') }}</p>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <x-ui.button class="w-full sm:w-auto" :href="$classic('battles.php')">
                    <span aria-hidden="true">⚔️</span> {{ __('Fight') }}
                </x-ui.button>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" :href="$classic('world_map.php')">
                    <span aria-hidden="true">🧭</span> {{ __('Explore') }}
                </x-ui.button>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" href="{{ route('game.dungeons.index') }}">
                    <span aria-hidden="true">🏯</span> {{ __('Dungeon') }}
                </x-ui.button>
            </div>
        </x-ui.card>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card id="realm-world-events" class="lg:col-span-2" glow="blue" padding="p-6 sm:p-7">
                <h3 class="font-cinzel text-sm font-semibold uppercase tracking-wider text-sky-200/85">{{ __('World events') }}</h3>
                <p class="mt-1 text-sm text-slate-400">{{ __('Rumors from the jianghu — check back as fate shifts.') }}</p>
                <ul class="mt-5 space-y-3 text-sm">
                    <li class="flex gap-3 rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/40 px-4 py-3 realm-transition hover:border-sky-500/25">
                        <span class="text-lg" aria-hidden="true">🌑</span>
                        <div>
                            <p class="font-medium text-slate-200">{{ __('Eclipse over the northern peaks') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Dungeon modifiers may intensify through the week.') }}</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/40 px-4 py-3 realm-transition hover:border-amber-500/25">
                        <span class="text-lg" aria-hidden="true">📜</span>
                        <div>
                            <p class="font-medium text-slate-200">{{ __('Sect envoys gather at Jade Terrace') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Diplomacy and trade bonuses rumored for allied sects.') }}</p>
                        </div>
                    </li>
                    <li class="flex gap-3 rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/40 px-4 py-3 realm-transition hover:border-emerald-500/25">
                        <span class="text-lg" aria-hidden="true">✦</span>
                        <div>
                            <p class="font-medium text-slate-200">{{ __('Spirit tide rises in the lower realms') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Cultivation sessions may feel slightly more fruitful.') }}</p>
                        </div>
                    </li>
                </ul>
            </x-ui.card>

            <x-ui.card padding="p-6 sm:p-7">
                <h3 class="font-cinzel text-sm font-semibold uppercase tracking-wider text-amber-200/80">{{ __('At a glance') }}</h3>
                <p class="mt-3 text-sm leading-relaxed text-slate-400">
                    {{ __('Return to the spirit hall anytime, or open a category below — every path leads back to the dashboard.') }}
                </p>
                <div class="mt-4">
                    <x-ui.button variant="secondary" class="w-full" :href="$classic('game.php')">
                        {{ __('Open spirit hall') }}
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-3">
            @foreach ($hubMenus as $menu)
                <details
                    class="group realm-transition rounded-2xl border border-indigo-500/20 bg-[rgba(20,25,45,0.45)] backdrop-blur-md open:border-indigo-400/35 open:shadow-[0_0_28px_-12px_rgba(99,102,241,0.25)]"
                    @if (! $onboardingActive && $loop->first) open @endif
                >
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-4 py-3.5 text-left realm-transition hover:bg-white/[0.04] [&::-webkit-details-marker]:hidden">
                        <div>
                            <span class="font-semibold text-slate-100">{{ $menu['label'] }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $menu['summary'] }}</span>
                        </div>
                        <span class="text-slate-500 realm-transition group-open:rotate-90" aria-hidden="true">▶</span>
                    </summary>
                    <div class="grid grid-cols-1 gap-3 border-t border-indigo-500/10 px-3 pb-4 pt-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($menu['items'] as $item)
                            <a
                                href="{{ $item['href'] }}"
                                class="block rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/35 p-4 realm-transition hover:border-amber-400/30 hover:bg-[rgba(20,25,45,0.65)] hover:shadow-[0_0_20px_-10px_rgba(232,184,74,0.2)]"
                            >
                                <span class="text-2xl">{{ $item['icon'] }}</span>
                                <span class="mt-1 block font-medium text-slate-100">{{ $item['label'] }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ $item['desc'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        @if ($u->is_admin)
            <p class="text-center text-sm text-slate-500">
                <a
                    href="{{ url('/game/admin/heavenly_observatory.php') }}"
                    class="text-indigo-300 underline-offset-4 realm-transition hover:text-amber-200 hover:underline"
                >
                    {{ __('Heavenly Dao admin') }}
                </a>
            </p>
        @endif
    </div>
</x-app-layout>
