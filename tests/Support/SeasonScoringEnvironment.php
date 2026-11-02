<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use RuntimeException;

/** Test-only guard; never consults DB_*, .env, or a default game connection. */
final class SeasonScoringEnvironment
{
    public static function validate(array $config): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('SEASON_TEST_MARIADB') !== '1'
            || getenv('SEASON_TEST_DISPOSABLE') !== '1'
            || ! preg_match('/^[a-f0-9]{32}$/D', (string) getenv('SEASON_TEST_INSTANCE_TOKEN'))
            || ! preg_match('/^upperrealms_season_test_[a-f0-9]{16}$/D', $config['database'] ?? '')
            || ! is_int($config['port'] ?? null) || $config['port'] < 1 || $config['port'] > 65535
            || $config['port'] === 3306) {
            throw new RuntimeException('Unsafe disposable scoring configuration; no game connection fallback is allowed.');
        }
    }

    public static function verifyServer(PDO $pdo): void
    {
        $token = (string) getenv('SEASON_TEST_INSTANCE_TOKEN');
        if (! preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new RuntimeException('A disposable-instance token is required before schema setup.');
        }
        // Read only. Provision this marker inside the newly created container, never through this harness.
        $actual = $pdo->query('SELECT token FROM upperrealms_test_guard.instance WHERE id = 1')->fetchColumn();
        if (! is_string($actual) || ! hash_equals($token, $actual)) {
            throw new RuntimeException('Disposable-instance marker mismatch; refusing database setup.');
        }
    }
}
