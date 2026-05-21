<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WorldBossService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWorldBossRewardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $bossId,
    ) {}

    public function handle(WorldBossService $worldBoss): void
    {
        if ($this->bossId <= 0) {
            return;
        }

        if (method_exists($worldBoss, 'distributeRewardsForBoss')) {
            $worldBoss->distributeRewardsForBoss($this->bossId);

            return;
        }

        if (method_exists($worldBoss, 'processBossRewards')) {
            $worldBoss->processBossRewards($this->bossId);
        }
    }
}
