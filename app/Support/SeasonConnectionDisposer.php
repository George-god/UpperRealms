<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

require_once __DIR__.'/SeasonLifecycleLock.php';

/** Detach only this unsafe PDO; never KILL a guessed or unrelated server session. */
final class SeasonConnectionDisposer
{
    public static function discard(PDO $pdo): void
    {
        SeasonLifecycleLock::quarantine($pdo);
        if (class_exists(\Game\Config\Database::class, false)) {
            \Game\Config\Database::discardConnection($pdo);
        }
        if (function_exists('app') && app()->bound('db')) {
            $manager = app('db');
            foreach ($manager->getConnections() as $name => $connection) {
                $matched = false;
                if ($connection->getRawPdo() === $pdo) {
                    $connection->setPdo(null);
                    $matched = true;
                }
                if ($connection->getRawReadPdo() === $pdo) {
                    $connection->setReadPdo(null);
                    $matched = true;
                }
                // Purging a split connection with a healthy other session would close it too.
                if ($matched && $connection->getRawPdo() === null && $connection->getRawReadPdo() === null) {
                    $manager->purge($name);
                }
            }
        }
    }
}
