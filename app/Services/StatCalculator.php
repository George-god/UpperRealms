<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Attributes\AttributeCalculator;
use App\Services\Stats\StatBonusComposer;
use App\Support\GameDebug;
use App\Support\RealmDisplay;
use App\Support\PdoDatabase;
use PDOException;

/**
 * Stat Calculator - centralized stat calculation.
 */
class StatCalculator
{
    /** @var array<int, array<string, mixed>> Reused when the same instance calculates multiple users (e.g. PvP). */
    private array $finalStatsCache = [];

    /** @var array<int, float> Realm multipliers are global; safe to cache per process. */
    private static array $realmMultiplierById = [];

    /** @var array<int, string> */
    private static array $realmNameById = [];

    private ?AttributeCalculator $attributeCalculator = null;

    /**
     * Calculate final combat stats for a user.
     * Pipeline: base → equipment → Dao path (realm tier + Dao %) → bloodline → artifacts → titles → buffs (manuals + scroll).
     *
     * @param int $userId User ID
     * @return array ['user_id', 'base' => array, 'final' => array, 'modifiers' => ['equipment_bonus' => array]]
     */
    public function calculateFinalStats(int $userId): array
    {
        if (isset($this->finalStatsCache[$userId])) {
            return $this->finalStatsCache[$userId];
        }

        $baseStats = $this->getBaseStats($userId);
        if (!$baseStats) {
            throw new \Exception("User not found");
        }

        $afterEquipment = $this->applyEquippedItemBonuses($baseStats, $userId);
        $afterRealm = $this->applyRealmTierMultiplier($afterEquipment);

        if (config('stats.use_additive_percent_pool', true)) {
            $afterPooled = $this->applyPooledPercentBonuses($afterRealm, $userId);
            $finalStats = $this->attributes()->applyToFinalStats($baseStats, $afterPooled);
        } else {
            $afterDaoPath = $this->applyDaoBonuses($afterRealm);
            $afterBloodline = $this->applyBloodlineBonuses($afterDaoPath, $userId);
            $afterArtifacts = $this->applyArtifactBonuses($afterBloodline, $userId);
            $afterTitles = $this->applyTitleBonuses($afterArtifacts, $userId);
            $finalStats = $this->applyActiveScrollEffect($this->applyCultivationManualBonuses($afterTitles, $userId));
            $finalStats = $this->attributes()->applyToFinalStats($baseStats, $finalStats);
        }

        $equipmentBonus = $this->getEquippedItemBonusesSummary($userId);

        $payload = [
            'user_id' => $userId,
            'base' => $baseStats,
            'final' => $finalStats,
            'modifiers' => [
                'equipment_bonus' => $equipmentBonus,
            ],
        ];

        GameDebug::logStatCalculation($userId, $payload);

        $this->finalStatsCache[$userId] = $payload;

        return $this->finalStatsCache[$userId];
    }

    /** Clear per-instance cache (e.g. long-lived workers that mutate user stats). */
    public function clearFinalStatsCache(): void
    {
        $this->finalStatsCache = [];
    }

    /**
     * Get base stats from database plus Dao Path metadata.
     */
    private function getBaseStats(int $userId): ?array
    {
        try {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare("
                SELECT u.id, u.realm_id, u.level, u.chi, u.max_chi, u.attack, u.defense, u.active_scroll_type,
                       u.strength, u.agility, u.vitality, u.spirit, u.soul, u.willpower,
                       u.attribute_points, u.stat_specialization, u.body_type,
                       d.path_key AS dao_path_key, d.name AS dao_path_name, d.alignment AS dao_alignment,
                       d.element AS dao_element, d.attack_bonus_pct, d.defense_bonus_pct, d.max_chi_bonus_pct,
                       d.dodge_bonus_pct, d.bonus_damage_pct, d.heal_on_hit_pct, d.reflect_damage_pct,
                       d.self_damage_pct, d.favored_tribulation
                FROM users u
                LEFT JOIN dao_paths d ON d.id = u.dao_path_id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) {
                return null;
            }
            return [
                'attack' => (int)$user['attack'],
                'defense' => (int)$user['defense'],
                'chi' => (int)$user['chi'],
                'max_chi' => (int)$user['max_chi'],
                'level' => (int)$user['level'],
                'realm_id' => (int)$user['realm_id'],
                'active_scroll_type' => isset($user['active_scroll_type']) && $user['active_scroll_type'] !== '' ? (string)$user['active_scroll_type'] : null,
                'dao_path_key' => !empty($user['dao_path_key']) ? (string)$user['dao_path_key'] : null,
                'dao_path_name' => !empty($user['dao_path_name']) ? (string)$user['dao_path_name'] : null,
                'dao_alignment' => !empty($user['dao_alignment']) ? (string)$user['dao_alignment'] : null,
                'dao_element' => !empty($user['dao_element']) ? (string)$user['dao_element'] : null,
                'dao_attack_bonus_pct' => (float)($user['attack_bonus_pct'] ?? 0.0),
                'dao_defense_bonus_pct' => (float)($user['defense_bonus_pct'] ?? 0.0),
                'dao_max_chi_bonus_pct' => (float)($user['max_chi_bonus_pct'] ?? 0.0),
                'dao_dodge_bonus' => (float)($user['dodge_bonus_pct'] ?? 0.0),
                'dao_bonus_damage_pct' => (float)($user['bonus_damage_pct'] ?? 0.0),
                'dao_heal_on_hit_pct' => (float)($user['heal_on_hit_pct'] ?? 0.0),
                'dao_reflect_damage_pct' => (float)($user['reflect_damage_pct'] ?? 0.0),
                'dao_self_damage_pct' => (float)($user['self_damage_pct'] ?? 0.0),
                'dao_favored_tribulation' => !empty($user['favored_tribulation']) ? (string)$user['favored_tribulation'] : null,
                'strength' => (int)($user['strength'] ?? 5),
                'agility' => (int)($user['agility'] ?? 5),
                'vitality' => (int)($user['vitality'] ?? 5),
                'spirit' => (int)($user['spirit'] ?? 5),
                'soul' => (int)($user['soul'] ?? 5),
                'willpower' => (int)($user['willpower'] ?? 5),
                'attribute_points' => (int)($user['attribute_points'] ?? 0),
                'stat_specialization' => $user['stat_specialization'] ?? null,
                'body_type' => $user['body_type'] ?? null,
            ];
        } catch (PDOException $e) {
            error_log("StatCalculator::getBaseStats " . $e->getMessage());
            return null;
        }
    }

