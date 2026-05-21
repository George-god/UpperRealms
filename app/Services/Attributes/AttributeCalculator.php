<?php

declare(strict_types=1);

namespace App\Services\Attributes;

/**
 * Derives combat and cultivation modifiers from primary attributes, specialization, body type, and synergies.
 */
final class AttributeCalculator
{
    /** @var list<string> */
    public const PRIMARY_KEYS = ['strength', 'agility', 'vitality', 'spirit', 'soul', 'willpower'];

    /**
     * @param  array<string, mixed>  $row  User row with primary stats + optional specialization/body_type
     * @return array<string, mixed>
     */
    public function compute(array $row): array
    {
        $primaries = $this->extractPrimaries($row);
        $effective = $this->applySpecializationAndBody($primaries, $row);

        $derived = $this->computeDerived($effective);
        $synergies = $this->activeSynergies($effective);
        $derived = $this->applySynergyBonuses($derived, $synergies);
        $derived = $this->applyBodyTypeBonuses($derived, (string) ($row['body_type'] ?? ''));

        return [
            'primaries' => $primaries,
            'effective_primaries' => $effective,
            'flat_attack' => $derived['flat_attack'],
            'flat_defense' => $derived['flat_defense'],
            'flat_max_chi' => $derived['flat_max_chi'],
            'crit_rate' => $derived['crit_rate'],
            'crit_damage' => $derived['crit_damage'],
            'dodge' => $derived['dodge'],
            'attack_speed' => $derived['attack_speed'],
            'cultivation_speed_pct' => $derived['cultivation_speed_pct'],
            'technique_power_pct' => $derived['technique_power_pct'],
            'breakthrough_stability_pct' => $derived['breakthrough_stability_pct'],
            'mental_resistance_pct' => $derived['mental_resistance_pct'],
            'tribulation_resistance_pct' => $derived['tribulation_resistance_pct'],
            'debuff_resistance_pct' => $derived['debuff_resistance_pct'],
            'active_synergies' => $synergies,
            'specialization' => (string) ($row['stat_specialization'] ?? ''),
            'body_type' => (string) ($row['body_type'] ?? ''),
        ];
    }

    /**
     * Apply flat derived bonuses and combat meta to pipeline final stats.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public function applyToFinalStats(array $row, array $stats): array
    {
        $derived = $this->compute($row);

        return array_merge($stats, [
            'strength' => $derived['primaries']['strength'],
            'agility' => $derived['primaries']['agility'],
            'vitality' => $derived['primaries']['vitality'],
            'spirit' => $derived['primaries']['spirit'],
            'soul' => $derived['primaries']['soul'],
            'willpower' => $derived['primaries']['willpower'],
            'attack' => max(1, (int) ($stats['attack'] ?? 0) + $derived['flat_attack']),
            'defense' => max(0, (int) ($stats['defense'] ?? 0) + $derived['flat_defense']),
            'max_chi' => max(1, (int) ($stats['max_chi'] ?? 1) + $derived['flat_max_chi']),
            'chi' => min((int) ($stats['chi'] ?? 0), max(1, (int) ($stats['max_chi'] ?? 1) + $derived['flat_max_chi'])),
            'attribute_crit_chance_bonus' => $derived['crit_rate'],
            'attribute_crit_damage_bonus' => max(0.0, $derived['crit_damage'] - 1.5),
            'attribute_dodge_bonus' => $derived['dodge'],
            'attribute_attack_speed' => $derived['attack_speed'],
            'attribute_cultivation_speed_pct' => $derived['cultivation_speed_pct'],
            'attribute_technique_power_pct' => $derived['technique_power_pct'],
            'attribute_breakthrough_stability_pct' => $derived['breakthrough_stability_pct'],
            'attribute_mental_resistance_pct' => $derived['mental_resistance_pct'],
            'attribute_tribulation_resistance_pct' => $derived['tribulation_resistance_pct'],
            'attribute_debuff_resistance_pct' => $derived['debuff_resistance_pct'],
            'attribute_synergies' => $derived['active_synergies'],
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function extractPrimaries(array $row): array
    {
        $starting = config('attributes.starting', []);
        $out = [];
        foreach (self::PRIMARY_KEYS as $key) {
            $out[$key] = max(0, (int) ($row[$key] ?? $starting[$key] ?? 5));
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $primaries
     * @return array<string, int>
     */
    private function applySpecializationAndBody(array $primaries, array $row): array
    {
        $effective = $primaries;
        $specKey = (string) ($row['stat_specialization'] ?? '');
        $spec = config("attributes.specializations.{$specKey}");
        if (is_array($spec) && isset($spec['bonuses'])) {
            foreach ($spec['bonuses'] as $stat => $mult) {
                if ($stat === 'all') {
                    foreach (self::PRIMARY_KEYS as $k) {
                        $effective[$k] = (int) round($effective[$k] * (1 + (float) $mult));
                    }
                } elseif (isset($effective[$stat])) {
                    $effective[$stat] = (int) round($effective[$stat] * (1 + (float) $mult));
                }
            }
        }

        $bodyKey = (string) ($row['body_type'] ?? '');
        $body = config("attributes.body_types.{$bodyKey}");
        if (is_array($body) && isset($body['affinities'])) {
            foreach ($body['affinities'] as $stat => $bonus) {
                if (isset($effective[$stat])) {
                    $effective[$stat] += (int) $bonus;
                }
            }
        }

        return $effective;
    }

