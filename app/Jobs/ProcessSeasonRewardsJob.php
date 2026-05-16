<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SeasonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSeasonRewardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $seasonId,
    ) {}

    public function handle(SeasonService $seasons): void
    {
        if ($this->seasonId <= 0) {
            return;
        }

        if (method_exists($seasons, 'distributeSeasonRewards')) {
            $seasons->distributeSeasonRewards($this->seasonId);
        }
    }
}