    private function attributes(): AttributeCalculator
    {
        return $this->attributeCalculator ??= new AttributeCalculator;
    }

    /**
     * Apply equipped item bonuses (flat bonuses from item_templates). Does not modify base stats in DB.
     */
    private function applyEquippedItemBonuses(array $stats, int $userId): array
    {
        $itemService = new ItemService();
        $bonuses = $itemService->getEquippedItemBonuses($userId);
        return [
            'attack' => $stats['attack'] + $bonuses['attack'],
            'defense' => $stats['defense'] + $bonuses['defense'],
            'chi' => $stats['chi'],
            'max_chi' => $stats['max_chi'] + $bonuses['hp'],
            'level' => $stats['level'],
            'realm_id' => $stats['realm_id'],
            'active_scroll_type' => $stats['active_scroll_type'] ?? null,
            'dao_path_key' => $stats['dao_path_key'] ?? null,
            'dao_path_name' => $stats['dao_path_name'] ?? null,
            'dao_alignment' => $stats['dao_alignment'] ?? null,
            'dao_element' => $stats['dao_element'] ?? null,
            'dao_attack_bonus_pct' => (float)($stats['dao_attack_bonus_pct'] ?? 0.0),
            'dao_defense_bonus_pct' => (float)($stats['dao_defense_bonus_pct'] ?? 0.0),
            'dao_max_chi_bonus_pct' => (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0),
            'dao_dodge_bonus' => (float)($stats['dao_dodge_bonus'] ?? 0.0),
            'dao_bonus_damage_pct' => (float)($stats['dao_bonus_damage_pct'] ?? 0.0),
            'dao_heal_on_hit_pct' => (float)($stats['dao_heal_on_hit_pct'] ?? 0.0),
            'dao_reflect_damage_pct' => (float)($stats['dao_reflect_damage_pct'] ?? 0.0),
            'dao_self_damage_pct' => (float)($stats['dao_self_damage_pct'] ?? 0.0),
            'dao_favored_tribulation' => $stats['dao_favored_tribulation'] ?? null,
        ];
    }

    /**
     * Apply realm tier multiplier (single value) to attack, defense, max_chi after equipment. Chi (current) unchanged.
     * Preserves Dao path and other metadata on the stat array for scroll / Dao phases.
     */
    private function applyRealmMultipliers(array $stats): array
    {
        $afterRealm = $this->applyRealmTierMultiplier($stats);
        return $this->applyDaoBonuses($this->applyActiveScrollEffect($afterRealm));
    }

