<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Optional debug logging for stats and combat (enabled via config/stats.php).
 */
final class GameDebug
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function logStatCalculation(int $userId, array $payload): void
    {
        if (! config('stats.debug_stats', false)) {
            return;
        }

        Log::channel('single')->debug('Stat calculation', [
            'user_id' => $userId,
            'base' => $payload['base'] ?? null,
            'final' => $payload['final'] ?? null,
            'modifiers' => $payload['modifiers'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function logCombat(string $event, int $userId, array $context = []): void
    {
        if (! config('stats.debug_combat', false)) {
            return;
        }

        Log::channel('single')->debug('Combat: '.$event, array_merge(['user_id' => $userId], $context));
    }
}
