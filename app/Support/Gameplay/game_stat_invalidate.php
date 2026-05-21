<?php

declare(strict_types=1);

/**
 * Bridge for legacy PHP services to invalidate Laravel stat cache without duplicating logic.
 */
function game_stat_invalidate(int $userId, string $reason = 'legacy'): void
{
    if ($userId <= 0 || ! function_exists('app')) {
        return;
    }

    try {
        if (app()->bound(\App\Services\Stats\StatInvalidator::class)) {
            app(\App\Services\Stats\StatInvalidator::class)->invalidate($userId, $reason);
        }
    } catch (\Throwable $e) {
        error_log('game_stat_invalidate: '.$e->getMessage());
    }
}
