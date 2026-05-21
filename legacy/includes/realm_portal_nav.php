<?php

declare(strict_types=1);

/**
 * Sticky realm navigation for legacy pages (Laravel /game/classic/...).
 * Uses Tailwind utility classes; pages should load Tailwind (CDN or build).
 */
$realmPortalHubUrl = '/game';
$realmPortalHere = strtolower(basename(parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));

$realmPortalLink = static function (string $page, string $label, string $icon, string $here): void {
    $page = str_ends_with($page, '.php') ? $page : $page.'.php';
    $active = $here === strtolower($page);
    $cls = $active
        ? 'border-amber-400/45 bg-amber-950/35 text-amber-100 shadow-[0_0_14px_-6px_rgba(245,158,11,0.45)]'
        : 'border-indigo-500/20 bg-[rgba(20,25,45,0.55)] text-indigo-100/95 hover:border-indigo-400/40 hover:shadow-[0_0_18px_-10px_rgba(99,102,241,0.35)]';

    echo '<a href="'.htmlspecialchars($page, ENT_QUOTES, 'UTF-8').'" class="realm-portal-quick inline-flex shrink-0 items-center gap-1 rounded-lg border px-2 py-1 text-[11px] font-semibold backdrop-blur-sm transition sm:text-xs '.$cls.'">';
    echo '<span aria-hidden="true">'.$icon.'</span> '.htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    echo '</a>';
};

?>
<nav class="realm-portal-nav sticky top-0 z-[100] mb-4 rounded-2xl border border-indigo-500/20 bg-[rgba(12,16,28,0.88)] px-3 py-2.5 shadow-[0_0_32px_-14px_rgba(99,102,241,0.35)] backdrop-blur-xl" aria-label="Realm navigation">
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?php echo htmlspecialchars($realmPortalHubUrl, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-1 rounded-xl border border-amber-500/35 bg-amber-950/30 px-2.5 py-1.5 text-xs font-bold text-amber-100 shadow-[0_0_16px_-8px_rgba(245,158,11,0.4)] transition hover:border-amber-400/55 sm:text-sm">
            <span aria-hidden="true">🏯</span> <?php echo htmlspecialchars('Home', ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <a href="<?php echo htmlspecialchars($realmPortalHubUrl, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-1 rounded-xl border border-indigo-500/30 bg-[rgba(20,25,45,0.65)] px-2.5 py-1.5 text-xs font-bold text-indigo-100 transition hover:border-indigo-400/50 sm:text-sm">
            <span aria-hidden="true">✦</span> <?php echo htmlspecialchars('Dashboard', ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <a href="game.php" class="inline-flex items-center gap-1 rounded-xl border border-violet-500/25 bg-[rgba(20,25,45,0.5)] px-2.5 py-1.5 text-xs font-semibold text-violet-100 transition hover:border-violet-400/45 sm:text-sm">
            <span aria-hidden="true">☯️</span> <?php echo htmlspecialchars('Spirit hall', ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <span class="mx-1 hidden h-4 w-px bg-indigo-500/25 sm:inline" aria-hidden="true"></span>
        <div class="flex max-w-full flex-1 flex-wrap items-center gap-1.5">
            <?php
            $realmPortalLink('world_map.php', 'Map', '🧭', $realmPortalHere);
            $realmPortalLink('battles.php', 'Combat', '⚔️', $realmPortalHere);
            $realmPortalLink('sect.php', 'Sect', '🏛️', $realmPortalHere);
            $realmPortalLink('inventory.php', 'Inventory', '🎒', $realmPortalHere);
            $realmPortalLink('marketplace.php', 'Market', '🏪', $realmPortalHere);
            $realmPortalLink('artifacts.php', 'Artifacts', '✨', $realmPortalHere);
            $realmPortalLink('bloodline.php', 'Bloodline', '🩸', $realmPortalHere);
            ?>
        </div>
    </div>
</nav>
