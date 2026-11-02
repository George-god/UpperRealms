<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/** Named, one-shot process barriers. No elapsed sleep is evidence of database contention. */
final class SeasonScoringBarrier
{
    public static function point(string $directory, string $worker, string $event, float $timeout = 10): void
    {
        foreach ([$worker, $event] as $component) {
            if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $component)) {
                throw new RuntimeException('Invalid barrier component.');
            }
        }
        if (! is_dir($directory) || $timeout <= 0 || $timeout > 10) {
            throw new RuntimeException('Invalid bounded barrier configuration.');
        }
        $prefix = $directory.'/'.$worker.'.'.$event;
        if (file_put_contents($prefix.'.ready', '1') === false) {
            throw new RuntimeException('Cannot publish barrier: '.$event);
        }
        $deadline = hrtime(true) + (int) ($timeout * 1_000_000_000);
        do {
            clearstatcache(true, $prefix.'.release');
            if (is_file($prefix.'.release')) {
                return;
            }
            usleep(10000);
        } while (hrtime(true) < $deadline);
        throw new RuntimeException('Scoring barrier timed out: '.$worker.'.'.$event);
    }
}
