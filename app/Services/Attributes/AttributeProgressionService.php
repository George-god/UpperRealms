<?php

declare(strict_types=1);

namespace App\Services\Attributes;

use App\Support\PdoDatabase;
use PDO;
use PDOException;

/**
 * Random primary growth on minor level-ups.
 */
final class AttributeProgressionService
{
    /**
     * Apply random stat growth when the player gains a cultivation level.
     *
     * @return list<array{stat: string, amount: int, label: string}>
     */
    public function applyMinorLevelGrowth(PDO $db, int $userId, int $newLevel): array
    {
        if (! $this->attributesEnabled($db)) {
            return [];
        }

        $cfg = config('attributes.minor_level', []);
        $rolls = max(1, (int) ($cfg['growth_rolls'] ?? 2));
        $min = max(1, (int) ($cfg['min_per_stat'] ?? 1));
        $max = max($min, (int) ($cfg['max_per_stat'] ?? 2));
        $cap = (int) config('attributes.stat_cap', 999);
        $keys = AttributeCalculator::PRIMARY_KEYS;
        $statLabels = config('attributes.stats', []);
        $gains = [];

        try {
            $stmt = $db->prepare('
                SELECT strength, agility, vitality, spirit, soul, willpower
                FROM users WHERE id = ? LIMIT 1
            ');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (! $row) {
                return [];
            }

            $updates = $row;
            for ($i = 0; $i < $rolls; $i++) {
                $stat = $keys[array_rand($keys)];
                $amount = mt_rand($min, $max);
                $before = (int) $updates[$stat];
                $after = min($cap, $before + $amount);
                $applied = $after - $before;
                if ($applied < 1) {
                    continue;
                }
                $updates[$stat] = $after;
                $gains[] = [
                    'stat' => $stat,
                    'amount' => $applied,
                    'label' => (string) ($statLabels[$stat]['label'] ?? ucfirst($stat)),
                ];
            }

            if ($gains === []) {
                return [];
            }

            $db->prepare('
                UPDATE users SET
                    strength = ?, agility = ?, vitality = ?, spirit = ?, soul = ?, willpower = ?
                WHERE id = ?
            ')->execute([
                $updates['strength'],
                $updates['agility'],
                $updates['vitality'],
                $updates['spirit'],
                $updates['soul'],
                $updates['willpower'],
                $userId,
            ]);
        } catch (PDOException $e) {
            error_log('AttributeProgressionService::applyMinorLevelGrowth '.$e->getMessage());

            return [];
        }

        return $gains;
    }

    private function attributesEnabled(PDO $db): bool
    {
        try {
            $db->query('SELECT strength FROM users LIMIT 0');

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
