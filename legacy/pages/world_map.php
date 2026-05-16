<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/SessionHelper.php';
require_once dirname(__DIR__) . '/services/ExplorationService.php';
require_once dirname(__DIR__) . '/includes/realm_display.php';

use Game\Helper\SessionHelper;
use Game\Service\ExplorationService;

legacy_session_start();
$userId = SessionHelper::requireLoggedIn();
$explorerName = htmlspecialchars((string)($_SESSION['username'] ?? 'Cultivator'), ENT_QUOTES, 'UTF-8');

$service = new ExplorationService();
$mapData = $service->getRegionsForUser($userId);
$regions = $mapData['regions'] ?? [];
$currentLocation = $mapData['current_location'] ?? null;
$cooldownRemaining = (int)($mapData['cooldown_remaining'] ?? 0);
$cooldownKind = (string)($mapData['explore_cooldown_kind'] ?? 'none');
$exploreBurstUsed = (int)($mapData['explore_burst_used'] ?? 0);
$exploreBurstMax = (int)($mapData['explore_burst_max'] ?? 10);
$exploreInLongRest = !empty($mapData['explore_in_long_rest']);
$cooldownLabel = match ($cooldownKind) {
    'long_rest' => 'Long rest',
    'interval' => 'Breath interval',
    default => 'Next explore',
};
$formatExploreCooldown = static function (int $seconds): string {
    $s = max(0, $seconds);
    if ($s >= 3600) {
        return (string) (int) ceil($s / 3600).'h';
    }
    if ($s >= 120) {
        return (string) (int) ceil($s / 60).'m';
    }

    return $s.'s';
};
$cooldownDisplay = $cooldownRemaining > 0 ? $formatExploreCooldown($cooldownRemaining) : 'Ready';

$regionCount = count($regions);
$mapNodes = [];
foreach (array_values($regions) as $i => $region) {
    $n = max(1, $regionCount);
    $angle = (2 * M_PI * $i / $n) - (M_PI / 2);
    $mapNodes[] = [
        'region' => $region,
        'x' => 50 + 38 * cos($angle),
        'y' => 50 + 38 * sin($angle),
    ];
}