    /**
     * Multiply core combat stats by realm tier; keeps dao_path fields and other keys from $stats intact.
     *
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function applyRealmTierMultiplier(array $stats): array
    {
        $realmId = (int)($stats['realm_id'] ?? 1);
        $mult = $this->getRealmMultiplier($realmId);
        return array_merge($stats, [
            'attack' => max(1, (int)round(($stats['attack'] ?? 0) * $mult)),
            'defense' => max(0, (int)round(($stats['defense'] ?? 0) * $mult)),
            'max_chi' => max(1, (int)round(($stats['max_chi'] ?? 1) * $mult)),
            'realm_id' => $realmId,
        ]);
    }

    /**
     * Phase 2.3: Apply active scroll effect (minor_attack +8%, minor_defense +8%, vitality +10% max HP).
     * Focus rune is applied in BreakthroughService only.
     */
    private function applyActiveScrollEffect(array $stats): array
    {
        $scroll = $stats['active_scroll_type'] ?? null;
        if (!$scroll) {
            return $this->ensureDaoMetaOnStats($stats);
        }
        $attack = $stats['attack'];
        $defense = $stats['defense'];
        $maxChi = $stats['max_chi'];
        if ($scroll === 'minor_attack') {
            $attack = max(1, (int)round($attack * 1.08));
        } elseif ($scroll === 'minor_defense') {
            $defense = (int)round($defense * 1.08);
        } elseif ($scroll === 'vitality') {
            $maxChi = max(1, (int)round($maxChi * 1.10));
        }
        return [
            'attack' => $attack,
            'defense' => $defense,
            'chi' => $stats['chi'],
            'max_chi' => $maxChi,
            'level' => $stats['level'],
            'realm_id' => $stats['realm_id'],
            'active_scroll_type' => $scroll,
            'dao_path_key' => $stats['dao_path_key'] ?? null,
            'dao_path_name' => $stats['dao_path_name'] ?? null,
            'dao_alignment' => $stats['dao_alignment'] ?? null,
            'dao_element' => $stats['dao_element'] ?? null,
            'dao_attack_bonus_pct' => (float)($stats['dao_attack_bonus_pct'] ?? 0.0),
            'dao_defense_bonus_pct' => (float)($stats['dao_defense_bonus_pct'] ?? 0.0),
            'dao_max_chi_bonus_pct' => (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0),
            'dao_dodge_bonus' => (float)($stats['dao_dodge_bonus'] ?? 0.0),
            'dao_bonus_damage_pct' => (float)($stats['dao_bonus_damage_pct'] ?? 0.0),
            'dao_heal_on_hit_pct' => (float)($stats['dao_heal_on_hit_pct'] ?? 0.0),
            'dao_reflect_damage_pct' => (float)($stats['dao_reflect_damage_pct'] ?? 0.0),
            'dao_self_damage_pct' => (float)($stats['dao_self_damage_pct'] ?? 0.0),
            'dao_favored_tribulation' => $stats['dao_favored_tribulation'] ?? null,
        ];
    }

