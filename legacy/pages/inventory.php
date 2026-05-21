<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/services/ItemService.php';
require_once dirname(__DIR__) . '/services/StatCalculator.php';
require_once dirname(__DIR__) . '/services/CultivationManualService.php';

use Game\Service\CultivationManualService;
use Game\Service\ItemService;
use Game\Service\StatCalculator;

legacy_session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '' || $_SESSION['user_id'] === null) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$itemService = new ItemService();
$manualService = new CultivationManualService();

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'equip' && !empty($_POST['inventory_id'])) {
        $res = $itemService->equipItem($userId, (int)$_POST['inventory_id']);
        $message = $res['success'] ? $res['message'] : null;
        $error = $res['success'] ? null : $res['message'];
    } elseif (isset($_POST['action']) && $_POST['action'] === 'unequip' && !empty($_POST['slot'])) {
        $res = $itemService->unequipItem($userId, (string)$_POST['slot']);
        $message = $res['success'] ? $res['message'] : null;
        $error = $res['success'] ? null : $res['message'];
    }
    if ($message || $error) {
        header('Location: inventory.php?msg=' . urlencode($message ?: '') . ($error ? '&err=' . urlencode($error) : ''));
        exit;
    }
}

$msg = $_GET['msg'] ?? null;
$err = $_GET['err'] ?? null;

$inventory = $itemService->getUserInventory($userId, true);
$equipment = $itemService->getUserEquipment($userId);
$statCalculator = new StatCalculator();
$calculatedStats = $statCalculator->calculateFinalStats($userId);
$displayStats = $calculatedStats['final'];
$inventoryIdToSlot = [];
$slotLabel = ['weapon' => 'weapon', 'armor' => 'armor', 'accessory_1' => 'accessory', 'accessory_2' => 'accessory'];
if ($equipment['weapon_id']) $inventoryIdToSlot[$equipment['weapon_id']] = 'weapon';
if ($equipment['armor_id']) $inventoryIdToSlot[$equipment['armor_id']] = 'armor';
if ($equipment['accessory_1_id']) $inventoryIdToSlot[$equipment['accessory_1_id']] = 'accessory_1';
if ($equipment['accessory_2_id']) $inventoryIdToSlot[$equipment['accessory_2_id']] = 'accessory_2';

$manualInventory = $manualService->getManualsForInventory($userId);
$cultivationManuals = array_merge($manualInventory['owned'] ?? [], $manualInventory['borrowed'] ?? []);
$manualTablesAvailable = (bool) ($manualInventory['tables_available'] ?? false);