$regionsPayload = [];
foreach ($regions as $region) {
    $regionsPayload[(string)(int)($region['id'] ?? 0)] = [
        'id' => (int)($region['id'] ?? 0),
        'name' => (string)($region['name'] ?? ''),
        'locked' => !empty($region['locked']),
        'difficulty' => (int)($region['difficulty'] ?? 1),
        'description' => (string)($region['description'] ?? ''),
        'resource_type' => (string)($region['resource_type'] ?? ''),
        'exploration_encounters' => (string)($region['exploration_encounters'] ?? ''),
        'hidden_dungeon_chance' => (float)($region['hidden_dungeon_chance'] ?? 0),
        'min_realm_name' => realm_display_label($region['min_realm_name'] ?? null, (int)($region['min_realm_id'] ?? 0) ?: null),
        'is_current' => !empty($region['is_current']),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>World Map - Cultivation Journey</title>
    <link rel="stylesheet" href="/css/realm-animations.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes realm-combat-hit-player {
            0%, 100% { box-shadow: inset 0 0 24px -8px rgba(99,102,241,0.2); filter: brightness(1); }
            50% { box-shadow: inset 0 0 32px -4px rgba(248,113,113,0.45); filter: brightness(1.15); }
        }
        @keyframes realm-combat-hit-enemy {
            0%, 100% { box-shadow: inset 0 0 24px -8px rgba(248,113,113,0.15); filter: brightness(1); }
            50% { box-shadow: inset 0 0 36px -4px rgba(56,189,248,0.4); filter: brightness(1.12); }
        }
        @keyframes realm-damage-float {
            0% { opacity: 1; transform: translateY(0) scale(1); }
            100% { opacity: 0; transform: translateY(-18px) scale(1.15); }
        }
        .realm-combat-hit-player { animation: realm-combat-hit-player 0.45s ease-out; }
        .realm-combat-hit-enemy { animation: realm-combat-hit-enemy 0.45s ease-out; }
        .realm-combat-log-line { border-radius: 0.5rem; padding: 0.35rem 0.5rem; transition: background 0.2s ease; }
        .realm-combat-log-line:hover { background: rgba(99,102,241,0.08); }
        .realm-damage-popup {
            position: absolute;
            pointer-events: none;
            font-weight: 700;
            animation: realm-damage-float 0.85s ease-out forwards;
        }

        @keyframes realm-node-boss-pulse {
            0%, 100% { filter: drop-shadow(0 0 6px rgba(248, 113, 113, 0.55)); transform: scale(1); }
            50% { filter: drop-shadow(0 0 14px rgba(251, 191, 36, 0.85)); transform: scale(1.08); }
        }
        @keyframes realm-node-glow {
            0%, 100% { opacity: 0.35; }
            50% { opacity: 0.65; }
        }
        .realm-map-node--boss .realm-node-core { animation: realm-node-boss-pulse 2.2s ease-in-out infinite; }
        .realm-map-node-group { cursor: pointer; transition: transform 0.2s ease; }
        .realm-map-node-group:hover { transform: scale(1.06); }
        .realm-map-node-group:focus { outline: none; }
        .realm-map-node-group.realm-map-node--selected .realm-node-ring {
            stroke: rgba(251, 191, 36, 0.95);
            stroke-width: 0.9;
        }
        .realm-path-line {
            stroke: rgba(99, 102, 241, 0.35);
            stroke-width: 0.35;
            fill: none;
            stroke-linecap: round;
            filter: drop-shadow(0 0 2px rgba(99,102,241,0.25));
        }
        .realm-path-line--danger {
            stroke: rgba(248, 113, 113, 0.28);
        }
    </style>
</head>
<body class="min-h-screen bg-[#0B0F1A] text-slate-200">
    <div class="mx-auto max-w-7xl px-4 py-8">
        <?php require_once dirname(__DIR__) . '/includes/realm_portal_nav.php'; ?>
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <?php $site_brand_compact = true; require_once dirname(__DIR__) . '/includes/site_brand.php'; ?>
                <div>
                    <h1 class="bg-gradient-to-r from-emerald-300 via-cyan-300 to-indigo-300 bg-clip-text text-3xl font-bold text-transparent sm:text-4xl">World Map</h1>
                    <p class="text-sm text-slate-500">Node realms · trace your path</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" data-realm-audio-toggle class="realm-audio-toggle realm-hover-lift rounded-xl border border-indigo-500/30 bg-[rgba(20,25,45,0.7)] px-3 py-2 text-lg backdrop-blur-md" aria-pressed="true" title="Sound on"><span data-realm-audio-icon aria-hidden="true">🔊</span></button>
            </div>
        </div>

        <div class="mb-6 rounded-2xl border border-indigo-500/20 bg-[rgba(20,25,45,0.72)] p-5 shadow-[0_0_36px_-12px_rgba(99,102,241,0.25)] backdrop-blur-xl sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-indigo-200">Exploration status</h2>
                    <p class="text-sm text-slate-400">
                        Current location:
                        <span class="font-medium text-white"><?php echo htmlspecialchars($currentLocation['name'] ?? 'Uncharted', ENT_QUOTES, 'UTF-8'); ?></span>
                    </p>
                    <p class="mt-2 max-w-xl text-xs text-slate-500">
                        Hostile encounters, dungeon clues, and rare finds scale with region difficulty.
                        <span class="text-amber-300/90">Boss-touched</span> nodes pulse when legends stir.
                    </p>
                    <p class="mt-2 text-sm text-slate-400">
                        <span id="explore-long-rest-line" class="<?php echo $exploreInLongRest ? 'text-amber-200/95' : 'hidden'; ?>">Long rest active. </span>
                        Burst progress:
                        <span id="explore-burst-text" class="font-medium text-cyan-300"><?php echo (int)$exploreBurstUsed; ?> / <?php echo (int)$exploreBurstMax; ?></span>
                        <span id="explore-burst-hint" class="text-slate-500"><?php echo $exploreInLongRest ? ' (mandatory rest after completing a full burst)' : ' (explores before mandatory long rest)'; ?></span>
                    </p>
                </div>
                <div class="text-sm text-slate-400">
                    <span id="explore-cooldown-label"><?php echo htmlspecialchars($cooldownLabel, ENT_QUOTES, 'UTF-8'); ?></span>:
                    <span id="explore-cooldown" class="font-semibold <?php echo $cooldownKind === 'long_rest' ? 'text-amber-200' : 'text-white'; ?>"><?php echo htmlspecialchars($cooldownDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>

        <div id="explore-message" class="mb-4 hidden"></div>

        <div id="explore-battle-panel" class="mb-6 hidden rounded-2xl border border-indigo-500/25 bg-[rgba(20,25,45,0.82)] p-4 shadow-[0_0_40px_-12px_rgba(99,102,241,0.35)] backdrop-blur-xl sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-indigo-500/15 pb-3">
                <h2 class="text-lg font-semibold text-amber-100/95 sm:text-xl">Encounter: <span id="explore-battle-npc-name" class="text-indigo-200"></span></h2>
                <span class="rounded-lg border border-indigo-500/20 bg-[#0B0F1A]/60 px-2 py-1 text-[10px] font-medium uppercase tracking-wider text-slate-500">Playback</span>
            </div>
            <div class="relative grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                <div id="explore-battle-player-card" class="realm-combat-card relative rounded-2xl border border-indigo-400/25 bg-[#0B0F1A]/55 p-4 shadow-[inset_0_0_24px_-8px_rgba(99,102,241,0.2)] transition-all duration-200">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-300/70">You</p>
                            <p id="explore-battle-user-name" class="text-lg font-semibold text-white"><?php echo $explorerName; ?></p>
                        </div>
                        <div id="explore-battle-user-buffs" class="flex max-w-[40%] flex-wrap justify-end gap-1" aria-label="Your effects"></div>
                    </div>
                    <p class="mb-1 text-[11px] text-slate-500">Spirit (Chi)</p>
                    <div class="mb-1 h-3 overflow-hidden rounded-full bg-black/50 ring-1 ring-indigo-500/25">
                        <div id="explore-battle-user-bar" class="h-full rounded-full bg-gradient-to-r from-indigo-500 via-sky-400 to-cyan-300 shadow-[0_0_12px_-4px_rgba(56,189,248,0.6)] transition-[width] duration-500 ease-out" style="width: 100%"></div>
                    </div>
                    <p id="explore-battle-user-text" class="mb-3 text-right text-[11px] tabular-nums text-slate-400">— / —</p>
                    <p class="mb-1 text-[11px] text-slate-500">Focus</p>
                    <div class="h-2 overflow-hidden rounded-full bg-black/40 ring-1 ring-amber-500/20">
                        <div id="explore-battle-user-stamina-bar" class="h-full rounded-full bg-gradient-to-r from-amber-600/90 to-amber-400/70 transition-[width] duration-300 ease-out" style="width: 100%"></div>
                    </div>
                </div>
                <div id="explore-battle-enemy-card" class="realm-combat-card relative rounded-2xl border border-red-500/25 bg-[#0B0F1A]/55 p-4 shadow-[inset_0_0_24px_-8px_rgba(248,113,113,0.15)] transition-all duration-200">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-red-300/70">Foe</p>
                            <p class="text-lg font-semibold text-red-100/90"><span id="explore-battle-npc-label"></span></p>
                        </div>
                        <div id="explore-battle-enemy-buffs" class="flex max-w-[40%] flex-wrap justify-end gap-1" aria-label="Enemy effects"></div>
                    </div>
                    <p class="mb-1 text-[11px] text-slate-500">Vitality</p>
                    <div class="mb-1 h-3 overflow-hidden rounded-full bg-black/50 ring-1 ring-red-500/25">
                        <div id="explore-battle-npc-bar" class="h-full rounded-full bg-gradient-to-r from-red-600 via-orange-500 to-amber-500 shadow-[0_0_12px_-4px_rgba(248,113,113,0.5)] transition-[width] duration-500 ease-out" style="width: 100%"></div>
                    </div>
                    <p id="explore-battle-npc-text" class="mb-3 text-right text-[11px] tabular-nums text-slate-400">— / —</p>
                    <p class="mb-1 text-[11px] text-slate-500">Malice</p>
                    <div class="h-2 overflow-hidden rounded-full bg-black/40 ring-1 ring-red-500/15">
                        <div id="explore-battle-enemy-pressure-bar" class="h-full rounded-full bg-gradient-to-r from-rose-700/80 to-red-500/60 transition-[width] duration-300 ease-out" style="width: 100%"></div>
                    </div>
                </div>
            </div>
            <div id="explore-battle-log" class="realm-combat-log mt-4 max-h-48 overflow-y-auto rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/65 p-3 text-sm shadow-inner backdrop-blur-sm sm:max-h-56"></div>
            <div id="explore-battle-result" class="mt-3 hidden rounded-xl border border-indigo-500/20 bg-indigo-950/30 px-4 py-3 text-center text-sm font-semibold"></div>
            <div id="explore-battle-chi-display" class="mt-2 hidden text-center text-sm text-cyan-300">Chi: <span id="explore-battle-chi-value"></span></div>
            <div id="explore-combat-actions" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4" role="group" aria-label="Combat actions">
                <button type="button" class="realm-combat-action rounded-xl border border-indigo-400/30 bg-indigo-950/40 px-3 py-3 text-xs font-semibold text-indigo-100 shadow-[0_0_16px_-8px_rgba(99,102,241,0.5)] transition-all duration-200 hover:border-indigo-300/50 hover:shadow-[0_0_24px_-6px_rgba(129,140,248,0.45)] disabled:pointer-events-none disabled:opacity-40" disabled>Attack</button>
                <button type="button" class="realm-combat-action rounded-xl border border-sky-400/25 bg-sky-950/30 px-3 py-3 text-xs font-semibold text-sky-100 transition-all duration-200 hover:border-sky-300/45 hover:shadow-[0_0_20px_-8px_rgba(56,189,248,0.35)] disabled:pointer-events-none disabled:opacity-40" disabled>Technique</button>
                <button type="button" class="realm-combat-action rounded-xl border border-amber-400/25 bg-amber-950/25 px-3 py-3 text-xs font-semibold text-amber-100 transition-all duration-200 hover:border-amber-300/45 hover:shadow-[0_0_20px_-8px_rgba(232,184,74,0.3)] disabled:pointer-events-none disabled:opacity-40" disabled>Use item</button>
                <button type="button" class="realm-combat-action rounded-xl border border-emerald-400/25 bg-emerald-950/25 px-3 py-3 text-xs font-semibold text-emerald-100 transition-all duration-200 hover:border-emerald-300/45 hover:shadow-[0_0_20px_-8px_rgba(74,222,128,0.3)] disabled:pointer-events-none disabled:opacity-40" disabled>Defend</button>
            </div>
            <p class="mt-2 text-center text-[10px] text-slate-500">Encounters resolve automatically — buttons preview future interactable combat.</p>
        </div>

        <div id="explore-result" class="mb-6 hidden rounded-2xl border border-indigo-500/20 bg-[rgba(20,25,45,0.75)] p-6 backdrop-blur-xl"></div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <div class="rounded-2xl border border-indigo-500/20 bg-[rgba(20,25,45,0.65)] p-4 shadow-[0_0_40px_-16px_rgba(99,102,241,0.3)] backdrop-blur-xl sm:p-5">
                    <p class="mb-3 text-center text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Realm nodes</p>
                    <div class="relative mx-auto max-w-2xl">
                        <svg id="realm-map-svg" viewBox="0 0 100 100" class="aspect-square w-full overflow-visible" role="img" aria-label="Region map">
                            <defs>
                                <radialGradient id="realmMapBg" cx="50%" cy="45%" r="65%">
                                    <stop offset="0%" stop-color="rgba(99,102,241,0.12)"/>
                                    <stop offset="100%" stop-color="rgba(11,15,26,0)"/>
                                </radialGradient>
                                <filter id="realmNodeGlow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feGaussianBlur stdDeviation="0.8" result="b"/>
                                    <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                                </filter>
                            </defs>
                            <rect width="100" height="100" fill="url(#realmMapBg)" rx="2"/>
                            <?php if ($regionCount > 1): ?>
                                <?php for ($i = 0; $i < $regionCount; $i++): ?>
                                    <?php
                                    $j = ($i + 1) % $regionCount;
                                    $a = $mapNodes[$i];
                                    $b = $mapNodes[$j];
                                    $rA = $a['region'];
                                    $dangerEdge = (int)($rA['difficulty'] ?? 1) >= 3 || (int)($b['region']['difficulty'] ?? 1) >= 3;
                                    ?>
                                    <line
                                        class="realm-path-line <?php echo $dangerEdge ? 'realm-path-line--danger' : ''; ?>"
                                        x1="<?php echo $a['x']; ?>"
                                        y1="<?php echo $a['y']; ?>"
                                        x2="<?php echo $b['x']; ?>"
                                        y2="<?php echo $b['y']; ?>"
                                    />
                                <?php endfor; ?>
                            <?php endif; ?>

                            <?php foreach ($mapNodes as $node): ?>
                                <?php
                                $r = $node['region'];
                                $rid = (int)($r['id'] ?? 0);
                                $locked = !empty($r['locked']);
                                $diff = (int)($r['difficulty'] ?? 1);
                                $hd = (float)($r['hidden_dungeon_chance'] ?? 0);
                                $isBoss = $diff >= 4 || $hd >= 1.5;
                                $isDanger = $diff >= 3 && !$locked;
                                $isCurrent = !empty($r['is_current']);
                                $nodeClasses = 'realm-map-node-group';
                                if ($locked) {
                                    $nodeClasses .= ' realm-map-node--locked';
                                }
                                if ($isBoss) {
                                    $nodeClasses .= ' realm-map-node--boss';
                                } elseif ($isDanger) {
                                    $nodeClasses .= ' realm-map-node--danger';
                                } elseif (!$locked) {
                                    $nodeClasses .= ' realm-map-node--open';
                                }
                                $fill = $locked ? '#475569' : ($isBoss ? '#b91c1c' : ($isDanger ? '#c2410c' : '#4f46e5'));
                                $stroke = $isCurrent ? '#fbbf24' : 'rgba(148,163,184,0.5)';
                                ?>
                                <g
                                    class="<?php echo htmlspecialchars($nodeClasses, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-region-id="<?php echo $rid; ?>"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Region <?php echo htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <circle class="realm-node-halo" cx="<?php echo $node['x']; ?>" cy="<?php echo $node['y']; ?>" r="7" fill="rgba(99,102,241,0.15)" style="animation: realm-node-glow 3s ease-in-out infinite"/>
                                    <circle
                                        class="realm-node-ring"
                                        cx="<?php echo $node['x']; ?>"
                                        cy="<?php echo $node['y']; ?>"
                                        r="5.2"
                                        fill="<?php echo htmlspecialchars($fill, ENT_QUOTES, 'UTF-8'); ?>"
                                        stroke="<?php echo htmlspecialchars($stroke, ENT_QUOTES, 'UTF-8'); ?>"
                                        stroke-width="0.45"
                                        filter="url(#realmNodeGlow)"
                                    />
                                    <circle class="realm-node-core" cx="<?php echo $node['x']; ?>" cy="<?php echo $node['y']; ?>" r="2.4" fill="rgba(255,255,255,0.85)"/>
                                    <text
                                        x="<?php echo $node['x']; ?>"
                                        y="<?php echo $node['y'] + 8.5; ?>"
                                        text-anchor="middle"
                                        fill="rgba(226,232,240,0.92)"
                                        font-size="2.8"
                                        font-weight="600"
                                        style="pointer-events: none;"
                                    ><?php echo htmlspecialchars(function_exists('mb_substr') ? mb_substr((string)($r['name'] ?? '?'), 0, 10) : substr((string)($r['name'] ?? '?'), 0, 10), ENT_QUOTES, 'UTF-8'); ?></text>
                                </g>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-center gap-3 text-[10px] text-slate-500">
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-slate-600"></span> Locked</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-indigo-600"></span> Open</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-orange-700"></span> Dangerous</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-2 animate-pulse rounded-full bg-red-700"></span> Boss active</span>
                    </div>
                </div>
            </div>

            <aside class="lg:col-span-4">
                <div id="region-detail-card" class="sticky top-4 rounded-2xl border border-indigo-500/25 bg-[rgba(20,25,45,0.82)] p-5 shadow-[0_0_32px_-12px_rgba(99,102,241,0.28)] backdrop-blur-xl transition-shadow duration-200">
                    <div id="region-detail-empty" class="text-center text-sm text-slate-500">
                        <p class="mb-2 text-2xl opacity-40">◇</p>
                        <p>Select a realm node to plan your expedition.</p>
                    </div>
                    <div id="region-detail-body" class="hidden space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-indigo-500/15 pb-3">
                            <div>
                                <h3 id="region-detail-name" class="text-xl font-semibold text-white"></h3>
                                <div id="region-detail-badges" class="mt-2 flex flex-wrap gap-1.5"></div>
                            </div>
                        </div>
                        <p id="region-detail-desc" class="text-sm leading-relaxed text-slate-400"></p>
                        <dl class="grid grid-cols-1 gap-2 text-xs text-slate-400 sm:grid-cols-2">
                            <div><dt class="text-slate-500">Resources</dt><dd id="region-detail-resources" class="text-indigo-200"></dd></div>
                            <div><dt class="text-slate-500">Encounters</dt><dd id="region-detail-encounters"></dd></div>
                            <div><dt class="text-slate-500">Dungeon chance</dt><dd id="region-detail-dungeon"></dd></div>
                            <div><dt class="text-slate-500">Requires</dt><dd id="region-detail-realm" class="text-amber-200/90"></dd></div>
                        </dl>
                        <div class="flex flex-col gap-2">
                            <button
                                type="button"
                                id="region-panel-explore"
                                class="explore-btn rounded-xl border border-emerald-500/35 bg-gradient-to-r from-emerald-700/80 to-cyan-700/70 py-3 text-sm font-semibold text-white shadow-[0_0_20px_-8px_rgba(52,211,153,0.45)] transition-all duration-200 hover:shadow-[0_0_28px_-6px_rgba(52,211,153,0.5)] disabled:cursor-not-allowed disabled:opacity-45"
                                data-region-id=""
                            >
                                Explore
                            </button>
                            <a href="/game/dungeons" class="rounded-xl border border-violet-500/30 bg-violet-950/40 py-3 text-center text-sm font-semibold text-violet-200 transition-all duration-200 hover:border-violet-400/45 hover:shadow-[0_0_20px_-8px_rgba(139,92,246,0.35)]">
                                Enter Dungeon
                            </a>
                            <a href="world_boss.php" class="rounded-xl border border-red-500/30 bg-red-950/35 py-3 text-center text-sm font-semibold text-red-200/90 transition-all duration-200 hover:border-red-400/45 hover:shadow-[0_0_22px_-8px_rgba(248,113,113,0.35)]">
                                Hunt Boss
                            </a>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>window.REALM_MAP_REGIONS = <?php echo json_encode($regionsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
    <script src="/js/realm-sounds.js"></script>
    <script src="/js/realm-particles.js"></script>
    <script src="../explore_encounter.js"></script>
    <script>
    (function() {
        var messageEl = document.getElementById('explore-message');
        var resultEl = document.getElementById('explore-result');
        var cooldownEl = document.getElementById('explore-cooldown');
        var cooldownLabelEl = document.getElementById('explore-cooldown-label');
        var burstTextEl = document.getElementById('explore-burst-text');
        var longRestLineEl = document.getElementById('explore-long-rest-line');
        var burstHintEl = document.getElementById('explore-burst-hint');
        var cooldownRemaining = <?php echo $cooldownRemaining; ?>;
        var cooldownKind = <?php echo json_encode($cooldownKind, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        var cooldownInterval = null;
        var selectedRegionId = null;

        function formatCooldown(seconds) {
            var s = Math.max(0, parseInt(seconds, 10) || 0);
            if (s >= 3600) return Math.ceil(s / 3600) + 'h';
            if (s >= 120) return Math.ceil(s / 60) + 'm';
            return s + 's';
        }

        function showMessage(text, isError) {
            if (!messageEl) return;
            messageEl.textContent = text;
            messageEl.className = 'mb-4 rounded-xl border p-3 ' + (isError ? 'border-red-500/40 bg-red-950/30 text-red-200' : 'border-emerald-500/35 bg-emerald-950/25 text-emerald-200');
            messageEl.classList.remove('hidden');
        }

        function setCooldownKind(kind) {
            cooldownKind = kind || 'none';
            if (!cooldownLabelEl) return;
            if (kind === 'long_rest') {
                cooldownLabelEl.textContent = 'Long rest';
            } else if (kind === 'interval') {
                cooldownLabelEl.textContent = 'Breath interval';
            } else {
                cooldownLabelEl.textContent = 'Next explore';
            }
            if (cooldownEl) {
                cooldownEl.classList.toggle('text-amber-200', kind === 'long_rest');
                cooldownEl.classList.toggle('text-white', kind !== 'long_rest');
            }
        }

        function setCooldown(seconds, kind) {
            cooldownRemaining = Math.max(0, seconds || 0);
            if (kind) setCooldownKind(kind);
            if (cooldownEl) {
                cooldownEl.textContent = cooldownRemaining > 0 ? formatCooldown(cooldownRemaining) : 'Ready';
            }
            document.querySelectorAll('.explore-btn').forEach(function(btn) {
                if (btn.getAttribute('data-locked') === '1') return;
                btn.disabled = cooldownRemaining > 0;
            });
        }

        function setBurstFromPayload(payload) {
            if (!burstTextEl || !payload) return;
            var u = payload.explore_burst_used;
            var m = payload.explore_burst_max;
            if (u == null || m == null) return;
            burstTextEl.textContent = u + ' / ' + m;
            var lr = payload.explore_in_long_rest === true;
            if (payload.explore_cooldown_kind) {
                setCooldownKind(payload.explore_cooldown_kind);
            } else if (lr) {
                setCooldownKind('long_rest');
            } else if (cooldownRemaining > 0 && cooldownRemaining <= 120) {
                setCooldownKind('interval');
            }
            if (longRestLineEl) {
                longRestLineEl.classList.toggle('hidden', !lr);
                if (lr) longRestLineEl.classList.add('text-amber-200/95');
            }
            if (burstHintEl) {
                burstHintEl.textContent = lr
                    ? ' (mandatory rest after completing a full burst)'
                    : ' (explores before mandatory long rest)';
            }
        }

        function startCooldown(seconds, kind) {
            if (cooldownInterval) {
                clearInterval(cooldownInterval);
                cooldownInterval = null;
            }
            setCooldown(seconds, kind);
            if (seconds <= 0) return;
            cooldownInterval = window.setInterval(function() {
                cooldownRemaining -= 1;
                setCooldown(cooldownRemaining);
                if (cooldownRemaining <= 0) {
                    clearInterval(cooldownInterval);
                    cooldownInterval = null;
                    if (selectedRegionId) selectRegion(selectedRegionId);
                }
            }, 1000);
        }

        function renderItem(item) {
            if (!item) return '';
            return '<div class="text-sm text-emerald-300">Found: ' + item.name + ' x' + (item.quantity || 1) + '</div>';
        }

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function buildEncounterAftermathHtml(payload, data) {
            if (!data) return '<p class="text-slate-400">Encounter complete.</p>';
            var html = '<h3 class="text-lg font-semibold text-amber-300 mb-2">Encounter: ' + escapeHtml(data.npc_name || 'Enemy') + '</h3>';
            html += '<p class="text-sm text-slate-400 mb-3">' + escapeHtml(payload.message || '') + '</p>';
            html += '<ul class="text-sm text-slate-300 space-y-1 list-disc list-inside">';
            html += '<li>Winner: <strong class="text-white">' + escapeHtml(String(data.winner || '')) + '</strong></li>';
            html += '<li>Chi reward: ' + (data.chi_reward || 0) + ' · Gold: ' + (data.gold_gained || 0) + ' · Spirit stones: ' + (data.spirit_stone_gained || 0) + '</li>';
            html += '</ul>';
            if (data.herb_dropped) html += '<div class="text-green-300 text-sm mt-2">Herb: ' + escapeHtml(data.herb_dropped.name || '') + '</div>';
            if (data.material_dropped) html += '<div class="text-orange-300 text-sm mt-1">Material: ' + escapeHtml(data.material_dropped.name || '') + '</div>';
            if (data.rune_fragment_dropped) html += '<div class="text-purple-300 text-sm mt-1">Rune fragment acquired.</div>';
            if (data.dropped_item) {
                var it = data.dropped_item;
                html += '<div class="text-cyan-300 text-sm mt-2">Gear drop: ' + escapeHtml(it.name || 'Item') + '</div>';
            }
            html += '<p class="text-xs text-slate-500 mt-4">Rewards above are already applied. Battle playback is shown in the panel above.</p>';
            return html;
        }

        function renderResult(payload) {
            if (!resultEl) return;
            var html = '';
            var eventType = payload.event_type || 'nothing';
            var data = payload.data || null;

            if (eventType === 'encounter' && data && typeof playExploreEncounterBattle === 'function') {
                resultEl.classList.add('hidden');
                playExploreEncounterBattle('explore-', data, function() {
                    resultEl.innerHTML = buildEncounterAftermathHtml(payload, data);
                    resultEl.classList.remove('hidden');
                });
                return;
            }

            if (eventType === 'encounter' && data) {
                html = buildEncounterAftermathHtml(payload, data);
            } else if (eventType === 'dungeon_discovery' && data && data.dungeon) {
                html = '<h3 class="text-lg font-semibold text-purple-300 mb-2">Hidden Dungeon Discovered</h3>';
                html += '<p class="text-slate-300 mb-2">' + data.dungeon.name + ' | Difficulty ' + data.dungeon.difficulty + '</p>';
                html += '<p class="text-slate-400 mb-3">Boss: ' + data.dungeon.boss_name + '</p>';
                if (data.dungeon.locked) {
                    html += '<p class="text-amber-300 text-sm">Requires ' + (data.dungeon.min_realm_name || 'Realm') + ' to enter.</p>';
                } else {
                    html += '<a href="/game/dungeon/' + data.dungeon.id + '" class="inline-block px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg font-semibold">Enter Dungeon</a>';
                }
            } else if (eventType === 'manual_discovery' && data && data.manual) {
                html = '<h3 class="text-lg font-semibold text-violet-300 mb-2">Forgotten Manual Unearthed</h3>';
                html += '<div class="text-violet-200">' + data.manual.name + ' <span class="text-slate-400">(' + data.manual.rarity + ')</span></div>';
                html += '<a href="cultivation_manuals.php" class="inline-block mt-3 px-4 py-2 bg-violet-600 hover:bg-violet-500 text-white rounded-lg font-semibold">Open Manuals</a>';
            } else if (data && data.item) {
                html = '<h3 class="text-lg font-semibold text-cyan-300 mb-2">' + (payload.region_name || 'Region') + '</h3>' + renderItem(data.item);
            } else {
                html = '<h3 class="text-lg font-semibold text-cyan-300 mb-2">' + (payload.region_name || 'Region') + '</h3><p class="text-slate-400">Nothing unusual happened.</p>';
            }

            resultEl.innerHTML = html;
            resultEl.classList.remove('hidden');
        }

        function selectRegion(id) {
            var r = window.REALM_MAP_REGIONS[String(id)];
            if (!r) return;
            selectedRegionId = id;
            document.querySelectorAll('.realm-map-node-group').forEach(function(g) {
                g.classList.toggle('realm-map-node--selected', g.getAttribute('data-region-id') === String(id));
            });
            var empty = document.getElementById('region-detail-empty');
            var body = document.getElementById('region-detail-body');
            var card = document.getElementById('region-detail-card');
            if (empty) empty.classList.add('hidden');
            if (body) body.classList.remove('hidden');
            if (card) card.classList.add('shadow-[0_0_40px_-8px_rgba(251,191,36,0.2)]');

            document.getElementById('region-detail-name').textContent = r.name;
            document.getElementById('region-detail-desc').textContent = r.description || '—';
            document.getElementById('region-detail-resources').textContent = r.resource_type || '—';
            document.getElementById('region-detail-encounters').textContent = r.exploration_encounters || '—';
            document.getElementById('region-detail-dungeon').textContent = (r.hidden_dungeon_chance != null ? Number(r.hidden_dungeon_chance).toFixed(2) : '0') + '%';
            document.getElementById('region-detail-realm').textContent = r.min_realm_name || '—';

            var badges = document.getElementById('region-detail-badges');
            badges.innerHTML = '';
            function addBadge(label, cls) {
                var s = document.createElement('span');
                s.className = 'rounded-lg border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ' + cls;
                s.textContent = label;
                badges.appendChild(s);
            }
            if (r.locked) addBadge('Locked', 'border-slate-600 bg-slate-900/60 text-slate-400');
            if (r.is_current) addBadge('Here', 'border-amber-500/40 bg-amber-950/40 text-amber-200');
            var diff = r.difficulty || 1;
            var hd = r.hidden_dungeon_chance || 0;
            if (diff >= 4 || hd >= 1.5) addBadge('Boss active', 'border-red-500/40 bg-red-950/35 text-red-200 animate-pulse');
            else if (diff >= 3 && !r.locked) addBadge('Dangerous', 'border-orange-500/35 bg-orange-950/30 text-orange-200');
            else if (!r.locked) addBadge('Open', 'border-emerald-500/35 bg-emerald-950/25 text-emerald-200');
            addBadge('Difficulty ' + diff, 'border-indigo-500/30 bg-indigo-950/30 text-indigo-200');

            var ex = document.getElementById('region-panel-explore');
            ex.setAttribute('data-region-id', String(id));
            ex.setAttribute('data-region-name', r.name);
            if (r.locked) {
                ex.disabled = true;
                ex.setAttribute('data-locked', '1');
                ex.textContent = 'Locked';
            } else {
                ex.removeAttribute('data-locked');
                ex.disabled = cooldownRemaining > 0;
                ex.textContent = cooldownRemaining > 0 ? 'Explore (cooldown)' : 'Explore';
            }
        }

        var svg = document.getElementById('realm-map-svg');
        if (svg) {
            svg.addEventListener('click', function(e) {
                var g = e.target.closest('.realm-map-node-group');
                if (!g) return;
                var id = g.getAttribute('data-region-id');
                if (id) selectRegion(id);
            });
            svg.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var g = e.target.closest('.realm-map-node-group');
                if (!g) return;
                e.preventDefault();
                var id = g.getAttribute('data-region-id');
                if (id) selectRegion(id);
            });
        }

        document.body.addEventListener('click', function(e) {
            var btn = e.target.closest('.explore-btn');
            if (!btn || btn.disabled) return;
            var regionId = btn.getAttribute('data-region-id');
            if (!regionId) return;
            var battlePanel = document.getElementById('explore-battle-panel');
            if (battlePanel) battlePanel.classList.add('hidden');
            btn.disabled = true;
            var fd = new FormData();
            fd.append('region_id', regionId);

            fetch('../controllers/explore_region.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.data) {
                        showMessage(data.message || 'Exploration complete.', false);
                        var payload = data.data;
                        if (data.message) payload.message = data.message;
                        renderResult(payload);
                        setBurstFromPayload(payload);
                        startCooldown(parseInt(data.data.cooldown_remaining || 0, 10), data.data.explore_cooldown_kind || null);
                    } else {
                        showMessage(data.message || 'Exploration failed.', true);
                        if (data.data && (data.data.cooldown_remaining != null || data.data.explore_burst_used != null)) {
                            setBurstFromPayload(data.data);
                            if (data.data.cooldown_remaining != null) {
                                startCooldown(parseInt(data.data.cooldown_remaining, 10), data.data.explore_cooldown_kind || null);
                            } else {
                                btn.disabled = false;
                            }
                        } else {
                            btn.disabled = false;
                        }
                    }
                })
                .catch(function() {
                    showMessage('Request failed.', true);
                    btn.disabled = false;
                });
        });

        setCooldownKind(cooldownKind);
        startCooldown(cooldownRemaining, cooldownKind);
    })();
    </script>
</body>
</html>
