<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/** Strict helper scope for cultivation only; other callers retain their existing behavior. */
final class GameplayOperationContext
{
    private static ?PDO $pdo = null;
    private static array $afterCommit = [];

    public static function active(): bool { return self::$pdo !== null; }

    public static function assertConnection(PDO $pdo): void
    {
        if (self::$pdo !== null && self::$pdo !== $pdo) {
            throw new \RuntimeException('Gameplay helper changed connection.');
        }
    }

    public static function required(Throwable $error): void
    {
        if (self::active()) { throw $error; }
    }

    public static function defer(callable $effect): void
    {
        if (self::active()) { self::$afterCommit[] = $effect; } else { $effect(); }
    }

    public static function run(PDO $pdo, callable $operation): mixed
    {
        if (self::active()) { throw new \RuntimeException('Nested gameplay scope.'); }
        self::$pdo = $pdo;
        self::$afterCommit = [];
        try { return $operation(); }
        finally { self::$pdo = null; }
    }

    public static function effects(bool $committed): array
    {
        $effects = self::$afterCommit;
        self::$afterCommit = [];
        $failures = [];
        if ($committed) {
            foreach ($effects as $effect) {
                try { $effect(); } catch (Throwable) { $failures[] = 'optional_effect_failed'; }
            }
        }
        return $failures;
    }
}
