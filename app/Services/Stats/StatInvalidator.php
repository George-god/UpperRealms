<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Jobs\SyncCodexUnlocksJob;
use Illuminate\Support\Facades\Log;

/**
 * Central stat-cache invalidation (equipment, realm, bloodline, artifacts, etc.).
 */
final class StatInvalidator
{
    public function __construct(
        private readonly PlayerStatCache $statCache,
    ) {}

    public function invalidate(int $userId, string $reason = 'unknown'): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->statCache->forget($userId);

        if (config('stats.queue_codex_on_invalidate', false)) {
            $this->queueCodexSync($userId);
        }

        if (config('stats.debug_stats')) {
            Log::channel('single')->debug('Stat cache invalidated', [
                'user_id' => $userId,
                'reason' => $reason,
            ]);
        }
    }

    public function invalidateEquipment(int $userId): void
    {
        $this->invalidate($userId, 'equipment');
    }

    public function invalidateRealm(int $userId): void
    {
        $this->invalidate($userId, 'realm');
    }

    public function invalidateBloodline(int $userId): void
    {
        $this->invalidate($userId, 'bloodline');
    }

    public function invalidateArtifacts(int $userId): void
    {
        $this->invalidate($userId, 'artifacts');
    }

    public function queueCodexSync(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        SyncCodexUnlocksJob::dispatch($userId)->onQueue(config('stats.queue', 'default'));
    }
}
