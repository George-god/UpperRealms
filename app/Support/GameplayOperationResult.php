<?php

declare(strict_types=1);

namespace App\Support;

/** Transport metadata is separate from the original, durable gameplay outcome. */
final class GameplayOperationResult
{
    public static function confirmed(array $outcome, string $key, bool $replay, bool $committed, array $effects): array
    {
        return array_merge($outcome, [
            'operation_status' => $outcome['operation_status'] ?? ($replay ? 'replayed' : 'committed'),
            'operation_key' => $key, 'replayed' => $replay, 'gameplay_committed' => $committed,
            'optional_effect_failures' => $effects,
        ]);
    }
}
