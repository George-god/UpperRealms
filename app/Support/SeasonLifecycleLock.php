<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;
use WeakMap;

require_once __DIR__.'/SeasonLifecycleLockException.php';

/** Cooperative, single-MariaDB-server mutex. No framework or second connection. */
final class SeasonLifecycleLock
{
    private static array $held = [];

    private static ?WeakMap $poisoned = null;

    public static function quarantine(PDO $db): void
    {
        self::$poisoned ??= new WeakMap;
        self::$poisoned[$db] = true;
    }

    public static function isUnsafe(PDO $db): bool
    {
        return self::$poisoned !== null && isset(self::$poisoned[$db]);
    }

    public static function name(PDO $db): string
    {
        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        if (! is_string($schema) || $schema === '') {
            throw new SeasonLifecycleLockException('missing_database');
        }

        return 'upperrealms:season:'.substr(hash('sha256', $schema), 0, 40);
    }

    public static function run(PDO $db, callable $operation, int $timeout = 5): mixed
    {
        self::$poisoned ??= new WeakMap;
        if (self::isUnsafe($db)) {
            throw new SeasonLifecycleLockException('unsafe_connection', null, true);
        }
        if ($db->inTransaction() || $timeout < 0 || $timeout > 5) {
            throw new SeasonLifecycleLockException('invalid_connection_state');
        }
        $attempted = false;
        try {
            $name = self::name($db);
            if (isset(self::$held[$name])) {
                // Reject recursion, including a second PDO in this PHP process.
                throw new SeasonLifecycleLockException('recursive_acquisition');
            }
            $owner = (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn();
            $existing = $db->prepare('SELECT IS_USED_LOCK(?)');
            $existing->execute([$name]);
            if ((string) $existing->fetchColumn() === $owner) {
                throw new SeasonLifecycleLockException('recursive_acquisition');
            }
            $acquire = $db->prepare('SELECT GET_LOCK(?, ?)');
            $attempted = true;
            $acquire->execute([$name, $timeout]);
            $result = $acquire->fetchColumn();
            if ($result === false) {
                throw new \RuntimeException('Acquisition acknowledgement missing.');
            }
            if ((string) $result !== '1') {
                throw new SeasonLifecycleLockException((string) $result === '0' ? 'busy' : 'acquisition_failed');
            }
        } catch (SeasonLifecycleLockException $error) {
            throw $error;
        } catch (Throwable $error) {
            if ($attempted) {
                self::quarantine($db);
            }
            throw new SeasonLifecycleLockException($attempted ? 'acquisition_uncertain' : 'database_error', $error, $attempted);
        }

        self::$held[$name] = $db;
        $primary = null;
        try {
            return $operation();
        } catch (Throwable $error) {
            $primary = $error;
            if ($error instanceof SeasonLifecycleLockException && $error->discardConnection) {
                self::quarantine($db);
            }
            throw $error;
        } finally {
            try {
                if ($db->inTransaction()) {
                    throw new SeasonLifecycleLockException('transaction_unresolved');
                }
                if ((string) $db->query('SELECT CONNECTION_ID()')->fetchColumn() !== $owner) {
                    throw new SeasonLifecycleLockException('connection_changed');
                }
                $release = $db->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$name]);
                if ((string) $release->fetchColumn() !== '1') {
                    throw new SeasonLifecycleLockException('release_failed');
                }
            } catch (Throwable $releaseError) {
                self::quarantine($db);
                error_log('Season lifecycle lock release failed; discard connection ('.$releaseError::class.').');
                $failures = $primary instanceof SeasonLifecycleLockException ? $primary->cleanupFailures : [];
                $failures['release'] = $releaseError;
                // The original remains in the cause chain; cleanup failure is never log-only.
                throw new SeasonLifecycleLockException(
                    $primary === null ? 'release_failed' : 'operation_and_release_failed',
                    $primary ?? $releaseError, true, $failures,
                );
            } finally {
                unset(self::$held[$name]);
            }
        }
    }
}
