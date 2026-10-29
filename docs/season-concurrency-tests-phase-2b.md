# Phase 2B: season concurrency and recovery characterization

Tests only, based on development `a68cb018e9b34c842b3397cbaaebbd031d297507`.
No application code, HTTP endpoint, lifecycle rules, scheduling or migrations change.
The six Phase 1 test methods and their reward/ranking assertions are retained.
Their random-database setup is shared through `Tests\Support\DisposableSeasonDatabase`.
The shared synthetic fixture now includes the seasons indexes used by the real schema
and a username column needed by the rankings-page service. It is still not a full
schema/migration test.

## What the tests assert

| Scenario | Forced interleaving and expected characterization |
| --- | --- |
| Two processors | First holds the real expired-season row lock; second must be observed waiting in `INNODB_LOCK_WAITS` on a different connection. After release, expect one finalization and one set of rewards. A future active season separates this from creation races. |
| Two creators (Laravel/Laravel, legacy/legacy, Laravel/legacy) | Both real empty active-season SELECTs finish before either INSERT. Expect **two active seasons**, an existing bug, rather than claiming creation is safe. |
| Processor vs legacy rankings-page service | Pause the processor after reward commit and empty replacement lookup. Pause `getPageData()` after its own empty lookup. Expect two active replacements; rewards remain single. This invokes the page's actual service path, not an HTTP request. |
| Scoring across expiry | Pause `onPvpWin()` after it selects the old active season. A different process finalizes it using a later session clock. Resume scoring: expect the ended season's score to change, with no score row in its replacement. Existing race; no new attribution rule is proposed. |
| Late finalization failure | A test-only trigger rejects the final status UPDATE after rank/currency/title/notification writes. Expect all those writes to roll back, but the PHP error log to survive. Remove the trigger and retry successfully. |
| Replacement failure | A test-only trigger rejects replacement INSERT after finalization commits. Expect service `ok=true`, rewards committed, and no active season. Retry with fault present still leaves none. Remove fault and retry: one active season, no repeated currency/title/notifications. Swallowed failure is an existing bug. |

The bug-characterization tests are intentionally green when those specific defects
are reproduced. They are not safety invariants. Once production behavior is fixed,
their expectations must be changed explicitly. Failed assertions are observations
to investigate, not automatically new application bugs.

## Real concurrency and limits

Symfony Process starts independent PHP processes, each with its own real PDO session.
A test-only PDOStatement subclass pauses *after* relevant real fetches; it neither
mocks SQL results nor replaces service logic. Credentials pass through stdin, not
command arguments or barrier files. Barriers contain only readiness/release markers
and connection IDs. Children have 20-second process timeouts, 12-second barrier
timeouts, 10-second statement limits, and 5-second InnoDB lock-wait limits. Parent
barrier/lock observation waits are bounded at 4 seconds; a slow or misconfigured VM
can fail these limits and must be diagnosed as a harness/environment issue.

Both parent PDO sessions (administrative and fixture) use a 5-second connection
timeout, a 10-second MariaDB statement limit, a 5-second InnoDB lock-wait limit,
and a 5-second metadata-lock wait limit (`lock_wait_timeout`). These are installed
before database creation or fixture DDL. Administrative lock-observation queries
and DROP DATABASE use the limited administrative session. The polling deadline is
checked between queries, so a query already in flight may exceed that deadline up
to its server-side limit. PDO's mysql connection timeout is not a universal socket
read deadline, and MariaDB statement interruption does not cover every DDL/server
failure. The external **180-second overall test-runner timeout**, followed by a
15-second kill grace period, is the final bound for both suites together. It must
be used even when all server-side limits are configured.

Parent database failures identify the operation and driver code. Cleanup attempts
worker, barrier-file, database, and Laravel teardown and reports accumulated errors
instead of silently discarding them. A cleanup failure means the run is not clean,
even if test assertions passed; destroy the disposable container after diagnosis.

The worker pins REPEATABLE READ. The expiry test advances individual MariaDB session
clocks instead of waiting for wall time; it characterizes an operation spanning
expiry, not multi-host clock drift. The minimal fixture cannot establish full-schema
foreign-key behavior. These scenarios do not prove safety for every interleaving,
isolation level, HTTP lifecycle, or crash/power-loss condition. Legacy PDO injection
uses reflection only in the child process and never opens its fallback connection.

The fixture requires explicit opt-in, an additional disposable-server confirmation,
a loopback host, a non-3306 port, random `upperrealms_season_test_*` database names,
and an isolated checkout without cached config. No DB_* credentials are used by the
MariaDB fixture/workers. Do not forward the test port to any other database server.

## Exact Linux development-VM commands

Run on `upperrealms-dev` with Docker, Git, Composer, PHP 8.3 CLI, pdo_mysql,
pdo_sqlite, mbstring, XML, and proc_open available. Use a separate checkout with no
game `.env`, credentials, or cached configuration. First set `REVIEWED_COMMIT` to
the exact 40-character SHA approved for execution. The originally reviewed commit
was `d6a2c18b5fab18e60559e2638c4cfccd276d3227`; it does **not** contain this follow-up.
After the follow-up is committed, substitute its SHA from the delivery report:
`export REVIEWED_COMMIT=<new-follow-up-commit-sha>`. Do not resolve the moving branch
tip as the expected SHA. A commit cannot embed its own SHA; this explicit input
avoids that circularity and prevents accidentally testing the older revision.

