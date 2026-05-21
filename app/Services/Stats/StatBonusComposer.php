<?php

declare(strict_types=1);

namespace App\Services\Stats;

/**
 * Normalizes percent bonuses: sum pools, cap, apply once (reduces multiplicative stacking).
 */
final class StatBonusComposer
{
    /**
     * @param  array<string, mixed>  $stats
     * @param  array{attack_pct?: float, defense_pct?: float, max_chi_pct?: float}  $pool
     * @return array<string, mixed>
     */
    public function applyPercentPool(array $stats, array $pool): array
    {
        if (! config('stats.use_additive_percent_pool', true)) {
            return $stats;
        }

        $atkPct = $this->cap('max_pooled_attack_pct', (float) ($pool['attack_pct'] ?? 0.0));
        $defPct = $this->cap('max_pooled_defense_pct', (float) ($pool['defense_pct'] ?? 0.0));
        $chiPct = $this->cap('max_pooled_max_chi_pct', (float) ($pool['max_chi_pct'] ?? 0.0));

        if ($atkPct <= 0.0 && $defPct <= 0.0 && $chiPct <= 0.0) {
            return $stats;
        }

        $attack = max(1, (int) round(($stats['attack'] ?? 0) * (1 + $atkPct)));
        $defense = max(0, (int) round(($stats['defense'] ?? 0) * (1 + $defPct)));
        $maxChi = max(1, (int) round(($stats['max_chi'] ?? 1) * (1 + $chiPct)));

        return array_merge($stats, [
            'attack' => $attack,
            'defense' => $defense,
            'max_chi' => $maxChi,
            'chi' => min((int) ($stats['chi'] ?? $maxChi), $maxChi),
            '_pooled_attack_pct' => $atkPct,
            '_pooled_defense_pct' => $defPct,
            '_pooled_max_chi_pct' => $chiPct,
        ]);
    }

    private function cap(string $configKey, float $value): float
    {
        $max = (float) config('stats.'.$configKey, 2.5);

        return max(0.0, min($max, $value));
    }
}
