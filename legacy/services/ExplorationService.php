<?php
declare(strict_types=1);

namespace Game\Service;

require_once __DIR__ . '/CultivationManualService.php';
require_once __DIR__ . '/PvEBattleService.php';
require_once __DIR__ . '/StatCalculator.php';

use Game\Config\Database;
use PDO;
use PDOException;

/**
 * World map exploration. Pacing matches config/game.php when Laravel is loaded.
 */
class ExplorationService
{
    private const RUNE_FRAGMENT_TEMPLATE_ID = 56;

    private function exploreMinIntervalSeconds(): int
    {
        if (function_exists('config')) {
            try {
                return (int) config('game.exploration.min_interval_seconds', 5);
            } catch (\Throwable) {
            }
        }
        $v = getenv('EXPLORATION_MIN_INTERVAL_SECONDS');

        return ($v !== false && $v !== '') ? (int) $v : 5;
    }

    private function exploreBurstLimit(): int
    {
        if (function_exists('config')) {
            try {
                return (int) config('game.exploration.burst_limit', 10);
            } catch (\Throwable) {
            }
        }
        $v = getenv('EXPLORATION_BURST_LIMIT');

        return ($v !== false && $v !== '') ? (int) $v : 10;
    }

    private function exploreLongRestSeconds(): int
    {
        if (function_exists('config')) {
            try {
                return (int) config('game.exploration.long_rest_seconds', 1800);
            } catch (\Throwable) {
            }
        }
        $v = getenv('EXPLORATION_LONG_REST_SECONDS');
        if ($v !== false && $v !== '') {
            return (int) $v;
        }
        $env = getenv('APP_ENV') ?: 'production';

        return in_array($env, ['local', 'testing'], true) ? 5 : 1800;
    }

    /** @var array<int, bool> */
    private static array $burstColumnsByDb = [];

    private function getDbSignature(PDO $db): int
    {
        return spl_object_id($db);
    }

    private function userLocationHasBurstColumns(PDO $db): bool
    {
        $sig = $this->getDbSignature($db);
        if (array_key_exists($sig, self::$burstColumnsByDb)) {
            return self::$burstColumnsByDb[$sig];
        }
        try {
            $stmt = $db->query("SHOW COLUMNS FROM user_location LIKE 'explore_burst_count'");
            self::$burstColumnsByDb[$sig] = (bool)$stmt->fetch();
        } catch (PDOException $e) {
            self::$burstColumnsByDb[$sig] = false;
        }
        return self::$burstColumnsByDb[$sig];
    }

    /**
     * Fix inconsistent rows (e.g. long-rest timer active while burst count is still mid-cycle).
     */
    private function repairExplorePacingState(PDO $db, int $userId): void
    {
        if (! $this->userLocationHasBurstColumns($db)) {
            return;
        }
        try {
            $stmt = $db->prepare('SELECT explore_burst_count, explore_blocked_until FROM user_location WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (! $row) {
                return;
            }
            $used = (int) ($row['explore_burst_count'] ?? 0);
            $blocked = $row['explore_blocked_until'] ?? null;
            if ($blocked === null || $blocked === '') {
                return;
            }
            $blockedTs = strtotime((string) $blocked);
            if ($blockedTs === false || $blockedTs <= time()) {
                $db->prepare('UPDATE user_location SET explore_blocked_until = NULL WHERE user_id = ?')->execute([$userId]);

                return;
            }
            if ($used > 0) {
                $db->prepare('UPDATE user_location SET explore_blocked_until = NULL WHERE user_id = ?')->execute([$userId]);
            }
        } catch (PDOException $e) {
            error_log('ExplorationService::repairExplorePacingState '.$e->getMessage());
        }
    }

