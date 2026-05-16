<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/SessionHelper.php';
require_once dirname(__DIR__) . '/services/DungeonService.php';
require_once dirname(__DIR__) . '/includes/realm_display.php';

use Game\Helper\SessionHelper;
use Game\Service\DungeonService;

legacy_session_start();
$userId = SessionHelper::requireLoggedIn();
$dungeonId = (int)($_GET['dungeon_id'] ?? $_POST['dungeon_id'] ?? 0);

$service = new DungeonService();
$message = null;
$error = null;
$battleResult = null;

if ($dungeonId < 1) {
    header('Location: /game/dungeons');
    exit;
}

$dungeon = $service->getDungeonById($dungeonId);
if (!$dungeon) {
    header('Location: /game/dungeons');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    if ($action === 'start_run') {
        $result = $service->startRun($userId, $dungeonId);
        $_SESSION['dungeon_flash'] = [
            'dungeon_id' => $dungeonId,
            'message' => $result['success'] ? ($result['message'] ?? '') : null,
            'error' => !$result['success'] ? ($result['message'] ?? 'Could not start run.') : null,
            'battle' => null,
            'stage_name' => null,
            'rewards' => null,
            'completed' => false,
        ];
    } elseif ($action === 'advance_run') {
        $runId = (int)($_POST['run_id'] ?? 0);
        $result = $service->advanceRun($userId, $runId);
        $_SESSION['dungeon_flash'] = [
            'dungeon_id' => $dungeonId,
            'message' => $result['success'] ? ($result['message'] ?? '') : null,
            'error' => !$result['success'] ? ($result['message'] ?? 'Advance failed.') : null,
            'battle' => $result['battle'] ?? null,
            'stage_name' => $result['stage_name'] ?? null,
            'rewards' => $result['rewards'] ?? null,
            'completed' => !empty($result['completed']),
        ];
    }
    header('Location: /game/dungeon/'.$dungeonId);
    exit;
}

if (!empty($_SESSION['dungeon_flash']) && is_array($_SESSION['dungeon_flash'])) {
    $flash = $_SESSION['dungeon_flash'];
    unset($_SESSION['dungeon_flash']);
    if ((int)($flash['dungeon_id'] ?? 0) === $dungeonId) {
        $message = !empty($flash['message']) ? (string)$flash['message'] : null;
        $error = !empty($flash['error']) ? (string)$flash['error'] : null;
        if (!empty($flash['battle']) && is_array($flash['battle'])) {
            $battleResult = [
                'battle' => $flash['battle'],
                'stage_name' => $flash['stage_name'] ?? 'Battle',
                'rewards' => $flash['rewards'] ?? null,
                'completed' => !empty($flash['completed']),
            ];
        }
    }
}

$activeRun = $service->getActiveRunForUser($userId, $dungeonId);
$runsRemaining = $service->getDailyRunsRemaining($userId);
$stagePreview = $service->getStagePreview($activeRun ?: $dungeon);
$locked = ($service->getDungeonsForUser($userId)['user_realm_id'] ?? 1) < (int)$dungeon['min_realm_id'];

$dungeonBattleJson = null;
$pulseRoomIndex = null;
if ($battleResult && !empty($battleResult['battle'])) {
    $dungeonBattleJson = [
        'battle' => $battleResult['battle'],
        'stage_name' => $battleResult['stage_name'] ?? 'Battle',
        'rewards' => $battleResult['rewards'] ?? null,
        'playerName' => (string)($_SESSION['username'] ?? 'Cultivator'),
        'regionName' => (string)($dungeon['region_name'] ?? ''),
        'dungeonName' => (string)($dungeon['name'] ?? ''),
    ];
    $sn = (string)($battleResult['stage_name'] ?? '');
    if (preg_match('/Stage\s*3|Boss\b/i', $sn)) {
        $pulseRoomIndex = 3;
    } elseif (preg_match('/Stage\s*2|Elite/i', $sn)) {
        $pulseRoomIndex = 2;
    } else {
        $pulseRoomIndex = 1;
    }
}

