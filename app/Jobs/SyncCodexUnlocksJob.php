<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Codex\CodexUnlockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCodexUnlocksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(CodexUnlockService $unlockService): void
    {
        if ($this->userId <= 0) {
            return;
        }

        $unlockService->syncForUser($this->userId);
    }
}
