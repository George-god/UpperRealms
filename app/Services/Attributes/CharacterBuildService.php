<?php

declare(strict_types=1);

namespace App\Services\Attributes;

use App\Services\Stats\StatInvalidator;
use App\Support\PdoDatabase;
use PDO;
use PDOException;

/**
 * Stat allocation, specialization, body type, and respec.
 */
final class CharacterBuildService
{
    public function __construct(
        private readonly AttributeCalculator $calculator = new AttributeCalculator,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function getBuildState(int $userId): ?array
    {
        $row = $this->fetchUserRow($userId);
        if ($row === null) {
            return null;
        }

        $derived = $this->calculator->compute($row);

        return [
            'user_id' => $userId,
            'level' => (int) ($row['level'] ?? 1),
            'realm_id' => (int) ($row['realm_id'] ?? 1),
            'primaries' => $derived['primaries'],
            'effective_primaries' => $derived['effective_primaries'],
            'attribute_points' => (int) ($row['attribute_points'] ?? 0),
            'stat_specialization' => $row['stat_specialization'] ?? null,
            'body_type' => $row['body_type'] ?? null,
            'derived' => $derived,
            'synergies' => $derived['active_synergies'],
            'respec' => $this->respecInfo($row),
            'config' => [
                'stats' => config('attributes.stats', []),
                'specializations' => config('attributes.specializations', []),
                'body_types' => config('attributes.body_types', []),
                'synergies' => config('attributes.synergies', []),
            ],
        ];
    }

    /**
     * @return array{success: bool, message?: string, build?: array<string, mixed>}
     */
    public function allocate(int $userId, string $stat, int $points = 1): array
    {
        $stat = strtolower(trim($stat));
        if (! in_array($stat, AttributeCalculator::PRIMARY_KEYS, true)) {
            return ['success' => false, 'message' => 'Invalid attribute.'];
        }
        $points = max(1, min(20, $points));

        try {
            $db = PdoDatabase::connection();
            $db->beginTransaction();
            $row = $this->fetchUserRowForUpdate($db, $userId);
            if ($row === null) {
                $db->rollBack();

                return ['success' => false, 'message' => 'User not found.'];
            }

            $available = (int) ($row['attribute_points'] ?? 0);
            if ($available < $points) {
                $db->rollBack();

                return ['success' => false, 'message' => 'Not enough attribute points.'];
            }

            $cap = (int) config('attributes.stat_cap', 999);
            $current = (int) ($row[$stat] ?? 0);
            if ($current + $points > $cap) {
                $db->rollBack();

                return ['success' => false, 'message' => 'Attribute cap reached.'];
            }

            $db->prepare("UPDATE users SET {$stat} = ?, attribute_points = attribute_points - ? WHERE id = ?")
                ->execute([$current + $points, $points, $userId]);

            $db->commit();
            $this->invalidateStats($userId);

            return ['success' => true, 'build' => $this->getBuildState($userId)];
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('CharacterBuildService::allocate '.$e->getMessage());

            return ['success' => false, 'message' => 'Could not allocate points.'];
        }
    }

    /**
     * @return array{success: bool, message?: string, build?: array<string, mixed>}
     */
    public function setSpecialization(int $userId, ?string $key): array
    {
        $key = $key !== null && $key !== '' ? strtolower(trim($key)) : null;
        if ($key !== null && ! isset(config('attributes.specializations')[$key])) {
            return ['success' => false, 'message' => 'Unknown specialization.'];
        }

        try {
            $db = PdoDatabase::connection();
            $db->prepare('UPDATE users SET stat_specialization = ? WHERE id = ?')->execute([$key, $userId]);
            $this->invalidateStats($userId);

            return ['success' => true, 'build' => $this->getBuildState($userId)];
        } catch (PDOException $e) {
            error_log('CharacterBuildService::setSpecialization '.$e->getMessage());

            return ['success' => false, 'message' => 'Could not update specialization.'];
        }
    }

    /**
     * @return array{success: bool, message?: string, build?: array<string, mixed>}
     */
    public function setBodyType(int $userId, ?string $key): array
    {
        $key = $key !== null && $key !== '' ? strtolower(trim($key)) : null;
        if ($key !== null && ! isset(config('attributes.body_types')[$key])) {
            return ['success' => false, 'message' => 'Unknown body type.'];
        }

        try {
            $db = PdoDatabase::connection();
            $db->prepare('UPDATE users SET body_type = ? WHERE id = ?')->execute([$key, $userId]);
            $this->invalidateStats($userId);

            return ['success' => true, 'build' => $this->getBuildState($userId)];
        } catch (PDOException $e) {
            error_log('CharacterBuildService::setBodyType '.$e->getMessage());

            return ['success' => false, 'message' => 'Could not update body type.'];
        }
    }

    /**
     * @return array{success: bool, message?: string, build?: array<string, mixed>}
     */
    public function respec(int $userId): array
    {
        try {
            $db = PdoDatabase::connection();
            $db->beginTransaction();
            $row = $this->fetchUserRowForUpdate($db, $userId);
            if ($row === null) {
                $db->rollBack();

                return ['success' => false, 'message' => 'User not found.'];
            }

            $info = $this->respecInfo($row);
            if (! $info['can_afford']) {
                $db->rollBack();

                return ['success' => false, 'message' => 'Not enough spirit stones for respec.'];
            }

            $cost = (int) $info['spirit_stone_cost'];
            if ($cost > 0) {
                $stones = (int) ($row['spirit_stones'] ?? 0);
                $db->prepare('UPDATE users SET spirit_stones = ? WHERE id = ?')
                    ->execute([max(0, $stones - $cost), $userId]);
            }

            $refund = $this->pointsSpentAboveBase($row);
            $starting = config('attributes.starting', []);
            $db->prepare('
                UPDATE users SET
                    strength = ?, agility = ?, vitality = ?, spirit = ?, soul = ?, willpower = ?,
                    attribute_points = attribute_points + ?,
                    attribute_respec_count = attribute_respec_count + 1
                WHERE id = ?
            ')->execute([
                (int) ($starting['strength'] ?? 5),
                (int) ($starting['agility'] ?? 5),
                (int) ($starting['vitality'] ?? 5),
                (int) ($starting['spirit'] ?? 5),
                (int) ($starting['soul'] ?? 5),
                (int) ($starting['willpower'] ?? 5),
                $refund,
                $userId,
            ]);

            $db->commit();
            $this->invalidateStats($userId);

            return ['success' => true, 'build' => $this->getBuildState($userId)];
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('CharacterBuildService::respec '.$e->getMessage());

            return ['success' => false, 'message' => 'Respec failed.'];
        }
    }

