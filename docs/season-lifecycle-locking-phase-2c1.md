# Phase 2C-1: shared season lifecycle locking

Base: development a68cb018e9b34c842b3397cbaaebbd031d297507. Test infrastructure
was selectively imported from reviewed Phase 2B 6e746689c1b8029017f27b5a6524ac7692c273bc.
No migrations, HTTP endpoint, auth, CSRF, scoring, gameplay transactions or scheduling changes.

## Deployment assumption (not verified on production)

Within Laravel, legacy/core/bootstrap.php copies the default MySQL/MariaDB connection
configuration into the legacy singleton. Standalone legacy execution instead reads
DB_*/MYSQL_* environment settings. Operators must verify the same authoritative
server and actual database/schema, not just matching database names. Advisory locks
are single-server cooperative locks, not a multi-primary distributed mutex. Confirm
no connection-switching proxy or statement-based replication incompatibility. This
implementation does not access production to establish those facts.

## Protocol and ownership

One PDO-only helper, app/Support/SeasonLifecycleLock.php, is autoloaded by Laravel
and explicitly included by the legacy service. A name derived from SELECT DATABASE()
is identical on both connections to the same schema and different for test schemas.
GET_LOCK uses a bounded five-second wait; only result 1 permits protected work.
Result 0 is busy, NULL is acquisition_failed; PDO exceptions are database_error.
No unlocked fallback exists. Public creation failures propagate to the caller.

Public entry points reject an existing transaction and process-local nested lock
ownership, including a second PDO. Private already-locked creation avoids nested
acquisition during finalization. An existing advisory lock on the same PDO is also
rejected. The helper uses the exact PDO for acquisition/release; native PDO operations
do not transparently reconnect. Connection identity is checked before release.

Ordering: acquire mutex -> existing transaction and FOR UPDATE -> commit or rollback
-> replacement check/insert -> release in finally. Rollback occurs before advisory
release. Replacement remains a separate autocommit operation, as previously. Reward
amounts, ranking order, expiry comparisons and 28-day creation parameters are unchanged.

Release failures are logged without SQL/credentials. They throw when there is no
original exception, otherwise the original exception is preserved. Such a PDO is
quarantined from future lifecycle operations and must be discarded by the host;
terminate the request/process rather than reuse it. Session termination frees MariaDB
advisory locks. A network partition may delay server detection; timeout never means
permission to bypass the lock. No lease expiry or second lock connection is used.

## Results and recovery

Processing retains ok, processed, detail and error, and adds status, error_code and
finalization_committed. Complete means the lock was released after replacement work.
Busy/connection/acquisition/finalization failures return ok=false. Diagnostic logs
identify lifecycle stage, exception class and SQLSTATE/code without returning raw
exception messages to the unchanged HTTP endpoint.

After an acknowledged finalization commit, replacement failure returns status=partial,
finalization_committed=true, and the actual processed/detail values. Rewards remain
committed. Retry under the mutex skips ended seasons and creates the missing active
season; tests verify no duplicate currency, titles or notifications.

An exception during COMMIT returns status=recovery_required and
finalization_committed=null, not a claim of rollback. Do not automatically replay
rewards on ambiguous acknowledgement. On the development/disposable environment,
inspect persisted season status, ranks, balances and notifications using a fresh
connection after the original session is gone. Operator reconciliation is required
if state is unclear. Release failure similarly reports recovery_required even if
writes committed. This is not an exactly-once event ledger or automatic failover.

Existing title/notification helper swallowing remains outside Phase 2C-1. Existing
duplicate seasons are not deleted or repaired automatically. The public legacy
endpoint and fallback secret remain unchanged and require a separate security task.

## Tests and explicit non-goals

Phase 1 reward/ranking assertions remain; the gold-column removal test is annotated
as intentional and asserts finalization_failed. New protocol unit tests cover 0/NULL
acquisition, exception release, release failure preserving the original exception,
quarantined connections, recursion across PDOs, existing transactions and changed
connection identity. Unit doubles test protocol contracts, not MariaDB behavior.