    /**
     * When no scroll is active, ensure dao_* keys exist (realm step uses array_merge and may omit them on older rows).
     *
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function ensureDaoMetaOnStats(array $stats): array
    {
        return array_merge([
            'dao_path_key' => null,
            'dao_path_name' => null,
            'dao_alignment' => null,
            'dao_element' => null,
            'dao_attack_bonus_pct' => 0.0,
            'dao_defense_bonus_pct' => 0.0,
            'dao_max_chi_bonus_pct' => 0.0,
            'dao_dodge_bonus' => 0.0,
            'dao_bonus_damage_pct' => 0.0,
            'dao_heal_on_hit_pct' => 0.0,
            'dao_reflect_damage_pct' => 0.0,
            'dao_self_damage_pct' => 0.0,
            'dao_favored_tribulation' => null,
        ], $stats);
    }

    private function applyDaoBonuses(array $stats): array
    {
        $attack = max(1, (int)round(($stats['attack'] ?? 0) * (1 + (float)($stats['dao_attack_bonus_pct'] ?? 0.0))));
        $defense = max(0, (int)round(($stats['defense'] ?? 0) * (1 + (float)($stats['dao_defense_bonus_pct'] ?? 0.0))));
        $maxChi = max(1, (int)round(($stats['max_chi'] ?? 1) * (1 + (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0))));

        return [
            'attack' => $attack,
            'defense' => $defense,
            'chi' => min((int)$stats['chi'], $maxChi),
            'max_chi' => $maxChi,
            'level' => $stats['level'],
            'realm_id' => $stats['realm_id'],
            'active_scroll_type' => $stats['active_scroll_type'] ?? null,
            'dao_path_key' => $stats['dao_path_key'] ?? null,
            'dao_path_name' => $stats['dao_path_name'] ?? null,
            'dao_alignment' => $stats['dao_alignment'] ?? null,
            'dao_element' => $stats['dao_element'] ?? null,
            'dao_attack_bonus_pct' => (float)($stats['dao_attack_bonus_pct'] ?? 0.0),
            'dao_defense_bonus_pct' => (float)($stats['dao_defense_bonus_pct'] ?? 0.0),
            'dao_max_chi_bonus_pct' => (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0),
            'dao_dodge_bonus' => (float)($stats['dao_dodge_bonus'] ?? 0.0),
            'dao_bonus_damage_pct' => (float)($stats['dao_bonus_damage_pct'] ?? 0.0),
            'dao_heal_on_hit_pct' => (float)($stats['dao_heal_on_hit_pct'] ?? 0.0),
            'dao_reflect_damage_pct' => (float)($stats['dao_reflect_damage_pct'] ?? 0.0),
            'dao_self_damage_pct' => (float)($stats['dao_self_damage_pct'] ?? 0.0),
            'dao_favored_tribulation' => $stats['dao_favored_tribulation'] ?? null,
        ];
    }

    private function applyCultivationManualBonuses(array $stats, int $userId): array
    {
        $manualService = new CultivationManualService();
        $effects = $manualService->getActiveEffectsForUser($userId);
        $attack = max(1, (int)round(($stats['attack'] ?? 0) * (1 + (float)$effects['passive_attack_pct'])));
        $defense = max(0, (int)round(($stats['defense'] ?? 0) * (1 + (float)$effects['passive_defense_pct'])));
        $maxChi = max(1, (int)round(($stats['max_chi'] ?? 1) * (1 + (float)$effects['passive_max_chi_pct'])));

        return [
            'attack' => $attack,
            'defense' => $defense,
            'chi' => min((int)$stats['chi'], $maxChi),
            'max_chi' => $maxChi,
            'level' => $stats['level'],
            'realm_id' => $stats['realm_id'],
            'active_scroll_type' => $stats['active_scroll_type'] ?? null,
            'dao_path_key' => $stats['dao_path_key'] ?? null,
            'dao_path_name' => $stats['dao_path_name'] ?? null,
            'dao_alignment' => $stats['dao_alignment'] ?? null,
            'dao_element' => $stats['dao_element'] ?? null,
            'dao_attack_bonus_pct' => (float)($stats['dao_attack_bonus_pct'] ?? 0.0),
            'dao_defense_bonus_pct' => (float)($stats['dao_defense_bonus_pct'] ?? 0.0),
            'dao_max_chi_bonus_pct' => (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0),
            'dao_dodge_bonus' => (float)($stats['dao_dodge_bonus'] ?? 0.0) + (float)$effects['passive_dodge_pct'],
            'dao_bonus_damage_pct' => (float)($stats['dao_bonus_damage_pct'] ?? 0.0),
            'dao_heal_on_hit_pct' => (float)($stats['dao_heal_on_hit_pct'] ?? 0.0),
            'dao_reflect_damage_pct' => (float)($stats['dao_reflect_damage_pct'] ?? 0.0),
            'dao_self_damage_pct' => (float)($stats['dao_self_damage_pct'] ?? 0.0),
            'dao_favored_tribulation' => $stats['dao_favored_tribulation'] ?? null,
            'manual_effects' => $effects,
        ];
    }

    /**
     * Equipped title: small % bonuses to attack, defense, max chi.
     */
    private function applyTitleBonuses(array $stats, int $userId): array
    {
        $titleService = new TitleService();
        $b = $titleService->getEquippedBonuses($userId);
        $atk = (float)($b['attack_pct'] ?? 0.0);
        $def = (float)($b['defense_pct'] ?? 0.0);
        $mc = (float)($b['max_chi_pct'] ?? 0.0);
        $attack = max(1, (int)round(($stats['attack'] ?? 0) * (1 + $atk)));
        $defense = max(0, (int)round(($stats['defense'] ?? 0) * (1 + $def)));
        $maxChi = max(1, (int)round(($stats['max_chi'] ?? 1) * (1 + $mc)));

        return [
            'attack' => $attack,
            'defense' => $defense,
            'chi' => min((int)$stats['chi'], $maxChi),
            'max_chi' => $maxChi,
            'level' => $stats['level'],
            'realm_id' => $stats['realm_id'],
            'active_scroll_type' => $stats['active_scroll_type'] ?? null,
            'dao_path_key' => $stats['dao_path_key'] ?? null,
            'dao_path_name' => $stats['dao_path_name'] ?? null,
            'dao_alignment' => $stats['dao_alignment'] ?? null,
            'dao_element' => $stats['dao_element'] ?? null,
            'dao_attack_bonus_pct' => (float)($stats['dao_attack_bonus_pct'] ?? 0.0),
            'dao_defense_bonus_pct' => (float)($stats['dao_defense_bonus_pct'] ?? 0.0),
            'dao_max_chi_bonus_pct' => (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0),
            'dao_dodge_bonus' => (float)($stats['dao_dodge_bonus'] ?? 0.0),
            'dao_bonus_damage_pct' => (float)($stats['dao_bonus_damage_pct'] ?? 0.0),
            'dao_heal_on_hit_pct' => (float)($stats['dao_heal_on_hit_pct'] ?? 0.0),
            'dao_reflect_damage_pct' => (float)($stats['dao_reflect_damage_pct'] ?? 0.0),
            'dao_self_damage_pct' => (float)($stats['dao_self_damage_pct'] ?? 0.0),
            'dao_favored_tribulation' => $stats['dao_favored_tribulation'] ?? null,
            'manual_effects' => $stats['manual_effects'] ?? [],
        ];
    }