    /**
     * @return array{used: int, max: int, in_long_rest: bool}
     */
    private function getExploreBurstPublicState(PDO $db, int $userId): array
    {
        $max = $this->exploreBurstLimit();
        if (! $this->userLocationHasBurstColumns($db)) {
            return ['used' => 0, 'max' => $max, 'in_long_rest' => false];
        }
        try {
            $this->repairExplorePacingState($db, $userId);
            $stmt = $db->prepare('SELECT explore_burst_count, explore_blocked_until FROM user_location WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (! $row) {
                return ['used' => 0, 'max' => $max, 'in_long_rest' => false];
            }
            $blocked = $row['explore_blocked_until'] ?? null;
            $blockedTs = $blocked ? strtotime((string) $blocked) : false;
            $inLong = $blocked && $blockedTs !== false && $blockedTs > time();
            $used = (int) ($row['explore_burst_count'] ?? 0);
            if ($inLong && $used !== 0) {
                try {
                    $db->prepare('UPDATE user_location SET explore_burst_count = 0 WHERE user_id = ?')->execute([$userId]);
                    $used = 0;
                } catch (PDOException $repairEx) {
                    error_log('ExplorationService::getExploreBurstPublicState repair: '.$repairEx->getMessage());
                    $used = 0;
                }
            }

            return [
                'used' => $used,
                'max' => $max,
                'in_long_rest' => (bool) $inLong,
            ];
        } catch (PDOException $e) {
            error_log('ExplorationService::getExploreBurstPublicState '.$e->getMessage());

            return ['used' => 0, 'max' => $max, 'in_long_rest' => false];
        }
    }