```bash
set -euo pipefail
command -v timeout >/dev/null || { echo 'GNU timeout is required.' >&2; exit 1; }
reviewed_commit=${REVIEWED_COMMIT:?Set the exact approved follow-up commit SHA first}
[[ "$reviewed_commit" =~ ^[0-9a-f]{40}$ ]] || { echo 'Expected a full lowercase commit SHA.' >&2; exit 1; }
qa_dir=$(mktemp -d /tmp/upperrealms-phase2b.XXXXXXXX)
git clone --no-checkout --single-branch --branch feature/season-concurrency-tests-phase-2b \
  https://github.com/George-god/UpperRealms.git "$qa_dir/repo"
cd "$qa_dir/repo"
git checkout --detach "$reviewed_commit"
[[ "$(git rev-parse HEAD)" == "$reviewed_commit" ]] || { echo 'Commit mismatch.' >&2; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo 'Checkout is not clean.' >&2; exit 1; }
# Verification must succeed before dependencies or tests execute.
composer install --no-interaction --prefer-dist --no-scripts

container=upperrealms-season-phase2b
if docker container inspect "$container" >/dev/null 2>&1; then
  echo 'Test container name already exists; inspect it before proceeding.' >&2
  exit 1
fi
docker run --detach --name "$container" \
  --label upperrealms.test=season-phase2b \
  --publish 127.0.0.1:33307:3306 \
  --tmpfs /var/lib/mysql:rw \
  --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
  --env MARIADB_ROOT_HOST=% mariadb:11.4
cleanup() {
  status=$?
  trap - EXIT
  if ! timeout --signal=TERM --kill-after=5s 20s \
    docker rm --force --volumes upperrealms-season-phase2b; then
    echo 'ERROR: disposable-container cleanup failed or timed out; inspect and remove it manually.' >&2
    status=1
  fi
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
for attempt in $(seq 1 60); do
  if docker exec "$container" healthcheck.sh --connect --innodb_initialized; then
    break
  fi
  sleep 1
done
docker exec "$container" healthcheck.sh --connect --innodb_initialized

# All settings below are synthetic/disposable, not game credentials.
export APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=''
export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
export CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
export SEASON_TEST_MARIADB=1 SEASON_TEST_DISPOSABLE=1
export SEASON_TEST_PORT=33307 SEASON_TEST_USER=root SEASON_TEST_PASSWORD=''

status=0
timeout --signal=TERM --kill-after=15s 180s bash -c \
  'php vendor/bin/phpunit --filter "SeasonProcessingTest|SeasonConcurrencyTest" &&
   php vendor/bin/phpunit --filter "ProcessSeasonsCommandTest|RememberTokenMigrationTest"' || status=$?
if [[ "$status" == 124 || "$status" == 137 ]]; then
  echo 'ERROR: overall test-runner timeout/kill; results are incomplete, not an application-race finding.' >&2
fi
[[ "$status" == 0 ]] || exit "$status"
printf 'Isolated checkout: %s\n' "$qa_dir/repo"
```

GNU coreutils `timeout` is required (available on Ubuntu 24.04). The 180-second
budget covers both test commands, not dependency installation/container startup.
`set -e` stops on failure; the EXIT trap still attempts bounded container removal,
preserves test failure status, and turns cleanup failure into a nonzero result.
The test account needs CREATE/DROP DATABASE, TRIGGER and PROCESS privileges in this
disposable instance. Do not grant these privileges to the application's normal user.
Do not run `game:process-seasons` manually as part of these instructions.

## Cleanup and diagnosis

Normal teardown stops children before dropping their random database and deleting
barrier files. The container uses tmpfs, not a game volume. The EXIT trap removes
the container and its anonymous volumes, including databases left by killed tests.
If the shell is killed before the trap runs, inspect the named test container and
its `upperrealms.test=season-phase2b` label, then remove only that container:

```bash
docker inspect --format '{{ index .Config.Labels "upperrealms.test" }}' upperrealms-season-phase2b
# Expected output: season-phase2b
timeout --signal=TERM --kill-after=5s 20s docker rm --force --volumes upperrealms-season-phase2b
```

Do not use a global database wipe or Docker prune. The temporary source checkout
can be removed after saving results. Barrier directories left by an abruptly killed
runner have the `upperrealms_barrier_` prefix and contain no credentials.

Opt-in disabled: 14 MariaDB tests skip (6 original, 8 new including data-provider
cases). Connection/setup errors, missing privileges, worker exits, barrier timeouts,
or an unobserved lock wait must not be reported as application race findings.

## Implementation-environment verification

The Windows authoring environment has PHP 8.3.36 but no available Docker/MariaDB
runtime. All five changed/new PHP files pass syntax validation; the PDO harness
classes load successfully. Test discovery and the no-opt-in run complete with
14 skips and no errors (using a workspace-only autoloader workaround for native
Windows file-readability checks). No MariaDB scenario was executed here, so the
outcomes above are explicit characterization expectations awaiting Linux execution,
not claimed experimental results. Phase 1's earlier VM results do not validate
these newly added scenarios.