    /**
     * Sum percent bonuses from dao, bloodline, artifacts, titles, manuals, scroll — apply once.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function applyPooledPercentBonuses(array $stats, int $userId): array
    {
        $pool = [
            'attack_pct' => (float) ($stats['dao_attack_bonus_pct'] ?? 0.0),
            'defense_pct' => (float) ($stats['dao_defense_bonus_pct'] ?? 0.0),
            'max_chi_pct' => (float) ($stats['dao_max_chi_bonus_pct'] ?? 0.0),
        ];

        $bl = (new BloodlineService)->getPassiveBonuses($userId);
        $pool['attack_pct'] += (float) ($bl['attack_pct'] ?? 0.0);
        $pool['defense_pct'] += (float) ($bl['defense_pct'] ?? 0.0);
        $pool['max_chi_pct'] += (float) ($bl['max_chi_pct'] ?? 0.0);

        $art = (new ArtifactService)->getAggregatedCombatModifiers($userId);
        $pool['attack_pct'] += (float) ($art['passive_attack_pct'] ?? 0.0);
        $pool['defense_pct'] += (float) ($art['passive_defense_pct'] ?? 0.0);
        $pool['max_chi_pct'] += (float) ($art['passive_max_chi_pct'] ?? 0.0);

        $title = (new TitleService)->getEquippedBonuses($userId);
        $pool['attack_pct'] += (float) ($title['attack_pct'] ?? 0.0);
        $pool['defense_pct'] += (float) ($title['defense_pct'] ?? 0.0);
        $pool['max_chi_pct'] += (float) ($title['max_chi_pct'] ?? 0.0);

        $manual = (new CultivationManualService)->getActiveEffectsForUser($userId);
        $pool['attack_pct'] += (float) ($manual['passive_attack_pct'] ?? 0.0);
        $pool['defense_pct'] += (float) ($manual['passive_defense_pct'] ?? 0.0);
        $pool['max_chi_pct'] += (float) ($manual['passive_max_chi_pct'] ?? 0.0);

        $scroll = $stats['active_scroll_type'] ?? null;
        if ($scroll === 'minor_attack') {
            $pool['attack_pct'] += 0.08;
        } elseif ($scroll === 'minor_defense') {
            $pool['defense_pct'] += 0.08;
        } elseif ($scroll === 'vitality') {
            $pool['max_chi_pct'] += 0.10;
        }

        $composer = new StatBonusComposer;
        $stats = $composer->applyPercentPool($stats, $pool);

        $stats = $this->attachBloodlineCombatModifiers($stats, $userId);
        $stats = $this->attachArtifactCombatModifiers($stats, $userId);
        $stats['manual_effects'] = $manual;
        $stats['dao_dodge_bonus'] = (float) ($stats['dao_dodge_bonus'] ?? 0.0) + (float) ($manual['passive_dodge_pct'] ?? 0.0);

        return $this->ensureDaoMetaOnStats($stats);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function attachBloodlineCombatModifiers(array $stats, int $userId): array
    {
        $combat = (new BloodlineService)->getScaledAbilityCombat($userId);
        $red = (float) ($combat['damage_taken_reduction_pct'] ?? 0.0);

        return array_merge($stats, [
            'bloodline_outgoing_damage_pct' => (float) ($combat['damage_out_pct'] ?? 0.0),
            'bloodline_damage_taken_mult' => max(0.5, 1.0 - min(0.65, $red)),
            'bloodline_crit_chance_bonus' => (float) ($combat['crit_chance_bonus'] ?? 0.0),
            'bloodline_dodge_bonus' => (float) ($combat['dodge_bonus'] ?? 0.0),
            'bloodline_counter_bonus' => (float) ($combat['counter_bonus'] ?? 0.0),
            'bloodline_lifesteal_bonus_pct' => (float) ($combat['lifesteal_bonus_pct'] ?? 0.0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function attachArtifactCombatModifiers(array $stats, int $userId): array
    {
        $a = (new ArtifactService)->getAggregatedCombatModifiers($userId);
        $artRed = (float) ($a['taken_reduction_pct'] ?? 0.0);
        $artTaken = max(0.5, 1.0 - min(0.65, $artRed));

        return array_merge($stats, [
            'artifact_outgoing_damage_pct' => (float) ($a['damage_out_pct'] ?? 0.0),
            'artifact_damage_taken_mult' => $artTaken,
            'artifact_crit_chance_bonus' => (float) ($a['crit_chance_bonus'] ?? 0.0),
            'artifact_dodge_bonus' => (float) ($a['dodge_bonus'] ?? 0.0),
            'artifact_counter_bonus' => (float) ($a['counter_bonus'] ?? 0.0),
            'artifact_lifesteal_bonus_pct' => (float) ($a['lifesteal_bonus_pct'] ?? 0.0),
        ]);
    }

    /**
     * Active bloodline: percentage bonuses to attack, defense, max chi (after title).
     *
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function applyBloodlineBonuses(array $stats, int $userId): array
    {
        $bl = new BloodlineService();
        $b = $bl->getPassiveBonuses($userId);
        $combat = $bl->getScaledAbilityCombat($userId);
        $atk = (float)($b['attack_pct'] ?? 0.0);
        $def = (float)($b['defense_pct'] ?? 0.0);
        $mc = (float)($b['max_chi_pct'] ?? 0.0);

        if ($atk > 0.0 || $def > 0.0 || $mc > 0.0) {
            $attack = max(1, (int)round(($stats['attack'] ?? 0) * (1 + $atk)));
            $defense = max(0, (int)round(($stats['defense'] ?? 0) * (1 + $def)));
            $maxChi = max(1, (int)round(($stats['max_chi'] ?? 1) * (1 + $mc)));
        } else {
            $attack = (int)($stats['attack'] ?? 0);
            $defense = (int)($stats['defense'] ?? 0);
            $maxChi = max(1, (int)($stats['max_chi'] ?? 1));
        }

        $red = (float)($combat['damage_taken_reduction_pct'] ?? 0.0);
        $takenMult = max(0.5, 1.0 - min(0.65, $red));

        return [
            'attack' => $attack,
            'defense' => $defense,
            'chi' => min((int)$stats['chi'], $maxChi),
            'max_chi' => $maxChi,
            'level' => $stats['level'],
            'realm_id' => $stats['realm_id'],
            'active_scroll_type' => $stats['active_scroll_type'] ?? null,
            'dao_path_key' => $stats['dao_path_key'] ?? null,
            'dao_path_name' => $stats['dao_path_name'] ?? null,
            'dao_alignment' => $stats['dao_alignment'] ?? null,
            'dao_element' => $stats['dao_element'] ?? null,
            'dao_attack_bonus_pct' => (float)($stats['dao_attack_bonus_pct'] ?? 0.0),
            'dao_defense_bonus_pct' => (float)($stats['dao_defense_bonus_pct'] ?? 0.0),
            'dao_max_chi_bonus_pct' => (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0),
            'dao_dodge_bonus' => (float)($stats['dao_dodge_bonus'] ?? 0.0),
            'dao_bonus_damage_pct' => (float)($stats['dao_bonus_damage_pct'] ?? 0.0),
            'dao_heal_on_hit_pct' => (float)($stats['dao_heal_on_hit_pct'] ?? 0.0),
            'dao_reflect_damage_pct' => (float)($stats['dao_reflect_damage_pct'] ?? 0.0),
            'dao_self_damage_pct' => (float)($stats['dao_self_damage_pct'] ?? 0.0),
            'dao_favored_tribulation' => $stats['dao_favored_tribulation'] ?? null,
            'manual_effects' => $stats['manual_effects'] ?? [],
            'bloodline_outgoing_damage_pct' => (float)($combat['damage_out_pct'] ?? 0.0),
            'bloodline_damage_taken_mult' => $takenMult,
            'bloodline_crit_chance_bonus' => (float)($combat['crit_chance_bonus'] ?? 0.0),
            'bloodline_dodge_bonus' => (float)($combat['dodge_bonus'] ?? 0.0),
            'bloodline_counter_bonus' => (float)($combat['counter_bonus'] ?? 0.0),
            'bloodline_lifesteal_bonus_pct' => (float)($combat['lifesteal_bonus_pct'] ?? 0.0),
        ];
    }

    /**
     * Equipped + active artifacts: passive % stats and combat modifiers (stack after bloodline).
     *
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function applyArtifactBonuses(array $stats, int $userId): array
    {
        $svc = new ArtifactService();
        $a = $svc->getAggregatedCombatModifiers($userId);
        $atk = (float)($a['passive_attack_pct'] ?? 0.0);
        $def = (float)($a['passive_defense_pct'] ?? 0.0);
        $mc = (float)($a['passive_max_chi_pct'] ?? 0.0);

        if ($atk > 0.0 || $def > 0.0 || $mc > 0.0) {
            $attack = max(1, (int)round(($stats['attack'] ?? 0) * (1 + $atk)));
            $defense = max(0, (int)round(($stats['defense'] ?? 0) * (1 + $def)));
            $maxChi = max(1, (int)round(($stats['max_chi'] ?? 1) * (1 + $mc)));
        } else {
            $attack = (int)($stats['attack'] ?? 0);
            $defense = (int)($stats['defense'] ?? 0);
            $maxChi = max(1, (int)($stats['max_chi'] ?? 1));
        }

        $artRed = (float)($a['taken_reduction_pct'] ?? 0.0);
        $artTaken = max(0.5, 1.0 - min(0.65, $artRed));

        return [
            'attack' => $attack,
            'defense' => $defense,
            'chi' => min((int)$stats['chi'], $maxChi),
            'max_chi' => $maxChi,
            'level' => $stats['level'],
            'realm_id' => $stats['realm_id'],
            'active_scroll_type' => $stats['active_scroll_type'] ?? null,
            'dao_path_key' => $stats['dao_path_key'] ?? null,
            'dao_path_name' => $stats['dao_path_name'] ?? null,
            'dao_alignment' => $stats['dao_alignment'] ?? null,
            'dao_element' => $stats['dao_element'] ?? null,
            'dao_attack_bonus_pct' => (float)($stats['dao_attack_bonus_pct'] ?? 0.0),
            'dao_defense_bonus_pct' => (float)($stats['dao_defense_bonus_pct'] ?? 0.0),
            'dao_max_chi_bonus_pct' => (float)($stats['dao_max_chi_bonus_pct'] ?? 0.0),
            'dao_dodge_bonus' => (float)($stats['dao_dodge_bonus'] ?? 0.0),
            'dao_bonus_damage_pct' => (float)($stats['dao_bonus_damage_pct'] ?? 0.0),
            'dao_heal_on_hit_pct' => (float)($stats['dao_heal_on_hit_pct'] ?? 0.0),
            'dao_reflect_damage_pct' => (float)($stats['dao_reflect_damage_pct'] ?? 0.0),
            'dao_self_damage_pct' => (float)($stats['dao_self_damage_pct'] ?? 0.0),
            'dao_favored_tribulation' => $stats['dao_favored_tribulation'] ?? null,
            'manual_effects' => $stats['manual_effects'] ?? [],
            'bloodline_outgoing_damage_pct' => (float)($stats['bloodline_outgoing_damage_pct'] ?? 0.0),
            'bloodline_damage_taken_mult' => (float)($stats['bloodline_damage_taken_mult'] ?? 1.0),
            'bloodline_crit_chance_bonus' => (float)($stats['bloodline_crit_chance_bonus'] ?? 0.0),
            'bloodline_dodge_bonus' => (float)($stats['bloodline_dodge_bonus'] ?? 0.0),
            'bloodline_counter_bonus' => (float)($stats['bloodline_counter_bonus'] ?? 0.0),
            'bloodline_lifesteal_bonus_pct' => (float)($stats['bloodline_lifesteal_bonus_pct'] ?? 0.0),
            'artifact_outgoing_damage_pct' => (float)($a['out_pct'] ?? 0.0),
            'artifact_damage_taken_mult' => $artTaken,
            'artifact_crit_chance_bonus' => (float)($a['crit'] ?? 0.0),
            'artifact_dodge_bonus' => (float)($a['dodge'] ?? 0.0),
            'artifact_counter_bonus' => (float)($a['counter'] ?? 0.0),
            'artifact_lifesteal_bonus_pct' => (float)($a['lifesteal'] ?? 0.0),
        ];
    }

    /**
     * Single realm multiplier (controlled exponential scaling). Fallback 1.0 if column missing.
     */
    private function getRealmName(int $realmId): string
    {
        if (isset(self::$realmNameById[$realmId])) {
            return self::$realmNameById[$realmId];
        }

        try {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare('SELECT name FROM realms WHERE id = ? LIMIT 1');
            $stmt->execute([$realmId]);
            $name = $stmt->fetchColumn();
            self::$realmNameById[$realmId] = is_string($name) ? $name : '';
        } catch (\Throwable $e) {
            error_log('StatCalculator::getRealmName '.$e->getMessage());
            self::$realmNameById[$realmId] = '';
        }

        return self::$realmNameById[$realmId];
    }