    /**
     * @return array{remaining: int, kind: 'none'|'interval'|'long_rest'}
     */
    public function getExploreCooldownMeta(int $userId): array
    {
        try {
            $db = Database::getConnection();
            $hasBurst = $this->userLocationHasBurstColumns($db);
            if ($hasBurst) {
                $this->repairExplorePacingState($db, $userId);
            }
            $stmt = $db->prepare(
                $hasBurst
                    ? 'SELECT last_explore_at, explore_blocked_until FROM user_location WHERE user_id = ? LIMIT 1'
                    : 'SELECT last_explore_at FROM user_location WHERE user_id = ? LIMIT 1'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (! $row) {
                return ['remaining' => 0, 'kind' => 'none'];
            }
            if ($hasBurst && ! empty($row['explore_blocked_until'])) {
                $blockTs = strtotime((string) $row['explore_blocked_until']);
                if ($blockTs !== false && $blockTs > time()) {
                    return ['remaining' => max(0, $blockTs - time()), 'kind' => 'long_rest'];
                }
            }
            $lastAt = $row['last_explore_at'] ?? null;
            if ($lastAt === null || $lastAt === '') {
                return ['remaining' => 0, 'kind' => 'none'];
            }
            $elapsed = time() - (int) strtotime((string) $lastAt);
            $remaining = max(0, $this->exploreMinIntervalSeconds() - $elapsed);

            return [
                'remaining' => $remaining,
                'kind' => $remaining > 0 ? 'interval' : 'none',
            ];
        } catch (PDOException $e) {
            error_log('ExplorationService::getExploreCooldownMeta '.$e->getMessage());

            return ['remaining' => 0, 'kind' => 'none'];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function attachExploreClientMeta(int $userId, array $payload): array
    {
        try {
            $db = Database::getConnection();
            $cooldown = $this->getExploreCooldownMeta($userId);
            $burst = $this->getExploreBurstPublicState($db, $userId);
            $payload['cooldown_remaining'] = $cooldown['remaining'];
            $payload['explore_cooldown_kind'] = $cooldown['kind'];
            $payload['explore_burst_used'] = $burst['used'];
            $payload['explore_burst_max'] = $burst['max'];
            $payload['explore_in_long_rest'] = $burst['in_long_rest'];
        } catch (\Throwable $e) {
            error_log('ExplorationService::attachExploreClientMeta ' . $e->getMessage());
        }
        return $payload;
    }

    public function getRegionsForUser(int $userId): array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT realm_id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $userRealmId = (int)($stmt->fetchColumn() ?: 1);

            $burstExtra = $this->userLocationHasBurstColumns($db)
                ? ', ul.explore_burst_count, ul.explore_blocked_until'
                : '';
            $stmt = $db->prepare("
                SELECT ul.region_id, ul.last_explore_at{$burstExtra}, r.name, r.resource_type, r.exploration_encounters, r.hidden_dungeon_chance
                FROM user_location ul
                JOIN world_regions r ON r.id = ul.region_id
                WHERE ul.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmt = $db->query("
                SELECT wr.id, wr.name, wr.difficulty, wr.description, wr.min_realm_id, wr.resource_type, wr.exploration_encounters, wr.hidden_dungeon_chance,
                       r.name AS min_realm_name
                FROM world_regions wr
                LEFT JOIN realms r ON r.id = wr.min_realm_id
                ORDER BY wr.min_realm_id ASC, wr.difficulty ASC, wr.id ASC
            ");
            $regions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $cooldown = $this->getExploreCooldownMeta($userId);
            $burst = $this->getExploreBurstPublicState($db, $userId);

            foreach ($regions as &$region) {
                $region['locked'] = $userRealmId < (int)$region['min_realm_id'];
                $region['is_current'] = $location && (int)$location['region_id'] === (int)$region['id'];
            }
            unset($region);

            return [
                'regions' => $regions,
                'user_realm_id' => $userRealmId,
                'current_location' => $location,
                'cooldown_remaining' => $cooldown['remaining'],
                'explore_cooldown_kind' => $cooldown['kind'],
                'explore_burst_used' => $burst['used'],
                'explore_burst_max' => $burst['max'],
                'explore_in_long_rest' => $burst['in_long_rest'],
            ];
        } catch (PDOException $e) {
            error_log('ExplorationService::getRegionsForUser ' . $e->getMessage());
            return [
                'regions' => [],
                'user_realm_id' => 1,
                'current_location' => null,
                'cooldown_remaining' => 0,
                'explore_cooldown_kind' => 'none',
                'explore_burst_used' => 0,
                'explore_burst_max' => $this->exploreBurstLimit(),
                'explore_in_long_rest' => false,
            ];
        }
    }

    public function getCooldownRemaining(int $userId): int
    {
        return $this->getExploreCooldownMeta($userId)['remaining'];
    }

    public function exploreRegion(int $userId, int $regionId): array
    {
        $region = $this->getRegionById($regionId);
        if (!$region) {
            return $this->attachExploreClientMeta($userId, ['success' => false, 'message' => 'Region not found.']);
        }

        $userRealmId = $this->getUserRealmId($userId);
        if ($userRealmId < (int)$region['min_realm_id']) {
            return $this->attachExploreClientMeta($userId, ['success' => false, 'message' => 'Your realm is too low for this region.']);
        }

        $cooldownRemaining = $this->reserveExploration($userId, $regionId);
        if ($cooldownRemaining > 0) {
            return $this->attachExploreClientMeta($userId, [
                'success' => false,
                'message' => 'You must wait before exploring again.',
                'cooldown_remaining' => $cooldownRemaining,
            ]);
        }

        $dungeonChance = max(0.0, (float)($region['hidden_dungeon_chance'] ?? 1.0));
        $dungeonRoll = mt_rand(1, 10000) / 100.0;
        if ($dungeonRoll <= $dungeonChance) {
            return $this->attachExploreClientMeta($userId, $this->handleDungeonDiscovery($userId, $region));
        }

        $roll = mt_rand(1, 100);
        if ($roll <= 40) {
            return $this->attachExploreClientMeta($userId, $this->handleEncounter($userId, $region));
        }
        if ($roll <= 65) {
            return $this->attachExploreClientMeta($userId, $this->handleHerbsFound($userId, $region));
        }
        if ($roll <= 85) {
            return $this->attachExploreClientMeta($userId, $this->handleMaterialsFound($userId, $region));
        }
        if ($roll <= 95) {
            return $this->attachExploreClientMeta($userId, [
                'success' => true,
                'event_type' => 'nothing',
                'message' => 'You searched the area but found nothing of value.',
                'region_name' => (string)$region['name'],
            ]);
        }
        return $this->attachExploreClientMeta($userId, $this->handleRareDiscovery($userId, $region));
    }

    private function handleDungeonDiscovery(int $userId, array $region): array
    {
        $dungeon = $this->pickDungeonForRegion((int)$region['id']);
        if (!$dungeon) {
            return $this->handleRareDiscovery($userId, $region);
        }

        $locked = $this->getUserRealmId($userId) < (int)$dungeon['min_realm_id'];
        return [
            'success' => true,
            'event_type' => 'dungeon_discovery',
            'message' => 'A hidden dungeon entrance revealed itself!',
            'region_name' => (string)$region['name'],
            'resource_type' => (string)($region['resource_type'] ?? ''),
            'exploration_encounters' => (string)($region['exploration_encounters'] ?? ''),
            'data' => [
                'dungeon' => [
                    'id' => (int)$dungeon['id'],
                    'name' => (string)$dungeon['name'],
                    'difficulty' => (int)$dungeon['difficulty'],
                    'boss_name' => (string)$dungeon['boss_name'],
                    'locked' => $locked,
                    'min_realm_id' => (int)$dungeon['min_realm_id'],
                    'min_realm_name' => $this->formatRealmDisplayLabel(
                        (string)($dungeon['min_realm_name'] ?? ''),
                        (int)$dungeon['min_realm_id']
                    ),
                ],
            ],
        ];
    }

    private function handleEncounter(int $userId, array $region): array
    {
        $npcId = $this->pickEncounterNpcId($userId, (int)$region['difficulty']);
        if ($npcId === null) {
            return [
                'success' => true,
                'event_type' => 'nothing',
                'message' => 'You sensed danger nearby, but nothing emerged.',
                'region_name' => (string)$region['name'],
            ];
        }

        $battleService = new PvEBattleService();
        $result = $battleService->simulateBattle($userId, $npcId);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['error'] ?? 'Exploration encounter failed.'];
        }

        $payload = $this->finalizeEncounter($userId, $result);
        return [
            'success' => true,
            'event_type' => 'encounter',
            'message' => $payload['winner'] === 'user'
                ? 'You encountered an enemy and won the battle.'
                : 'You encountered an enemy but were forced to retreat.',
            'region_name' => (string)$region['name'],
            'resource_type' => (string)($region['resource_type'] ?? ''),
            'exploration_encounters' => (string)($region['exploration_encounters'] ?? ''),
            'data' => $payload,
        ];
    }

    private function handleHerbsFound(int $userId, array $region): array
    {
        $template = $this->pickRandomTemplateByType('herb');
        if (!$template) {
            return [
                'success' => true,
                'event_type' => 'nothing',
                'message' => 'You searched for herbs but found none.',
                'region_name' => (string)$region['name'],
            ];
        }

        $itemService = new ItemService();
        $add = $itemService->addItemToInventory($userId, (int)$template['id'], 1);
        if (!$add['success']) {
            return ['success' => false, 'message' => $add['message'] ?? 'Could not add herb to inventory.'];
        }

        return [
            'success' => true,
            'event_type' => 'herbs',
            'message' => 'You gathered herbs while exploring.',
            'region_name' => (string)$region['name'],
            'resource_type' => (string)($region['resource_type'] ?? ''),
            'exploration_encounters' => (string)($region['exploration_encounters'] ?? ''),
            'data' => [
                'item' => [
                    'id' => (int)$template['id'],
                    'name' => (string)$template['name'],
                    'type' => (string)$template['type'],
                    'quantity' => 1,
                ],
            ],
        ];
    }

    private function handleMaterialsFound(int $userId, array $region): array
    {
        $template = $this->pickMaterialTemplateForDifficulty((int)$region['difficulty']);
        if (!$template) {
            return [
                'success' => true,
                'event_type' => 'nothing',
                'message' => 'You inspected the ground but found no useful materials.',
                'region_name' => (string)$region['name'],
            ];
        }

        $itemService = new ItemService();
        $add = $itemService->addItemToInventory($userId, (int)$template['id'], 1);
        if (!$add['success']) {
            return ['success' => false, 'message' => $add['message'] ?? 'Could not add material to inventory.'];
        }

        return [
            'success' => true,
            'event_type' => 'materials',
            'message' => 'You uncovered useful crafting materials.',
            'region_name' => (string)$region['name'],
            'resource_type' => (string)($region['resource_type'] ?? ''),
            'exploration_encounters' => (string)($region['exploration_encounters'] ?? ''),
            'data' => [
                'item' => [
                    'id' => (int)$template['id'],
                    'name' => (string)$template['name'],
                    'type' => (string)$template['type'],
                    'quantity' => 1,
                ],
            ],
        ];
    }

    private function handleRareDiscovery(int $userId, array $region): array
    {
        $manualService = new CultivationManualService();
        $manual = $manualService->awardAncientRuinsManual($userId, $region);
        if ($manual !== null) {
            return [
                'success' => true,
                'event_type' => 'manual_discovery',
                'message' => 'Ancient ruins yielded a forgotten cultivation manual.',
                'region_name' => (string)$region['name'],
                'resource_type' => (string)($region['resource_type'] ?? ''),
                'exploration_encounters' => (string)($region['exploration_encounters'] ?? ''),
                'data' => [
                    'manual' => [
                        'id' => (int)$manual['id'],
                        'name' => (string)$manual['name'],
                        'rarity' => (string)$manual['rarity'],
                    ],
                ],
            ];
        }

        $template = $this->getTemplateById(self::RUNE_FRAGMENT_TEMPLATE_ID);
        $quantity = 1;
        if (!$template) {
            $template = $this->pickMaterialTemplateForDifficulty(max(3, (int)$region['difficulty']));
            $quantity = 2;
        }
        if (!$template) {
            return [
                'success' => true,
                'event_type' => 'nothing',
                'message' => 'You sensed a hidden treasure, but it slipped away.',
                'region_name' => (string)$region['name'],
            ];
        }

        $itemService = new ItemService();
        $add = $itemService->addItemToInventory($userId, (int)$template['id'], $quantity);
        if (!$add['success']) {
            return ['success' => false, 'message' => $add['message'] ?? 'Could not add rare discovery to inventory.'];
        }

        return [
            'success' => true,
            'event_type' => 'rare_discovery',
            'message' => 'Rare discovery! You found something uncommon.',
            'region_name' => (string)$region['name'],
            'resource_type' => (string)($region['resource_type'] ?? ''),
            'exploration_encounters' => (string)($region['exploration_encounters'] ?? ''),
            'data' => [
                'item' => [
                    'id' => (int)$template['id'],
                    'name' => (string)$template['name'],
                    'type' => (string)$template['type'],
                    'quantity' => $quantity,
                ],
            ],
        ];
    }

    private function finalizeEncounter(int $userId, array $result): array
    {
        $chiReward = (int)$result['chi_reward'];
        $userChiAfter = (int)$result['user_chi_after'];

        $statCalc = new StatCalculator();
        $finalStats = $statCalc->calculateFinalStats($userId);
        $userMaxChi = (int)$finalStats['final']['max_chi'];

        if ($result['winner'] === 'user' && $chiReward > 0) {
            $newChi = min($userMaxChi, max(0, $userChiAfter + $chiReward));
            $db = Database::getConnection();
            $db->prepare('UPDATE users SET chi = GREATEST(0, LEAST(?, ?)) WHERE id = ?')
                ->execute([$userMaxChi, $newChi, $userId]);
            $userChiAfter = $newChi;
        }

        $db = Database::getConnection();
        $db->prepare('UPDATE users SET active_scroll_type = NULL WHERE id = ?')->execute([$userId]);

        return [
            'winner' => $result['winner'],
            'battle_log' => $result['battle_log'],
            'user_chi_after' => $userChiAfter,
            'user_max_chi' => $userMaxChi,
            'npc_hp_max' => (int)$result['npc_hp_max'],
            'chi_reward' => $chiReward,
            'npc_name' => $result['npc_name'],
            'dropped_item' => $result['dropped_item'] ?? null,
            'herb_dropped' => $result['herb_dropped'] ?? null,
            'material_dropped' => $result['material_dropped'] ?? null,
            'rune_fragment_dropped' => $result['rune_fragment_dropped'] ?? null,
            'gold_gained' => (int)($result['gold_gained'] ?? 0),
            'spirit_stone_gained' => (int)($result['spirit_stone_gained'] ?? 0),
        ];
    }

    private function reserveExploration(int $userId, int $regionId): int
    {
        try {
            $db = Database::getConnection();
            $hasBurst = $this->userLocationHasBurstColumns($db);
            if ($hasBurst) {
                $this->repairExplorePacingState($db, $userId);
            }
            $db->beginTransaction();

            $selectCols = $hasBurst
                ? 'region_id, last_explore_at, explore_burst_count, explore_blocked_until'
                : 'region_id, last_explore_at';
            $stmt = $db->prepare("SELECT {$selectCols} FROM user_location WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($hasBurst) {
                $blocked = $row['explore_blocked_until'] ?? null;
                $blockedTs = $blocked ? strtotime((string) $blocked) : false;
                if ($blocked && $blockedTs !== false && $blockedTs > time()) {
                    $db->rollBack();
                    return max(0, $blockedTs - time());
                }
            }

            if ($row && !empty($row['last_explore_at'])) {
                $elapsed = time() - (int)strtotime((string)$row['last_explore_at']);
                $remaining = max(0, $this->exploreMinIntervalSeconds() - $elapsed);
                if ($remaining > 0) {
                    $db->rollBack();
                    return $remaining;
                }
            }

            if ($hasBurst) {
                $burst = $row ? (int)($row['explore_burst_count'] ?? 0) : 0;
                $hitBurstLimit = ($burst + 1) >= $this->exploreBurstLimit();
                $newBurstCount = $hitBurstLimit ? 0 : ($burst + 1);
                $blockedUntilSql = $hitBurstLimit
                    ? 'DATE_ADD(NOW(), INTERVAL ' . (int) $this->exploreLongRestSeconds() . ' SECOND)'
                    : 'NULL';
                if ($row) {
                    $sql = "
                        UPDATE user_location
                        SET region_id = ?,
                            last_explore_at = NOW(),
                            explore_burst_count = ?,
                            explore_blocked_until = {$blockedUntilSql}
                        WHERE user_id = ?
                    ";
                    $db->prepare($sql)->execute([$regionId, $newBurstCount, $userId]);
                } else {
                    $db->prepare("
                        INSERT INTO user_location (user_id, region_id, last_explore_at, explore_burst_count, explore_blocked_until)
                        VALUES (?, ?, NOW(), ?, {$blockedUntilSql})
                    ")->execute([$userId, $regionId, $newBurstCount]);
                }
            } else {
                $stmt = $db->prepare("
                    INSERT INTO user_location (user_id, region_id, last_explore_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE region_id = VALUES(region_id), last_explore_at = VALUES(last_explore_at)
                ");
                $stmt->execute([$userId, $regionId]);
            }

            $db->commit();
            return 0;
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('ExplorationService::reserveExploration ' . $e->getMessage());
            return $this->exploreMinIntervalSeconds();
        }
    }

    private function getRegionById(int $regionId): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id, name, difficulty, description, min_realm_id, resource_type, exploration_encounters, hidden_dungeon_chance FROM world_regions WHERE id = ? LIMIT 1');
            $stmt->execute([$regionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('ExplorationService::getRegionById ' . $e->getMessage());
            return null;
        }
    }

    private function getUserRealmId(int $userId): int
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT realm_id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            return (int)($stmt->fetchColumn() ?: 1);
        } catch (PDOException $e) {
            error_log('ExplorationService::getUserRealmId ' . $e->getMessage());
            return 1;
        }
    }

    private function pickEncounterNpcId(int $userId, int $difficulty): ?int
    {
        try {
            $userRealmId = $this->getUserRealmId($userId);
            $targetLevel = max(1, $difficulty * 2);
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id
                FROM npcs
                WHERE realm_id <= ?
                ORDER BY ABS(level - ?) ASC, realm_id DESC, level ASC, id ASC
                LIMIT 1
            ");
            $stmt->execute([$userRealmId, $targetLevel]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int)$id : null;
        } catch (PDOException $e) {
            error_log('ExplorationService::pickEncounterNpcId ' . $e->getMessage());
            return null;
        }
    }

    private function pickRandomTemplateByType(string $type): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id, name, type FROM item_templates WHERE type = ? ORDER BY id ASC');
            $stmt->execute([$type]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows === []) {
                return null;
            }
            return $rows[array_rand($rows)];
        } catch (PDOException $e) {
            error_log('ExplorationService::pickRandomTemplateByType ' . $e->getMessage());
            return null;
        }
    }