$formatManualSource = static fn(string $source): string => ucwords(str_replace('_', ' ', $source));
$rarityBadgeClass = static function (string $rarity): string {
    return match (strtolower($rarity)) {
        'mythic' => 'bg-fuchsia-500/20 border-fuchsia-500/40 text-fuchsia-300',
        'legendary' => 'bg-amber-500/20 border-amber-500/40 text-amber-300',
        'epic' => 'bg-violet-500/20 border-violet-500/40 text-violet-300',
        'rare' => 'bg-blue-500/20 border-blue-500/40 text-blue-300',
        default => 'bg-gray-500/20 border-gray-500/40 text-gray-300',
    };
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Cultivation Journey</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-900 via-slate-900 to-gray-900 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <?php require_once dirname(__DIR__) . '/includes/realm_portal_nav.php'; ?>
        <div class="flex justify-between items-center mb-8 flex-wrap gap-4">
            <div class="flex items-center gap-4 flex-wrap">
                <?php $site_brand_compact = true; require_once dirname(__DIR__) . '/includes/site_brand.php'; ?>
                <h1 class="text-4xl font-bold bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">
                    🎒 Inventory
                </h1>
            </div>
            <div class="flex gap-4 flex-wrap">
                <a href="cultivation_manuals.php" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg border border-violet-500/30 text-violet-300 transition-all">Cultivation Manuals</a>
                <a href="equipment.php" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg border border-violet-500/30 text-violet-300 transition-all">Equipment</a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="mb-4 p-3 bg-green-900/30 border border-green-500/50 rounded-lg text-green-300">
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="mb-4 p-3 bg-red-900/30 border border-red-500/50 rounded-lg text-red-300">
                <?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="mb-6 bg-gray-800/90 backdrop-blur-lg border border-cyan-500/30 rounded-xl p-4">
            <h2 class="text-lg font-semibold text-cyan-300 mb-2">Stats (with equipment)</h2>
            <div class="flex flex-wrap gap-4 text-gray-200">
                <span>ATK: <strong id="stat-attack"><?php echo (int)$displayStats['attack']; ?></strong></span>
                <span>DEF: <strong id="stat-defense"><?php echo (int)$displayStats['defense']; ?></strong></span>
                <span>Max Chi: <strong id="stat-max-chi"><?php echo (int)$displayStats['max_chi']; ?></strong></span>
            </div>
        </div>

        <div class="mb-6 bg-gray-800/90 backdrop-blur-lg border border-violet-500/30 rounded-xl p-6">
            <div class="flex justify-between items-center gap-4 flex-wrap mb-4">
                <h2 class="text-xl font-semibold text-violet-300">📜 Cultivation Manuals</h2>
                <a href="cultivation_manuals.php" class="text-sm text-violet-300 hover:text-violet-200 underline">Manage bonuses &amp; crafting →</a>
            </div>
            <p class="text-gray-400 text-sm mb-4">Manuals are kept separately from gear and consumables. Passive bonuses apply when your Dao path matches the manual.</p>
            <?php if (! $manualTablesAvailable): ?>
                <p class="text-amber-300/90 text-sm">Cultivation manual tables are not set up on this server yet. Ask an admin to run migrations.</p>
            <?php elseif (empty($cultivationManuals)): ?>
                <p class="text-gray-400 text-sm">No cultivation manuals yet. Explore ancient ruins, clear dungeons, or defeat world bosses to find them.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($cultivationManuals as $manual): ?>
                        <?php
                        $rarity = strtolower((string) ($manual['rarity'] ?? 'common'));
                        $holder = (string) ($manual['holder'] ?? 'owned');
                        $isBorrowed = $holder === 'borrowed';
                        $daoOk = ! empty($manual['dao_applicable']);
                        ?>
                        <div class="bg-gray-900/80 border border-violet-500/20 rounded-lg p-4">
                            <div class="flex items-start justify-between gap-2 flex-wrap">
                                <div class="font-semibold text-white"><?php echo htmlspecialchars((string) ($manual['name'] ?? 'Manual'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <span class="px-2 py-0.5 rounded text-xs font-semibold border <?php echo $rarityBadgeClass($rarity); ?>"><?php echo htmlspecialchars(ucfirst($rarity), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="text-sm text-gray-400 mt-1">Cultivation manual</div>
                            <?php if ($isBorrowed): ?>
                                <div class="text-xs text-cyan-300 mt-1">Borrowed from <?php echo htmlspecialchars((string) ($manual['sect_name'] ?? 'sect'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php else: ?>
                                <div class="text-xs text-gray-500 mt-1">From <?php echo htmlspecialchars($formatManualSource((string) ($manual['acquired_from'] ?? 'unknown')), ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-400 mt-2">
                                Dao: <?php echo htmlspecialchars((string) ($manual['dao_element_label'] ?? 'Any'), ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo htmlspecialchars((string) ($manual['dao_alignment_label'] ?? 'Universal'), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="mt-2 text-xs <?php echo $daoOk ? 'text-emerald-300' : 'text-amber-300'; ?>">
                                <?php echo $daoOk ? '✓ Bonuses active for your Dao path' : '○ Bonuses inactive (Dao mismatch)'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-gray-800/90 backdrop-blur-lg border border-emerald-500/30 rounded-xl p-6">
            <h2 class="text-xl font-semibold text-emerald-300 mb-4">All Items</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($inventory as $row): ?>
                    <?php
                    $template = $row['template'] ?? null;
                    if (!$template) continue;
                    $equippable = in_array($template['type'], ['weapon', 'armor', 'accessory'], true);
                    $equipped = !empty($row['is_equipped']);
                    ?>
                    <div class="bg-gray-900/80 border border-gray-600 rounded-lg p-4">
                        <div class="flex items-center gap-2">
                            <div class="font-semibold text-white"><?php echo htmlspecialchars($template['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if (($template['rarity'] ?? 'common') === 'legendary'): ?>
                                <span class="px-2 py-0.5 rounded bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-semibold">Legendary</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-400 capitalize"><?php echo htmlspecialchars($template['type'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-sm text-gray-300 mt-2">
                            <?php if ((int)($template['attack_bonus'] ?? 0) > 0): ?>+<?php echo $template['attack_bonus']; ?> ATK <?php endif; ?>
                            <?php if ((int)($template['defense_bonus'] ?? 0) > 0): ?>+<?php echo $template['defense_bonus']; ?> DEF <?php endif; ?>
                            <?php if ((int)($template['hp_bonus'] ?? 0) > 0): ?>+<?php echo $template['hp_bonus']; ?> HP <?php endif; ?>
                        </div>
                        <div class="mt-2 text-gray-400 text-xs">Qty: <?php echo (int)$row['quantity']; ?></div>
                        <?php if ($equipped): ?>
                            <?php $slot = $inventoryIdToSlot[(int)$row['id']] ?? null; ?>
                            <div class="mt-3 text-cyan-300 text-sm">Equipped<?php echo $slot ? ' (' . ($slotLabel[$slot] ?? $slot) . ')' : ''; ?></div>
                            <?php if ($slot): ?>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="action" value="unequip">
                                <input type="hidden" name="slot" value="<?php echo htmlspecialchars($slot, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded text-sm">Unequip</button>
                            </form>
                            <?php endif; ?>
                        <?php elseif ($equippable): ?>
                            <button type="button" class="mt-3 px-3 py-1 bg-emerald-600 hover:bg-emerald-500 rounded text-sm text-white equip-btn" data-inventory-id="<?php echo (int)$row['id']; ?>">Equip</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($inventory)): ?>
                <p class="text-gray-400">Your inventory is empty. Use the World Map to explore—hostile encounters can drop loot.</p>
            <?php endif; ?>
        </div>
    </div>
    <script src="../inventory.js"></script>
</body>
</html>
