<?php

declare(strict_types=1);

namespace App\Support;

final class GameplayFingerprint
{
    public const VERSION = 1;

    public static function cultivation(int $actor, array $input = []): string
    {
        // Cultivation has no gameplay parameters. Reject extras rather than silently ignore them.
        if ($actor < 1 || $input !== []) { throw new \InvalidArgumentException('Invalid cultivation input.'); }
        return hash('sha256', json_encode(['version' => self::VERSION, 'actor' => $actor,
            'action' => 'cultivation', 'input' => (object) []], JSON_THROW_ON_ERROR));
    }

    public static function validKey(string $key): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{16,80}\z/D', $key) === 1;
    }
}