    private function pickMaterialTemplateForDifficulty(int $difficulty): ?array
    {
        $tier = $difficulty >= 4 ? 3 : ($difficulty >= 3 ? 2 : 1);
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, name, type
                FROM item_templates
                WHERE type = 'material' AND material_tier = ? AND id <> ?
                ORDER BY id ASC
            ");
            $stmt->execute([$tier, self::RUNE_FRAGMENT_TEMPLATE_ID]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows === []) {
                $stmt = $db->prepare("
                    SELECT id, name, type
                    FROM item_templates
                    WHERE type = 'material' AND id <> ?
                    ORDER BY id ASC
                ");
                $stmt->execute([self::RUNE_FRAGMENT_TEMPLATE_ID]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            if ($rows === []) {
                return null;
            }
            return $rows[array_rand($rows)];
        } catch (PDOException $e) {
            error_log('ExplorationService::pickMaterialTemplateForDifficulty ' . $e->getMessage());
            return null;
        }
    }

    private function getTemplateById(int $templateId): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id, name, type FROM item_templates WHERE id = ? LIMIT 1');
            $stmt->execute([$templateId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('ExplorationService::getTemplateById ' . $e->getMessage());
            return null;
        }
    }

    private function formatRealmDisplayLabel(?string $realmName, int $realmId): string
    {
        require_once dirname(__DIR__).'/includes/realm_display.php';

        return realm_display_label($realmName, $realmId > 0 ? $realmId : null);
    }

    private function pickDungeonForRegion(int $regionId): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT d.id, d.name, d.difficulty, d.min_realm_id, d.boss_name, r.name AS min_realm_name
                FROM dungeons d
                LEFT JOIN realms r ON r.id = d.min_realm_id
                WHERE d.region_id = ?
                ORDER BY d.difficulty ASC, d.id ASC
                LIMIT 1
            ");
            $stmt->execute([$regionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('ExplorationService::pickDungeonForRegion ' . $e->getMessage());
            return null;
        }
    }
}
