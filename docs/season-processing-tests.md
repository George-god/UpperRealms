# Season processing — Phase 1

`game:process-seasons` delegates synchronously to the existing Laravel
`SeasonService::processEndedSeasons()`. A successful service result prints a count
and exits 0; a failed result or thrown exception prints a generic error and exits 1.
No HTTP endpoint, schedule, reward formula, or service implementation changes are
included. Do not run this command against a persistent game database during QA.

## Default suite

Use PHP 8.3 with the Composer lock-file dependencies and SQLite enabled:

```sh
composer install
php vendor/bin/phpunit
```

Use an isolated checkout with no game credentials or cached configuration.
`phpunit.xml` selects testing, in-memory SQLite, array sessions/cache and sync queues.
Command tests mock SeasonService and do not process game data. The migration test
runs fresh migrations against in-memory SQLite. The later remember-token migration
now owns that column on both the test and normal schema paths.

## Optional MariaDB characterization suite

MariaDB is required to test the real `FOR UPDATE`, `NOW()`, `INSERT IGNORE`, and
`DATE_ADD` behavior. These tests must not be pointed at a normal development or
production database. Provision a **disposable local MariaDB instance** first.

Explicitly set `SEASON_TEST_MARIADB=1`, `SEASON_TEST_DISPOSABLE=1`, `SEASON_TEST_PORT` (default 3307; 3306 is refused),
`SEASON_TEST_USER` (default root), and optionally `SEASON_TEST_PASSWORD` using only
that disposable instance's credentials. Then run:

```sh
php vendor/bin/phpunit --filter SeasonProcessingTest
```

The host is fixed to 127.0.0.1. Normal DB_* settings are not used for this suite.
Each test creates its own randomly named `upperrealms_season_test_*` database,
loads a minimal synthetic schema, and drops only that database during teardown.
The dedicated local account needs CREATE/DROP DATABASE permission. If a test
process is killed, destroy the disposable MariaDB instance to remove leftovers.
Never forward the loopback test port to another server.

Phase 2B shares this fixture with process-based concurrency tests. For exact
disposable-container setup, bounded waits, characterization expectations and cleanup,
see [season-concurrency-tests-phase-2b.md](season-concurrency-tests-phase-2b.md).

Coverage: expiry and equality boundary, multiple-season order, ties, all currency
tiers, positive-score eligibility, category titles, notifications, sequential
retries, default 28-day creation, existing active seasons, and transaction rollback.
These are service characterization tests, not full-schema migration tests or
multi-process concurrency tests.

## Remaining work

The public legacy endpoint remains enabled in Phase 1. No scheduler or command
overlap lock is installed. Existing swallowed title/notification/season-creation
errors remain unchanged, so command success means the existing service reported
success; it is not an independent guarantee that every side effect completed.
Concurrency protection, lifecycle error handling, HTTP removal, and operational
cutover need separate Phase 2 approval.
