# Season processing tests

Run in an isolated checkout without game credentials or cached configuration.
Phase 2C-1 keeps the existing SeasonService reward/ranking rules and adds lifecycle
locking. Command success reflects the service's complete result; partial lifecycle
failure returns nonzero through the unchanged command. The HTTP endpoint is unchanged.

Unit protocol tests run without a database:

```sh
php vendor/bin/phpunit --filter SeasonLifecycleLockTest
```

The Phase 1 schema regression and command tests still use in-memory SQLite/mocks.
The six Phase 1 MariaDB tests share the reviewed disposable fixture. Set
SEASON_TEST_MARIADB=1 and SEASON_TEST_DISPOSABLE=1 only for a dedicated disposable
loopback MariaDB instance on a non-3306 port. Random per-test databases are dropped
in teardown. Never use persistent game credentials or a forwarded production port.

See [the Phase 2C-1 runbook](season-lifecycle-locking-phase-2c1.md) for exact pinned
checkout/container commands, timeouts, cleanup, expected outcomes and recovery limits.