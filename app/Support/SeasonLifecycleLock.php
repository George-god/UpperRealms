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
        if (isset(self::$poisoned[$db]) || $db->inTransaction() || $timeout < 0 || $timeout > 5) {
            throw new SeasonLifecycleLockException('invalid_connection_state');
        }
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
            $acquire->execute([$name, $timeout]);
            $result = $acquire->fetchColumn();
            if ((string) $result !== '1') {
                throw new SeasonLifecycleLockException((string) $result === '0' ? 'busy' : 'acquisition_failed');
            }
        } catch (SeasonLifecycleLockException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new SeasonLifecycleLockException('database_error', $error);
        }

        self::$held[$name] = $db;
        $primary = null;
        try {
            return $operation();
        } catch (Throwable $error) {
            $primary = $error;
            throw $error;
        } finally {
            try {
                if ((string) $db->query('SELECT CONNECTION_ID()')->fetchColumn() !== $owner) {
                    throw new SeasonLifecycleLockException('connection_changed');
                }
                $release = $db->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$name]);
                if ((string) $release->fetchColumn() !== '1') {
                    throw new SeasonLifecycleLockException('release_failed');
                }
            } catch (Throwable $releaseError) {
                self::$poisoned[$db] = true;
                error_log('Season lifecycle lock release failed; discard connection ('.$releaseError::class.').');
                if ($primary === null) {
                    throw new SeasonLifecycleLockException('release_failed', $releaseError);
                }
                // Preserve the original operation exception if release also failed.
            } finally {
                unset(self::$held[$name]);
            }
        }
    }
}