    private function getRealmMultiplier(int $realmId): float
    {
        if (isset(self::$realmMultiplierById[$realmId])) {
            return self::$realmMultiplierById[$realmId];
        }
        try {
            $db = PdoDatabase::connection();
            $stmt = $db->prepare("SELECT * FROM realms WHERE id = ? LIMIT 1");
            $stmt->execute([$realmId]);
            $row = $stmt->fetch();
            if ($row && isset($row['name']) && is_string($row['name'])) {
                self::$realmNameById[$realmId] = $row['name'];
            }
            $m = ($row && isset($row['multiplier'])) ? (float)$row['multiplier'] : 1.0;
            self::$realmMultiplierById[$realmId] = $m;
            return $m;
        } catch (\Throwable $e) {
            error_log("StatCalculator::getRealmMultiplier " . $e->getMessage());
            self::$realmMultiplierById[$realmId] = 1.0;
            return 1.0;
        }
    }

    private function getEquippedItemBonusesSummary(int $userId): array
    {
        $itemService = new ItemService();
        $b = $itemService->getEquippedItemBonuses($userId);
        return ['attack' => $b['attack'], 'defense' => $b['defense'], 'hp' => $b['hp']];
    }

    public function getFinalAttack(int $userId): int
    {
        $stats = $this->calculateFinalStats($userId);
        return $stats['final']['attack'];
    }

