<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Central combat orchestration facade.
 * Keeps battle rules in the existing specialized services.
 */
class CombatService
{
    public function __construct(
        private readonly BattleService $battleService,
        private readonly PvEBattleService $pveBattleService,
        private readonly StatService $statService,
    ) {}

    public function simulatePvp(int $attackerId, int $defenderId, array $options = []): array
    {
        return $this->battleService->simulateBattle($attackerId, $defenderId, $options);
    }

    public function simulatePve(int $userId, int $npcId, bool $useDaoTechniques = true): array
    {
        return $this->pveBattleService->simulateBattle($userId, $npcId, $useDaoTechniques);
    }

    /**
     * PvE attack with post-battle persistence (chi reward clamp, clear active scroll).
     *
     * @return array<string, mixed>
     */
    public function runPveAttack(int $userId, int $npcId, bool $useDaoTechniques = true): array
    {
        $result = $this->pveBattleService->simulateBattle($userId, $npcId, $useDaoTechniques);
        if (empty($result['success'])) {
            return $result;
        }

        $finalStats = $this->statService->calculateFinalStats($userId);
        $userMaxChi = (int) $finalStats['final']['max_chi'];
        $userChiAfter = (int) $result['user_chi_after'];
        $chiReward = (int) $result['chi_reward'];

        if (($result['winner'] ?? '') === 'user' && $chiReward > 0) {
            $newChi = min($userMaxChi, max(0, $userChiAfter + $chiReward));
            DB::update(
                'UPDATE users SET chi = GREATEST(0, LEAST(?, ?)) WHERE id = ?',
                [$userMaxChi, $newChi, $userId]
            );
            $userChiAfter = $newChi;
        }

        DB::update('UPDATE users SET active_scroll_type = NULL WHERE id = ?', [$userId]);

        return array_merge($result, [
            'user_chi_after' => $userChiAfter,
            'user_max_chi' => $userMaxChi,
        ]);
    }

    public function simulateCustomEncounter(
        int $userId,
        string $enemyName,
        int $hp,
        int $attack,
        int $defense,
        int $rewardChi = 0,
        bool $useDaoTechniques = true
    ): array {
        return $this->pveBattleService->simulateCustomBattle(
            $userId,
            $enemyName,
            $hp,
            $attack,
            $defense,
            $rewardChi,
            $useDaoTechniques
        );
    }
}
