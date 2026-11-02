# Phase 2C-1 corrective pass: lifecycle uncertainty and disposal

Branch: feature/season-lifecycle-locking-phase-2c1. This pass follows reviewed
1a8c49772407a55dd0bde6cce48f718a0c6d9f5d. Base lifecycle work remains based on
development a68cb018e9b34c842b3397cbaaebbd031d297507. No scoring, reward, ranking,
gameplay, endpoint, authentication, schema/migration or scheduling changes.

## Shared database assumption

Legacy execution under Laravel copies its configured MySQL/MariaDB connection.
Standalone legacy uses DB_*/MYSQL_* environment settings. Operators must verify both
use one authoritative server and the exact same schema. Advisory locks do not
coordinate separate primary servers. No production verification was performed.

## Lock and failure contract

The same schema-derived advisory-lock name is used by both services on their actual
PDO. Five-second acquisition timeout, no unlocked fallback, no recursive acquisition,
existing transaction rejection and native PDO ownership remain. Finalization keeps
its existing transaction and FOR UPDATE; replacement remains separate after commit
while the advisory lock is held. No second connection is opened for the lock.

Confirmed acquisition result 0 means busy; NULL means acquisition_failed. An exception
during GET_LOCK execution/result acknowledgement or a missing result means
acquisition_uncertain. Protected mutations never start in that case. The PDO is
quarantined and detached because the server may have acquired the lock.

SeasonLifecycleLockException retains the primary exception through getPrevious()
and cleanup exceptions in cleanupFailures. Operation plus release failure reports
operation_and_release_failed, not an ordinary partial failure. Original messages,
SQL, traces and credentials are never serialized in public service results.
failure_diagnostics contains only cause/cleanup exception types and driver codes;
cleanup_failures identifies affected stages.

Rollback checks both its boolean result and that inTransaction() becomes false.
A thrown/false/unresolved rollback preserves the primary operation failure, marks
rollback uncertainty, and quarantines the PDO. The helper does not release the
advisory lock while a transaction remains open: the unsafe session must be detached
and allowed to terminate. Compound cleanup errors are not silently lost.

Results:
- complete: acknowledged finalization and replacement, released lock; confirmed count.
- failed: definite non-commit failure with successful cleanup, including busy.
- partial: acknowledged commit, replacement failure, successful cleanup; confirmed
  processed count/detail retained. Retry skips ended seasons and repairs replacement.
- recovery_required: ambiguous commit/rollback, uncertain acquisition, unresolved
  release or unsafe session. connection_discard_required=true identifies unsafe use.
  finalization_committed and processed are null for ambiguous commit/rollback, and
  detail is empty unless finalization was acknowledged. Attempted rewards are not
  presented as persisted. Definite pre-transaction acquisition uncertainty keeps
  finalization_committed=false and processed=0 while lock ownership is uncertain.

A COMMIT exception is conservatively ambiguous even if a later rollback succeeds.
It must not cause automatic replay. A separate persisted-state inspection and a
controlled retry are required after the original session is gone. Tests explicitly
exercise real COMMIT followed by an injected acknowledgement exception, and an
exception before COMMIT followed by verified rollback; these are different persisted
outcomes even though both public outcomes conservatively require reconciliation.

## Disposal behavior and limits

SeasonConnectionDisposer detaches only matching PDO identities from all instantiated
Laravel connections and the legacy singleton. It never calls connection getters
that open new sessions. An empty Laravel connection is purged. With split read/write
sessions, only the matching unsafe reference is nulled; the other healthy session
is not disconnected/purged. A split connection with no write PDO fails closed rather
than reconnecting during this operation. Connection accessors reject quarantined PDOs
if a stale reference was reinstalled or detachment previously failed.

connection_detached means the managed references were detached, not proof of server
termination. session_termination_confirmed=false is intentional. PHP PDO has no
portable force-close API for a PDO object retained elsewhere (including exception
traces). We do NOT send KILL to a guessed or changed session ID. Such a retained
object can keep row/advisory locks alive until all references are dropped and the
nonpersistent session closes. Quarantine prevents reuse through the helper and
managed accessors; PHP cannot revoke arbitrary direct calls on an externally retained
PDO. End the request/process and reconcile uncertain transaction state before any
retry. Persistent connections and connection-switching proxies require operational
review; server detection of a broken network connection can be delayed.

Services account for uncertainty before detaching connections. They do not commit
or blindly retry an unsafe transaction as part of disposal. If detachment itself
fails, connection_detached=false and cleanup_failures includes connection_disposal;
the result still requires recovery. The original exception chain is preserved.

## Regression tests and remaining scope

Protocol unit tests verify acquisition acknowledgement exceptions, retained unsafe
references, original versus release exceptions, and recursion. A database-free test
uses actual Laravel DatabaseManager/Connection objects and the legacy singleton to
verify identity-based invalidation and preservation of unrelated/split sessions.

Twelve new real-MariaDB service cases (six faults times both services) inject:
replacement plus release failure, rollback false, rollback throw, acquisition after
real GET_LOCK, COMMIT before persistence, and acknowledgement loss AFTER real COMMIT.
They assert error_code/status/counts, cause identities, uncertain transaction state,
manager/singleton eviction, and that retained references may still own locks. After
the child exits they inspect persisted balances/ranks/status and perform controlled
retry, checking that acknowledged rewards are not duplicated. The injection lives
only in the test PDO and never changes production SQL or schema.

Existing lifecycle prevention assertions, Phase 1 rewards/rankings, timeout bounds,
opt-in and random disposable schemas remain. The scoring-across-expiry race is still
an explicit existing-bug characterization for Phase 2C-2. The public legacy endpoint
and fallback secret remain unchanged. Existing title/notification swallowing is
still deferred. Duplicate historical active seasons are not automatically repaired.

## Local validation

8 protocol/disposer unit tests passed, 31 assertions. The no-opt-in run skipped
28 MariaDB cases; none of their assertions executed locally. PHP syntax and diff
checks passed. MariaDB/Docker remains unavailable in the authoring environment.
The five command/migration feature tests remain blocked by the previously observed
native Windows Laravel bootstrap/cache writability error. Run every suite below on
the disposable Linux container before merge; earlier Phase 2B passes are insufficient.

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
  'php vendor/bin/phpunit --filter "SeasonLifecycleLockTest|SeasonConnectionDisposerTest|SeasonProcessingTest|SeasonConcurrencyTest" &&
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

Opt-in disabled: 28 MariaDB tests skip (6 original, 22 concurrency including data-provider
cases). Connection/setup errors, missing privileges, worker exits, barrier timeouts,
or an unobserved lock wait must not be reported as application race findings.
