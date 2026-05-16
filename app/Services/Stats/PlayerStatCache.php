<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Services\StatCalculator;
use Illuminate\Support\Facades\Cache;

/**
 * Persistent cache for expensive final-stat calculations.
 */
final class PlayerStatCache
{
    public function __construct(
        private readonly StatCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(int $userId): array
    {
        if (! config('stats.cache_enabled', true)) {
            return $this->calculator->calculateFinalStats($userId);
        }

        $key = $this->key($userId);
        $ttl = max(30, (int) config('stats.cache_ttl_seconds', 300));

        return Cache::remember($key, $ttl, function () use ($userId): array {
            $this->calculator->clearFinalStatsCache();

            return $this->calculator->calculateFinalStats($userId);
        });
    }

    public function forget(int $userId): void
    {
        Cache::forget($this->key($userId));
        Cache::forget($this->key($userId).':breakdown');
        $this->calculator->clearFinalStatsCache();
    }

    private function key(int $userId): string
    {
        return config('stats.cache_prefix', 'player_stats').':'.$userId;
    }
}