Real-process MariaDB tests observe an advisory-lock wait via PROCESSLIST and
IS_USED_LOCK. They assert one active season for Laravel/Laravel, legacy/legacy,
Laravel/legacy creators and processor versus legacy getPageData. They cover competing
processors, acquisition timeout without writes, release after exception, non-owner
release rejection, terminated owner, transaction rollback and partial failure/retry.
They use the actual service paths and native SQL, with bounded test barriers.

The scoring-across-expiry characterization intentionally remains an existing-bug
expectation. Score methods and their callers are untouched. Finalization does NOT
coordinate with seasonal scoring in this phase; Phase 2C-2 remains necessary. Fixing
creation races must not be presented as making the entire season lifecycle safe.

The original dual-empty-lookup barriers were replaced with a winner paused inside
the mutex and a contender observed waiting for that mutex. Waiting for two empty
lookups would deadlock under the corrected implementation. All parent, worker,
database, barrier and external runner bounds from reviewed Phase 2B are retained.
Minimal synthetic schema does not validate full schema import, multiple primaries,
all crash interleavings or persisted legacy installations.

Creation lock contention now propagates as a lifecycle exception rather than
returning a page with an assumed season. The existing page/controller may display
its normal error response until retried. No HTTP response redesign is included.

## Validation in authoring environment

PHP syntax checks and protocol unit tests can run here; MariaDB/Docker is unavailable.
No integration success is claimed without the disposable Linux run below. Earlier
Phase 2B VM passes do not validate these modified lifecycle tests. The three protocol
release-failure diagnostics in the unit run are deliberate injected failures.

Recorded local result: 6 protocol tests passed with 17 assertions; 16 disposable
MariaDB cases skipped. Ten changed/new PHP files passed syntax checks. The five
command/migration feature tests were attempted but all failed during Laravel
bootstrap because native Windows checks report bootstrap/cache unwritable. None
of their assertions executed. This is an environment blocker, not a passing
regression result; all suites must be rerun on upperrealms-dev before merging.

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
qa_dir=$(mktemp -d /tmp/upperrealms-phase2c1.XXXXXXXX)
git clone --no-checkout --single-branch --branch feature/season-lifecycle-locking-phase-2c1 \
  https://github.com/George-god/UpperRealms.git "$qa_dir/repo"
cd "$qa_dir/repo"
git checkout --detach "$reviewed_commit"
[[ "$(git rev-parse HEAD)" == "$reviewed_commit" ]] || { echo 'Commit mismatch.' >&2; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo 'Checkout is not clean.' >&2; exit 1; }
# Verification must succeed before dependencies or tests execute.
composer install --no-interaction --prefer-dist --no-scripts

container=upperrealms-season-phase2c1
if docker container inspect "$container" >/dev/null 2>&1; then
  echo 'Test container name already exists; inspect it before proceeding.' >&2
  exit 1
fi
docker run --detach --name "$container" \
  --label upperrealms.test=season-phase2c1 \
  --publish 127.0.0.1:33307:3306 \
  --tmpfs /var/lib/mysql:rw \
  --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
  --env MARIADB_ROOT_HOST=% mariadb:11.4
cleanup() {
  status=$?
  trap - EXIT
  if ! timeout --signal=TERM --kill-after=5s 20s \
    docker rm --force --volumes upperrealms-season-phase2c1; then
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
  'php vendor/bin/phpunit --filter "SeasonLifecycleLockTest|SeasonProcessingTest|SeasonConcurrencyTest" &&
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
its `upperrealms.test=season-phase2c1` label, then remove only that container:

```bash
docker inspect --format '{{ index .Config.Labels "upperrealms.test" }}' upperrealms-season-phase2c1
# Expected output: season-phase2c1
timeout --signal=TERM --kill-after=5s 20s docker rm --force --volumes upperrealms-season-phase2c1
```

Do not use a global database wipe or Docker prune. The temporary source checkout
can be removed after saving results. Barrier directories left by an abruptly killed
runner have the `upperrealms_barrier_` prefix and contain no credentials.

Opt-in disabled: 16 MariaDB tests skip (6 original, 10 concurrency including data-provider
cases). Connection/setup errors, missing privileges, worker exits, barrier timeouts,
or an unobserved lock wait must not be reported as application race findings.