$nextRoomIndex = 1;
if ($activeRun) {
    $nextRoomIndex = min(3, (int)$activeRun['progress'] + 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dungeon['name'] ?? 'Dungeon', ENT_QUOTES, 'UTF-8'); ?> - Cultivation Journey</title>
    <link rel="stylesheet" href="/css/dungeon-combat.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-[#050810] via-slate-950 to-[#0a0612] min-h-screen text-slate-200">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="flex justify-between items-center mb-8 flex-wrap gap-4">
            <div class="flex items-center gap-4 flex-wrap">
                <?php $site_brand_compact = true; require_once dirname(__DIR__) . '/includes/site_brand.php'; ?>
                <h1 class="text-4xl font-bold bg-gradient-to-r from-violet-300 via-fuchsia-400 to-amber-400 bg-clip-text text-transparent drop-shadow-[0_0_24px_rgba(139,92,246,0.35)]">
                    <?php echo htmlspecialchars($dungeon['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </h1>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <button type="button" data-realm-audio-toggle class="realm-audio-toggle rounded-lg border border-violet-500/30 bg-gray-900/80 px-3 py-2 text-lg" aria-pressed="true" title="Sound on"><span data-realm-audio-icon aria-hidden="true">🔊</span></button>
                <a href="/game/dungeons" class="px-4 py-2 bg-gray-900/90 hover:bg-gray-800 rounded-lg border border-purple-500/30 text-purple-300 transition-all">Dungeons</a>
                <a href="game.php" class="px-4 py-2 bg-gray-900/90 hover:bg-gray-800 rounded-lg border border-cyan-500/30 text-cyan-300 transition-all">← Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-emerald-950/40 border border-emerald-500/45 rounded-xl text-emerald-200"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-950/40 border border-red-500/50 rounded-xl text-red-200"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="bg-gray-900/70 backdrop-blur-md border border-violet-500/25 rounded-xl p-6 mb-6 shadow-[0_0_40px_-12px_rgba(139,92,246,0.25)]">
            <p class="text-slate-400 mb-2">Region: <span class="text-white font-medium"><?php echo htmlspecialchars($dungeon['region_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></p>
            <p class="text-slate-400 mb-2">Difficulty: <span class="text-white font-medium"><?php echo (int)($dungeon['difficulty'] ?? 1); ?></span></p>
            <p class="text-slate-400 mb-2">Boss: <span class="text-white font-medium"><?php echo htmlspecialchars($dungeon['boss_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></p>
            <p class="text-slate-400 mb-2">Requires: <span class="text-white font-medium"><?php echo htmlspecialchars(realm_display_label($dungeon['min_realm_name'] ?? null, (int)($dungeon['min_realm_id'] ?? 0) ?: null), ENT_QUOTES, 'UTF-8'); ?></span></p>
            <p class="text-sm text-slate-500">Daily runs remaining: <?php echo $runsRemaining; ?> / 3</p>
        </div>

        <div class="bg-gray-900/70 backdrop-blur-md border border-slate-700/80 rounded-xl p-6 mb-6">
            <h2 class="text-xl font-semibold text-violet-200 mb-4 tracking-tight">Dungeon path</h2>
            <p class="text-xs text-slate-500 mb-3">Treasure caches and ward-stones react as you push deeper. Clearing a stage lights the way forward.</p>
            <div class="space-y-3">
                <div data-dc-room="1" class="dc-room dc-room--corridor rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-2 border transition-all <?php echo ($activeRun && (int)$activeRun['progress'] > 0) ? 'bg-emerald-950/25 border-emerald-500/35' : ($nextRoomIndex === 1 && $activeRun ? 'bg-violet-950/30 border-violet-500/40 dc-room--enter' : 'bg-slate-950/50 border-slate-700'); ?>">
                    <div>
                        <div class="font-semibold text-white">I — Threshold hall</div>
                        <div class="text-xs text-slate-500">Normal guardian · subtle ward</div>
                    </div>
                    <div class="flex gap-2 text-lg opacity-80" aria-hidden="true">
                        <span title="Treasure" class="hover:scale-110 transition-transform">📜</span>
                        <span title="Hidden alcove" class="hover:scale-110 transition-transform">✦</span>
                    </div>
                </div>
                <div data-dc-room="2" class="dc-room dc-room--corridor rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-2 border transition-all <?php echo ($activeRun && (int)$activeRun['progress'] > 1) ? 'bg-emerald-950/25 border-emerald-500/35' : ($nextRoomIndex === 2 && $activeRun ? 'bg-violet-950/30 border-violet-500/40 dc-room--enter' : 'bg-slate-950/50 border-slate-700'); ?>">
                    <div>
                        <div class="font-semibold text-white">II — Inner sanctum</div>
                        <div class="text-xs text-slate-500">Elite warden · trapped tiles</div>
                    </div>
                    <div class="flex gap-2 text-lg opacity-80" aria-hidden="true">
                        <span title="Trap sigil" class="hover:scale-110 transition-transform text-amber-400/90">⚠</span>
                        <span title="Spirit cache" class="hover:scale-110 transition-transform">💎</span>
                    </div>
                </div>
                <div data-dc-room="3" class="dc-room dc-room--corridor rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-2 border transition-all <?php echo ($activeRun && (int)$activeRun['progress'] > 2) ? 'bg-emerald-950/25 border-emerald-500/35' : ($nextRoomIndex === 3 && $activeRun ? 'bg-amber-950/25 border-amber-500/40 dc-room--enter' : 'bg-slate-950/50 border-slate-700'); ?>">
                    <div>
                        <div class="font-semibold text-amber-100/95">III — Heart of the dungeon</div>
                        <div class="text-xs text-slate-500">Boss arena · environmental qi storm</div>
                    </div>
                    <div class="flex gap-2 text-lg opacity-90" aria-hidden="true">
                        <span class="animate-pulse">👹</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($battleResult && !empty($battleResult['battle'])): ?>
            <?php
            $battle = $battleResult['battle'];
            $wonBattle = (($battle['winner'] ?? '') === 'user');
            $outcomeClass = $wonBattle ? 'dc-outcome--win' : 'dc-outcome--lose';
            $outcomeLabel = $wonBattle ? 'Victory' : 'Defeat';
            ?>
            <div id="dungeon-combat-mount" class="mb-6">
                <script type="application/json" data-dungeon-battle><?php echo json_encode($dungeonBattleJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
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
                                <div class="dc-stage-pill"><?php echo htmlspecialchars($battleResult['stage_name'] ?? 'Battle', ENT_QUOTES, 'UTF-8'); ?></div>
                                <p class="dc-outcome <?php echo $outcomeClass; ?> mt-1"><?php echo $outcomeLabel; ?></p>
                                <p class="text-slate-500 text-sm mt-1 max-w-xl"><?php echo $wonBattle ? 'You won this fight. Rewards below are already applied on the server.' : 'You were forced back. Your run remains — challenge this stage again when ready.'; ?></p>
                            </div>
                        </header>
                        <div class="dc-combat-grid">
                            <div class="dc-panel dc-panel--player">
                                <div class="dc-panel-head">
                                    <div>
                                        <div class="dc-panel-title">Cultivator</div>
                                        <div class="dc-name" data-dc-name-player>—</div>
                                    </div>
                                    <div class="dc-enemy-portrait bg-indigo-950/80 border-indigo-500/30" aria-hidden="true">🜂</div>
                                </div>
                                <div class="dc-bar-label"><span>Spirit (chi)</span><span data-dc-player-pct>—</span></div>
                                <div class="dc-bar-track"><div class="dc-bar-fill dc-bar-fill--player" style="width:100%"></div></div>
                            </div>
                            <div class="dc-panel dc-panel--enemy dc-rarity-common">
                                <div class="dc-panel-head">
                                    <div>
                                        <div class="dc-panel-title">Adversary</div>
                                        <div class="dc-name" data-dc-name-enemy>—</div>
                                    </div>
                                    <div class="dc-enemy-portrait" aria-hidden="true">⚔️</div>
                                </div>
                                <div class="dc-bar-label"><span>Vitality</span><span data-dc-npc-pct>—</span></div>
                                <div class="dc-bar-track"><div class="dc-bar-fill dc-bar-fill--enemy" style="width:100%"></div></div>
                            </div>
                        </div>
                        <div class="dc-skill-strip" aria-live="polite"><strong>Technique rhythm:</strong> playback reveals your Dao exchanges.</div>
                        <div class="dc-playback-bar">
                            <button type="button" class="dc-btn" data-dc-play>Replay combat</button>
                            <button type="button" class="dc-btn" data-dc-skip>Skip to end</button>
                            <div class="dc-progress" aria-hidden="true"><span></span></div>
                        </div>
                        <div class="dc-log" role="log" aria-label="Combat log"></div>
                        <?php if (!empty($battleResult['rewards'])): ?>
                            <?php
                            $rw = $battleResult['rewards'];
                            $lootLegendary = !empty($rw['manual']) && in_array(strtolower((string)($rw['manual']['rarity'] ?? '')), ['legendary', 'mythic', 'ancient'], true);
                            ?>
                            <div class="dc-loot <?php echo $lootLegendary ? 'dc-loot--legendary' : ''; ?>">
                                <div class="text-amber-200/90 text-sm font-semibold mb-2 tracking-wide">Spoils</div>
                                <div class="dc-loot-card">
                                    <span class="dc-loot-pill">🪙 <?php echo (int)($rw['gold'] ?? 0); ?> gold</span>
                                    <span class="dc-loot-pill dc-loot-pill--spirit">✧ <?php echo (int)($rw['spirit_stones'] ?? 0); ?> spirit stones</span>
                                    <?php if (!empty($rw['manual'])): ?>
                                        <div class="dc-loot-manual">
                                            Manual: <strong><?php echo htmlspecialchars((string)$rw['manual']['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="text-violet-300">(<?php echo htmlspecialchars(ucfirst((string)$rw['manual']['rarity']), ENT_QUOTES, 'UTF-8'); ?>)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <noscript>
                    <div class="mt-4 rounded-xl border border-slate-600 bg-slate-950/80 p-4 text-sm text-slate-400">
                        <p class="text-slate-300 font-medium mb-2">Combat log (enable JavaScript for cinematic playback)</p>
                        <?php foreach (($battle['battle_log'] ?? []) as $row): ?>
                            <div>
                                <?php echo htmlspecialchars(($row['attacker'] ?? '') === 'user' ? 'You' : ($battle['npc_name'] ?? 'Enemy'), ENT_QUOTES, 'UTF-8'); ?>
                                — <?php echo (int)($row['damage'] ?? 0); ?> damage
                            </div>
                        <?php endforeach; ?>
                    </div>
                </noscript>
            </div>
        <?php endif; ?>

        <div class="bg-gray-900/70 backdrop-blur-md border border-slate-700/80 rounded-xl p-6 shadow-inner">
            <?php if ($locked): ?>
                <p class="text-amber-300">Requires <?php echo htmlspecialchars(realm_display_label($dungeon['min_realm_name'] ?? null, (int)($dungeon['min_realm_id'] ?? 0) ?: null), ENT_QUOTES, 'UTF-8'); ?> to enter.</p>
            <?php elseif ($activeRun): ?>
                <h2 class="text-xl font-semibold text-violet-300 mb-2"><?php echo htmlspecialchars($stagePreview['label'] ?? 'Next Stage', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="text-sm text-slate-400 mb-4">Enemy: <span class="text-slate-200"><?php echo htmlspecialchars($stagePreview['enemy_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <form method="POST">
                    <input type="hidden" name="dungeon_id" value="<?php echo $dungeonId; ?>">
                    <input type="hidden" name="run_id" value="<?php echo (int)$activeRun['id']; ?>">
                    <input type="hidden" name="action" value="advance_run">
                    <button type="submit" class="px-5 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-500 hover:to-fuchsia-500 text-white font-semibold shadow-[0_0_24px_-8px_rgba(139,92,246,0.55)] transition-all">Challenge Stage</button>
                </form>
            <?php elseif ($runsRemaining > 0): ?>
                <h2 class="text-xl font-semibold text-violet-300 mb-2">Begin a new run</h2>
                <p class="text-sm text-slate-400 mb-4">Three stages await inside this hidden dungeon.</p>
                <form method="POST">
                    <input type="hidden" name="dungeon_id" value="<?php echo $dungeonId; ?>">
                    <input type="hidden" name="action" value="start_run">
                    <button type="submit" class="px-5 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-500 hover:to-fuchsia-500 text-white font-semibold shadow-[0_0_24px_-8px_rgba(139,92,246,0.55)] transition-all">Start Dungeon Run</button>
                </form>
            <?php else: ?>
                <p class="text-amber-300">You have used all dungeon runs for today.</p>
            <?php endif; ?>
        </div>
    </div>
    <script src="/js/realm-sounds.js"></script>
    <script src="/js/dungeon-combat.js"></script>
    <script>
    (function () {
        if (window.DungeonCombat && document.getElementById('dungeon-combat-mount')) {
            DungeonCombat.initFromMount('#dungeon-combat-mount');
        }
        <?php if ($pulseRoomIndex !== null): ?>
        var pulse = <?php echo (int)$pulseRoomIndex; ?>;
        var room = document.querySelector('[data-dc-room="' + pulse + '"]');
        if (room) {
            room.classList.add('dc-room--pulse');
            setTimeout(function () { room.classList.remove('dc-room--pulse'); }, 1200);
        }
        <?php endif; ?>
    })();
    </script>
</body>
</html>