    /**
     * @param  array<string, int>  $stats
     * @return array<string, float|int>
     */
    private function computeDerived(array $stats): array
    {
        $cfg = config('attributes.derived', []);
        $bodyKey = '';
        $bodyBonuses = [];

        $flatAttack = (int) floor(
            ($stats['strength'] ?? 0) * (float) ($cfg['attack']['strength'] ?? 2.2)
            + ($stats['spirit'] ?? 0) * (float) ($cfg['attack']['spirit'] ?? 0.6)
        );
        $flatDefense = (int) floor(
            ($stats['vitality'] ?? 0) * (float) ($cfg['defense']['vitality'] ?? 1.6)
            + ($stats['willpower'] ?? 0) * (float) ($cfg['defense']['willpower'] ?? 0.4)
        );
        $flatMaxChi = (int) floor(
            ($stats['spirit'] ?? 0) * (float) ($cfg['max_chi']['spirit'] ?? 10)
            + ($stats['vitality'] ?? 0) * (float) ($cfg['max_chi']['vitality'] ?? 6)
        );

        $critRate = $this->scaled(
            (float) ($cfg['crit_rate']['base'] ?? 0.03),
            $stats,
            $cfg['crit_rate'] ?? [],
            ['agility', 'soul']
        );
        $critDamage = $this->scaled(
            (float) ($cfg['crit_damage']['base'] ?? 1.5),
            $stats,
            $cfg['crit_damage'] ?? [],
            ['soul'],
            false
        );
        $dodge = $this->scaled(
            (float) ($cfg['dodge']['base'] ?? 0.02),
            $stats,
            $cfg['dodge'] ?? [],
            ['agility']
        );
        $attackSpeed = $this->scaled(
            (float) ($cfg['attack_speed']['base'] ?? 1.0),
            $stats,
            $cfg['attack_speed'] ?? [],
            ['agility'],
            false
        );

        return [
            'flat_attack' => $flatAttack,
            'flat_defense' => $flatDefense,
            'flat_max_chi' => $flatMaxChi,
            'crit_rate' => $critRate,
            'crit_damage' => $critDamage,
            'dodge' => $dodge,
            'attack_speed' => $attackSpeed,
            'cultivation_speed_pct' => $this->scaled(
                (float) ($cfg['cultivation_speed']['base'] ?? 0),
                $stats,
                $cfg['cultivation_speed'] ?? [],
                ['spirit']
            ),
            'technique_power_pct' => $this->scaled(
                (float) ($cfg['technique_power']['base'] ?? 0),
                $stats,
                $cfg['technique_power'] ?? [],
                ['spirit', 'soul']
            ),
            'breakthrough_stability_pct' => $this->scaled(
                (float) ($cfg['breakthrough_stability']['base'] ?? 0),
                $stats,
                $cfg['breakthrough_stability'] ?? [],
                ['soul', 'willpower']
            ),
            'mental_resistance_pct' => $this->scaled(
                (float) ($cfg['mental_resistance']['base'] ?? 0),
                $stats,
                $cfg['mental_resistance'] ?? [],
                ['soul', 'willpower']
            ),
            'tribulation_resistance_pct' => $this->scaled(
                (float) ($cfg['tribulation_resistance']['base'] ?? 0),
                $stats,
                $cfg['tribulation_resistance'] ?? [],
                ['willpower', 'spirit']
            ),
            'debuff_resistance_pct' => $this->scaled(
                (float) ($cfg['debuff_resistance']['base'] ?? 0),
                $stats,
                $cfg['debuff_resistance'] ?? [],
                ['willpower', 'vitality']
            ),
        ];
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<string, float>  $cfg
     * @param  list<string>  $keys
     */
    private function scaled(float $base, array $stats, array $cfg, array $keys, bool $useCap = true): float
    {
        $value = $base;
        foreach ($keys as $key) {
            $value += ($stats[$key] ?? 0) * (float) ($cfg[$key] ?? 0);
        }
        if ($useCap && isset($cfg['cap'])) {
            return min((float) $cfg['cap'], max(0.0, $value));
        }

        return max(0.0, $value);
    }

    /**
     * @param  array<string, int>  $stats
     * @return list<array<string, mixed>>
     */
    public function activeSynergies(array $stats): array
    {
        $active = [];
        foreach (config('attributes.synergies', []) as $synergy) {
            if (! is_array($synergy)) {
                continue;
            }
            $requires = $synergy['requires'] ?? [];
            $ok = true;
            if (isset($requires['min_all'])) {
                $minAll = (int) $requires['min_all'];
                foreach (self::PRIMARY_KEYS as $k) {
                    if (($stats[$k] ?? 0) < $minAll) {
                        $ok = false;
                        break;
                    }
                }
                unset($requires['min_all']);
            }
            foreach ($requires as $stat => $min) {
                if (($stats[$stat] ?? 0) < (int) $min) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $active[] = $synergy;
            }
        }

        return $active;
    }

    /**
     * @param  array<string, float|int>  $derived
     * @param  list<array<string, mixed>>  $synergies
     * @return array<string, float|int>
     */
    /**
     * @param  array<string, float|int>  $derived
     * @return array<string, float|int>
     */
    private function applyBodyTypeBonuses(array $derived, string $bodyKey): array
    {
        if ($bodyKey === '') {
            return $derived;
        }
        $body = config("attributes.body_types.{$bodyKey}");
        if (! is_array($body)) {
            return $derived;
        }
        foreach ($body['bonuses'] ?? [] as $key => $bonus) {
            $pctKey = str_ends_with($key, '_pct') ? $key : "{$key}_pct";
            if (isset($derived[$pctKey])) {
                $derived[$pctKey] = (float) $derived[$pctKey] + (float) $bonus;
            } elseif ($key === 'crit_damage' && isset($derived['crit_damage'])) {
                $derived['crit_damage'] = (float) $derived['crit_damage'] + (float) $bonus;
            } elseif ($key === 'defense' && isset($derived['flat_defense'])) {
                $derived['flat_defense'] = (int) round($derived['flat_defense'] * (1 + (float) $bonus));
            }
        }

        return $derived;
    }

    /**
     * @param  array<string, float|int>  $derived
     * @param  list<array<string, mixed>>  $synergies
     * @return array<string, float|int>
     */
    private function applySynergyBonuses(array $derived, array $synergies): array
    {
        foreach ($synergies as $synergy) {
            $bonuses = $synergy['bonuses'] ?? [];
            if (isset($bonuses['all_derived'])) {
                $mult = 1 + (float) $bonuses['all_derived'];
                foreach (['flat_attack', 'flat_defense', 'flat_max_chi'] as $k) {
                    $derived[$k] = (int) round($derived[$k] * $mult);
                }
                foreach (['crit_rate', 'dodge', 'cultivation_speed_pct', 'technique_power_pct'] as $k) {
                    if (isset($derived[$k])) {
                        $derived[$k] = (float) $derived[$k] * $mult;
                    }
                }
            }
            foreach ($bonuses as $key => $bonus) {
                if ($key === 'all_derived') {
                    continue;
                }
                if ($key === 'defense' && isset($derived['flat_defense'])) {
                    $derived['flat_defense'] = (int) round($derived['flat_defense'] * (1 + (float) $bonus));
                } elseif (isset($derived[$key])) {
                    $derived[$key] = (float) $derived[$key] + (float) $bonus;
                } elseif (isset($derived["{$key}_pct"])) {
                    $derived["{$key}_pct"] = (float) $derived["{$key}_pct"] + (float) $bonus;
                }
            }
        }

        return $derived;
    }
}
