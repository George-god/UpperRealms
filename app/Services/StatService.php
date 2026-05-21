<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Stats\StatInvalidator;
use App\Services\Stats\StatPipeline;

/**
 * Central stat access facade.
 */
class StatService
{
    public function __construct(
        private readonly StatPipeline $pipeline,
        private readonly StatInvalidator $invalidator,
    ) {}

    public function calculateFinalStats(int $userId): array
    {
        return $this->pipeline->calculateFinalStats($userId);
    }

    public function invalidate(int $userId, string $reason = 'manual'): void
    {
        $this->invalidator->invalidate($userId, $reason);
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