    public function getFinalDefense(int $userId): int
    {
        $stats = $this->calculateFinalStats($userId);
        return $stats['final']['defense'];
    }

    public function getFinalMaxChi(int $userId): int
    {
        $stats = $this->calculateFinalStats($userId);

        return (int) $stats['final']['max_chi'];
    }

    public function getFinalChi(int $userId): array
    {
        $stats = $this->calculateFinalStats($userId);
        return [
            'chi' => $stats['final']['chi'],
            'max_chi' => $stats['final']['max_chi']
        ];
    }

    /**
     * Human-readable combat stat pipeline for character sheet UI.
     *
     * @return array<string, mixed>|null
     */
    public function getCombatStatBreakdown(int $userId): ?array
    {
        $base = $this->getBaseStats($userId);
        if ($base === null) {
            return null;
        }

        $realmId = (int)($base['realm_id'] ?? 1);
        $realmMult = $this->getRealmMultiplier($realmId);
        $realmLabel = RealmDisplay::label($this->getRealmName($realmId), $realmId);

        $afterEquipment = $this->applyEquippedItemBonuses($base, $userId);
        $afterDaoPath = $this->applyDaoBonuses($this->applyRealmTierMultiplier($afterEquipment));
        $afterBloodline = $this->applyBloodlineBonuses($afterDaoPath, $userId);
        $afterArtifacts = $this->applyArtifactBonuses($afterBloodline, $userId);
        $afterTitle = $this->applyTitleBonuses($afterArtifacts, $userId);
        $afterManuals = $this->applyCultivationManualBonuses($afterTitle, $userId);
        $afterScroll = $this->applyActiveScrollEffect($afterManuals);
        $final = $this->attributes()->applyToFinalStats($base, $afterScroll);

        $equipmentFlat = $this->getEquippedItemBonusesSummary($userId);
        $scrollType = $base['active_scroll_type'] ?? null;

        return [
            'realm_id' => $realmId,
            'realm_name' => $this->getRealmName($realmId),
            'realm_multiplier' => $realmMult,
            'active_scroll_type' => $scrollType,
            'active_scroll_label' => $this->describeActiveScroll((string)($scrollType ?? '')),
            'equipment_flat' => $equipmentFlat,
            'steps' => [
                [
                    'key' => 'base',
                    'label' => 'Base (database)',
                    'note' => 'Stored attack, defense, max chi before gear and realm scaling.',
                    'stats' => $this->snapshotCore($base),
                ],
                [
                    'key' => 'equipment',
                    'label' => 'After equipped items',
                    'note' => 'Flat bonuses from weapon, armor, and accessories.',
                    'stats' => $this->snapshotCore($afterEquipment),
                ],
                [
                    'key' => 'dao_path',
                    'label' => 'After '.$realmLabel.' tier ×' . rtrim(rtrim((string)round($realmMult, 4), '0'), '.') . ' + Dao Path',
                    'note' => $realmLabel.' multiplier and Dao percentage modifiers.',
                    'stats' => $this->snapshotCore($afterDaoPath),
                ],
                [
                    'key' => 'bloodline',
                    'label' => 'After active bloodline',
                    'note' => 'Ancestral bloodline passives (only your active lineage applies).',
                    'stats' => $this->snapshotCore($afterBloodline),
                ],
                [
                    'key' => 'artifacts',
                    'label' => 'After artifacts',
                    'note' => 'Equipped and active artifact modifiers.',
                    'stats' => $this->snapshotCore($afterArtifacts),
                ],
                [
                    'key' => 'title',
                    'label' => 'After equipped title',
                    'note' => 'Small percentage bonuses from your equipped title.',
                    'stats' => $this->snapshotCore($afterTitle),
                ],
                [
                    'key' => 'manuals',
                    'label' => 'After cultivation manuals',
                    'note' => 'Passive bonuses from owned / borrowed manuals.',
                    'stats' => $this->snapshotCore($afterManuals),
                ],
                [
                    'key' => 'scroll',
                    'label' => 'After active rune scroll (buffs)',
                    'note' => $scrollType ? $this->describeActiveScroll((string)$scrollType) : 'No combat scroll active.',
                    'stats' => $this->snapshotCore($afterScroll),
                ],
                [
                    'key' => 'attributes',
                    'label' => 'After primary attributes',
                    'note' => 'Strength, Agility, Vitality, Spirit, Soul, and Willpower — specialization, body, and synergies.',
                    'stats' => $this->snapshotCore($final),
                ],
            ],
            'primaries' => [
                'strength' => (int) ($base['strength'] ?? 5),
                'agility' => (int) ($base['agility'] ?? 5),
                'vitality' => (int) ($base['vitality'] ?? 5),
                'spirit' => (int) ($base['spirit'] ?? 5),
                'soul' => (int) ($base['soul'] ?? 5),
                'willpower' => (int) ($base['willpower'] ?? 5),
            ],
            'dao_path' => [
                'name' => (string)($final['dao_path_name'] ?? ''),
                'attack_pct' => (float)($final['dao_attack_bonus_pct'] ?? 0.0),
                'defense_pct' => (float)($final['dao_defense_bonus_pct'] ?? 0.0),
                'max_chi_pct' => (float)($final['dao_max_chi_bonus_pct'] ?? 0.0),
                'dodge_bonus' => (float)($final['dao_dodge_bonus'] ?? 0.0),
                'bonus_damage_pct' => (float)($final['dao_bonus_damage_pct'] ?? 0.0),
                'heal_on_hit_pct' => (float)($final['dao_heal_on_hit_pct'] ?? 0.0),
                'reflect_damage_pct' => (float)($final['dao_reflect_damage_pct'] ?? 0.0),
                'self_damage_pct' => (float)($final['dao_self_damage_pct'] ?? 0.0),
                'favored_tribulation' => $final['dao_favored_tribulation'] ?? null,
            ],
            'manual_effects' => $final['manual_effects'] ?? [],
            'final' => $final,
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @return array{attack: int, defense: int, max_chi: int, chi: int}
     */
    private function snapshotCore(array $stats): array
    {
        return [
            'attack' => (int)($stats['attack'] ?? 0),
            'defense' => (int)($stats['defense'] ?? 0),
            'max_chi' => (int)($stats['max_chi'] ?? 0),
            'chi' => (int)($stats['chi'] ?? 0),
        ];
    }

    private function describeActiveScroll(string $scroll): string
    {
        return match ($scroll) {
            '' => 'No combat scroll active.',
            'minor_attack' => 'Minor Attack Rune: +8% attack.',
            'minor_defense' => 'Minor Defense Rune: +8% defense.',
            'vitality' => 'Vitality Rune: +10% max chi.',
            default => 'Active rune: ' . $scroll,
        };
    }
}
