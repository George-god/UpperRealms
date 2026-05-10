<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/SessionHelper.php';
require_once dirname(__DIR__) . '/services/SectService.php';
require_once dirname(__DIR__) . '/services/SectBaseService.php';

use Game\Helper\SessionHelper;
use Game\Service\SectBaseService;
use Game\Service\SectService;

legacy_session_start();
$userId = SessionHelper::requireLoggedIn();

$sectService = new SectService();
$baseService = new SectBaseService();
$mySect = $sectService->getSectByUserId($userId);
$baseData = $mySect ? $baseService->getBaseForSect((int)$mySect['id']) : null;

$members = $baseData['members'] ?? [];
$npcs = $baseData['npcs'] ?? [];
$buildings = $baseData['buildings'] ?? [];
$npcBonuses = $baseData['npc_bonuses'] ?? ['cultivation_speed' => 0.0, 'gold_gain' => 0.0, 'breakthrough' => 0.0];
$npcDiscipleCapacity = (int)($baseData['npc_disciple_capacity'] ?? 0);
$activeDiscipleNpcCount = (int)($baseData['active_disciple_npc_count'] ?? 0);
$realReplacements = (int)($baseData['real_joiners_replacing_npcs'] ?? 0);

$buildingIcons = [
    'sect_hall' => '🏛️',
    'training_grounds' => '⚔️',
    'alchemy_pavilion' => '⚗️',
    'forge_pavilion' => '🔨',
    'library_pavilion' => '📚',
    'inner_garden' => '🌿',
    'war_room' => '🗺️',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sect Base - Cultivation Journey</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes sect-building-hover {
            0%, 100% { transform: translateY(0); box-shadow: 0 0 24px -10px rgba(52, 211, 153, 0.25); }
            50% { transform: translateY(-2px); box-shadow: 0 0 32px -8px rgba(52, 211, 153, 0.4); }
        }
        .sect-building-card { transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; }
        .sect-building-card:hover {
            transform: scale(1.02) translateY(-2px);
            box-shadow: 0 0 36px -8px rgba(16, 185, 129, 0.35);
        }
        .sect-building-card:active { transform: scale(0.99); }
        @keyframes sect-upgrade-flash {
            0% { filter: brightness(1); }
            40% { filter: brightness(1.25); }
            100% { filter: brightness(1); }
        }
        .sect-upgrade-flash { animation: sect-upgrade-flash 0.6s ease-out; }
        #building-modal[aria-hidden="false"] { display: flex; }
    </style>
</head>
<body class="min-h-screen bg-[#0B0F1A] text-slate-200">
    <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <?php $site_brand_compact = true; require_once dirname(__DIR__) . '/includes/site_brand.php'; ?>
                <div>
                    <h1 class="bg-gradient-to-r from-emerald-300 to-cyan-300 bg-clip-text text-3xl font-bold text-transparent sm:text-4xl">Sect Base</h1>
                    <p class="text-sm text-slate-500">Grounds · halls · war council</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="sect_library.php" class="rounded-xl border border-violet-500/30 bg-[rgba(20,25,45,0.7)] px-3 py-2 text-sm text-violet-200 backdrop-blur-sm transition-all duration-200 hover:border-violet-400/50 hover:shadow-[0_0_16px_-6px_rgba(139,92,246,0.35)]">Library</a>
                <a href="sect_missions.php" class="rounded-xl border border-teal-500/30 bg-[rgba(20,25,45,0.7)] px-3 py-2 text-sm text-teal-200 backdrop-blur-sm transition-all duration-200 hover:border-teal-400/45 hover:shadow-[0_0_16px_-6px_rgba(45,212,191,0.3)]">Missions</a>
                <a href="territories.php" class="rounded-xl border border-emerald-500/30 bg-[rgba(20,25,45,0.7)] px-3 py-2 text-sm text-emerald-200 backdrop-blur-sm transition-all duration-200 hover:border-emerald-400/45">Territories</a>
                <a href="sect.php" class="rounded-xl border border-amber-500/30 bg-[rgba(20,25,45,0.7)] px-3 py-2 text-sm text-amber-200 backdrop-blur-sm transition-all duration-200 hover:border-amber-400/45">Sect</a>
                <a href="game.php" class="rounded-xl border border-indigo-500/30 bg-[rgba(20,25,45,0.7)] px-3 py-2 text-sm text-indigo-200 backdrop-blur-sm transition-all duration-200 hover:border-indigo-400/45">← Dashboard</a>
            </div>
        </div>

        <?php if (!$mySect || !$baseData): ?>
            <div class="rounded-2xl border border-slate-600/40 bg-[rgba(20,25,45,0.75)] p-10 text-center backdrop-blur-xl">
                <p class="text-lg text-slate-300">You are not part of a sect yet.</p>
                <p class="mt-2 text-sm text-slate-500">Create or join a sect first to unlock a living sect base.</p>
                <a href="sect.php" class="mt-6 inline-flex rounded-xl border border-emerald-500/35 bg-emerald-900/30 px-6 py-3 text-sm font-semibold text-emerald-200 transition-all duration-200 hover:border-emerald-400/50">Go to Sect</a>
            </div>
        <?php else: ?>
            <header class="mb-8 rounded-2xl border border-emerald-500/25 bg-[rgba(20,25,45,0.78)] p-6 shadow-[0_0_40px_-14px_rgba(52,211,153,0.25)] backdrop-blur-xl sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.25em] text-emerald-400/70">Stronghold</p>
                        <h2 class="mt-1 text-2xl font-semibold text-white sm:text-3xl"><?php echo htmlspecialchars($baseData['base']['base_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="mt-2 text-slate-400">Order of <span class="text-emerald-200/90 font-medium"><?php echo htmlspecialchars($mySect['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></p>
                    </div>
                    <div class="flex gap-8 text-right text-sm">
                        <div>
                            <p class="text-slate-500">Members</p>
                            <p class="text-xl font-bold text-white"><?php echo count($members); ?></p>
                        </div>
                        <div>
                            <p class="text-slate-500">NPC residents</p>
                            <p class="text-xl font-bold text-violet-200"><?php echo count($npcs); ?></p>
                        </div>
                    </div>
                </div>
                <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-cyan-500/20 bg-[#0B0F1A]/50 p-4">
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">NPC passive bonuses</p>
                        <p class="mt-2 text-sm text-cyan-300">+<?php echo number_format((float)$npcBonuses['cultivation_speed'] * 100, 1); ?>% cultivation</p>
                        <p class="text-sm text-amber-300">+<?php echo number_format((float)$npcBonuses['gold_gain'] * 100, 1); ?>% gold</p>
                        <p class="text-sm text-purple-300">+<?php echo number_format((float)$npcBonuses['breakthrough'] * 100, 1); ?>% breakthrough</p>
                    </div>
                    <div class="rounded-xl border border-emerald-500/20 bg-[#0B0F1A]/50 p-4">
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Disciple residency</p>
                        <p class="mt-2 text-lg font-semibold text-emerald-300"><?php echo $activeDiscipleNpcCount; ?> / <?php echo $npcDiscipleCapacity; ?> NPC disciples</p>
                        <p class="mt-2 text-xs text-slate-500">NPC disciples yield as players join.</p>
                    </div>
                    <div class="rounded-xl border border-amber-500/20 bg-[#0B0F1A]/50 p-4">
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Replacement progress</p>
                        <p class="mt-2 text-lg font-semibold text-amber-300"><?php echo $realReplacements; ?> joiners replaced NPCs</p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-black/40 ring-1 ring-amber-500/20">
                            <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-orange-400 transition-all duration-500" style="width: <?php echo $npcDiscipleCapacity > 0 ? min(100, (int)round(($realReplacements / $npcDiscipleCapacity) * 100)) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                <main class="lg:col-span-8">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h3 class="text-lg font-semibold text-emerald-200">Buildings</h3>
                        <span class="text-[10px] uppercase tracking-wider text-slate-500">Click a hall for details</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <?php foreach ($buildings as $building): ?>
                            <?php
                            $key = (string)($building['building_key'] ?? '');
                            $icon = $buildingIcons[$key] ?? '✦';
                            $bj = htmlspecialchars(json_encode([
                                'key' => $key,
                                'name' => (string)($building['building_name'] ?? ''),
                                'level' => (int)($building['level'] ?? 1),
                                'description' => (string)($building['description'] ?? ''),
                                'bonus' => (string)($building['bonus_summary'] ?? ''),
                                'icon' => $icon,
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                            ?>
                            <button
                                type="button"
                                class="sect-building-card building-open-modal text-left rounded-2xl border border-emerald-500/20 bg-[rgba(20,25,45,0.72)] p-5 backdrop-blur-xl"
                                data-building="<?php echo $bj; ?>"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-3xl drop-shadow-[0_0_12px_rgba(52,211,153,0.35)]" aria-hidden="true"><?php echo $icon; ?></span>
                                    <span class="rounded-lg border border-cyan-500/30 bg-cyan-950/40 px-2.5 py-1 text-xs font-semibold text-cyan-200">Lv. <?php echo (int)($building['level'] ?? 1); ?></span>
                                </div>
                                <h4 class="mt-3 text-lg font-semibold text-white"><?php echo htmlspecialchars($building['building_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="mt-2 line-clamp-2 text-sm text-slate-400"><?php echo htmlspecialchars($building['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mt-3 text-xs font-medium text-emerald-300/90"><?php echo htmlspecialchars($building['bonus_summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                <span class="mt-4 inline-block text-[10px] font-semibold uppercase tracking-wider text-emerald-400/80">Open briefing →</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </main>

                <aside class="lg:col-span-4">
                    <div class="sticky top-4 space-y-6">
                        <div class="rounded-2xl border border-amber-500/25 bg-[rgba(20,25,45,0.78)] p-5 backdrop-blur-xl">
                            <h3 class="mb-4 text-lg font-semibold text-amber-200">Members</h3>
                            <ul class="max-h-[min(420px,50vh)] space-y-2 overflow-y-auto pr-1">
                                <?php foreach ($members as $member): ?>
                                    <li class="flex items-center justify-between gap-2 rounded-xl border border-indigo-500/15 bg-[#0B0F1A]/45 px-3 py-2.5 transition-colors duration-200 hover:border-indigo-400/25">
                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-white"><?php echo htmlspecialchars($member['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($member['rank'] ?? $member['role'] ?? 'outer_disciple'))), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="shrink-0 text-xs font-semibold text-amber-300"><?php echo number_format((int)($member['contribution'] ?? 0)); ?></div>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (count($members) === 0): ?>
                                    <li class="text-sm text-slate-500">No members listed.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-violet-500/25 bg-[rgba(20,25,45,0.78)] p-5 backdrop-blur-xl">
                            <h3 class="mb-4 text-lg font-semibold text-violet-200">Resident NPCs</h3>
                            <ul class="max-h-64 space-y-2 overflow-y-auto text-sm">
                                <?php foreach ($npcs as $npc): ?>
                                    <li class="rounded-lg border border-violet-500/10 bg-[#0B0F1A]/40 px-3 py-2">
                                        <div class="font-medium text-slate-200"><?php echo htmlspecialchars($npc['npc_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($npc['npc_rank'] ?? 'outer_disciple'))) . ' · ' . (string)($npc['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($npc['bonus_type']) && (float)($npc['bonus_value'] ?? 0) > 0): ?>
                                            <div class="mt-1 text-xs text-violet-300">+<?php echo number_format((float)$npc['bonus_value'] * 100, 1); ?>% <?php echo htmlspecialchars(str_replace('_', ' ', (string)$npc['bonus_type']), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>

    <div
        id="building-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center p-4"
        aria-hidden="true"
        role="dialog"
        aria-labelledby="building-modal-title"
    >
        <button type="button" class="building-modal-backdrop absolute inset-0 bg-black/65 backdrop-blur-sm" aria-label="Close"></button>
        <div class="relative z-10 w-full max-w-md rounded-2xl border border-emerald-500/30 bg-[rgba(20,25,45,0.95)] p-6 shadow-[0_0_48px_-12px_rgba(52,211,153,0.35)] backdrop-blur-xl">
            <div class="flex items-start justify-between gap-3">
                <span id="building-modal-icon" class="text-4xl" aria-hidden="true"></span>
                <button type="button" class="building-modal-close rounded-lg p-1 text-slate-400 transition-colors hover:bg-white/10 hover:text-white" aria-label="Close">✕</button>
            </div>
            <h2 id="building-modal-title" class="mt-2 text-xl font-semibold text-white"></h2>
            <p id="building-modal-level" class="mt-1 text-sm text-cyan-300"></p>
            <p id="building-modal-desc" class="mt-4 text-sm leading-relaxed text-slate-400"></p>
            <div class="mt-4 rounded-xl border border-emerald-500/20 bg-emerald-950/20 p-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-400/80">Bonuses</p>
                <p id="building-modal-bonus" class="mt-1 text-sm text-emerald-200/90"></p>
            </div>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                <button type="button" id="building-modal-upgrade" class="flex-1 rounded-xl border border-amber-500/40 bg-gradient-to-r from-amber-700/70 to-orange-700/60 py-3 text-sm font-semibold text-white shadow-[0_0_20px_-8px_rgba(251,191,36,0.4)] transition-all duration-200 hover:from-amber-600 hover:to-orange-600">
                    Plan upgrade
                </button>
                <button type="button" class="building-modal-close flex-1 rounded-xl border border-slate-600/50 bg-slate-900/50 py-3 text-sm font-medium text-slate-300 transition-all duration-200 hover:border-slate-500">
                    Close
                </button>
            </div>
            <p id="building-modal-upgrade-hint" class="mt-3 hidden text-center text-xs text-amber-200/80"></p>
        </div>
    </div>

    <script>
    (function () {
        var modal = document.getElementById('building-modal');
        if (!modal) return;
        var titleEl = document.getElementById('building-modal-title');
        var levelEl = document.getElementById('building-modal-level');
        var descEl = document.getElementById('building-modal-desc');
        var bonusEl = document.getElementById('building-modal-bonus');
        var iconEl = document.getElementById('building-modal-icon');
        var upgradeBtn = document.getElementById('building-modal-upgrade');
        var upgradeHint = document.getElementById('building-modal-upgrade-hint');

        var lastBuildingBtn = null;

        function openModal(data, sourceBtn) {
            lastBuildingBtn = sourceBtn || null;
            titleEl.textContent = data.name || '';
            levelEl.textContent = 'Structure level ' + (data.level || 1);
            descEl.textContent = data.description || '';
            bonusEl.textContent = data.bonus || '—';
            iconEl.textContent = data.icon || '✦';
            upgradeHint.classList.add('hidden');
            upgradeHint.textContent = '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.building-open-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                try {
                    var raw = btn.getAttribute('data-building');
                    openModal(JSON.parse(raw), btn);
                } catch (e) {
                    console.error(e);
                }
            });
        });

        modal.querySelectorAll('.building-modal-close, .building-modal-backdrop').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
        });

        if (upgradeBtn) {
            upgradeBtn.addEventListener('click', function () {
                if (lastBuildingBtn) {
                    lastBuildingBtn.classList.remove('sect-upgrade-flash');
                    void lastBuildingBtn.offsetWidth;
                    lastBuildingBtn.classList.add('sect-upgrade-flash');
                    window.setTimeout(function () {
                        if (lastBuildingBtn) lastBuildingBtn.classList.remove('sect-upgrade-flash');
                    }, 600);
                }
                upgradeHint.textContent = 'Blueprint logged. Fund upgrades through sect missions and treasury — construction queue is narrative for now.';
                upgradeHint.classList.remove('hidden');
            });
        }
    })();
    </script>
</body>
</html>
