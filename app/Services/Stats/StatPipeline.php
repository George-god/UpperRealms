<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Services\StatCalculator;

/**
 * Named entry point for the combat stat pipeline (delegates to StatCalculator).
 */
final class StatPipeline
{
    public function __construct(
        private readonly StatCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculateFinalStats(int $userId): array
    {
        return $this->calculator->calculateFinalStats($userId);
    }

    public function calculator(): StatCalculator
    {
        return $this->calculator;
    }
}