    public function grantBreakthroughPoints(int $userId, int $newRealmId): void
    {
        $cfg = config('attributes.breakthrough', []);
        $points = (int) ($cfg['base_points'] ?? 5) + max(0, $newRealmId - 1) * (int) ($cfg['per_realm_bonus'] ?? 2);

        try {
            $db = PdoDatabase::connection();
            $db->prepare('UPDATE users SET attribute_points = attribute_points + ? WHERE id = ?')
                ->execute([$points, $userId]);
        } catch (PDOException $e) {
            error_log('CharacterBuildService::grantBreakthroughPoints '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pointsSpentAboveBase(array $row): int
    {
        $starting = config('attributes.starting', []);
        $spent = 0;
        foreach (AttributeCalculator::PRIMARY_KEYS as $key) {
            $spent += max(0, (int) ($row[$key] ?? 0) - (int) ($starting[$key] ?? 5));
        }

        return $spent;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function respecInfo(array $row): array
    {
        $cfg = config('attributes.respec', []);
        $count = (int) ($row['attribute_respec_count'] ?? 0);
        $free = (int) ($cfg['free_respecs'] ?? 1);
        $cost = $count < $free ? 0 : (int) ($cfg['spirit_stone_cost'] ?? 500);
        $stones = (int) ($row['spirit_stones'] ?? 0);

        return [
            'count' => $count,
            'spirit_stone_cost' => $cost,
            'can_afford' => $stones >= $cost,
            'spirit_stones' => $stones,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUserRow(int $userId): ?array
    {
        try {
            $db = PdoDatabase::connection();
            $cols = $this->userColumnsSql();
            $stmt = $db->prepare("SELECT {$cols} FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (PDOException $e) {
            error_log('CharacterBuildService::fetchUserRow '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUserRowForUpdate(PDO $db, int $userId): ?array
    {
        $cols = $this->userColumnsSql();
        $stmt = $db->prepare("SELECT {$cols} FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function userColumnsSql(): string
    {
        return 'id, level, realm_id, spirit_stones,
            strength, agility, vitality, spirit, soul, willpower,
            attribute_points, stat_specialization, body_type, attribute_respec_count';
    }

    private function invalidateStats(int $userId): void
    {
        if (! function_exists('app') || ! app()->bound(StatInvalidator::class)) {
            return;
        }

        app(StatInvalidator::class)->invalidate($userId, 'attributes');
    }
}
