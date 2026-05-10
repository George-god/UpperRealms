<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Stats\StatPipeline;

/**
 * Central stat access facade.
 */
class StatService
{
    public function __construct(
        private readonly StatPipeline $pipeline,
    ) {}

    public function calculateFinalStats(int $userId): array
    {
        return $this->pipeline->calculateFinalStats($userId);
    }

    public function getFinalAttack(int $userId): int
    {
        return $this->pipeline->calculator()->getFinalAttack($userId);
    }

    public function getFinalDefense(int $userId): int
    {
        return $this->pipeline->calculator()->getFinalDefense($userId);
    }

    public function getFinalMaxChi(int $userId): int
    {
        return $this->pipeline->calculator()->getFinalMaxChi($userId);
    }
}
