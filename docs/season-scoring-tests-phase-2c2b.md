# Phase 2C-2B isolated execution runbook

Use an isolated checkout and a **new disposable MariaDB 11.4 container** on
`upperrealms-dev`. Never reuse the game's database, volumes, credentials or `.env`.
No production access or season-processing Artisan invocation is needed.

## Pin the exact reviewed code first

The base is `bd9f1124f50e8eb0301adcf07ae2987bea8d3be9`. Before executing the script,
set `REVIEWED_COMMIT` to the full 40-character SHA approved for this branch's tests.
The snapshot must be available in the named remote branch before using this clone
recipe. Do not substitute a moving branch name or resolve its tip automatically.
After a follow-up is committed, replace `REVIEWED_COMMIT` with
its full new SHA and repeat the checkout/verification before dependency installation.

The script assumes PHP 8.3 with pdo_mysql, Composer, Git, Docker, OpenSSL and GNU
timeout. It creates no operation ledger and runs no application migrations against
MariaDB. The existing migration regression test uses in-memory SQLite only.

```bash
set -euo pipefail
for executable in timeout git composer php docker openssl; do
  command -v "$executable" >/dev/null || { echo "Missing: $executable" >&2; exit 1; }
done
baseline=bd9f1124f50e8eb0301adcf07ae2987bea8d3be9
reviewed_commit=${REVIEWED_COMMIT:?Set the exact approved commit SHA}
[[ "$reviewed_commit" =~ ^[0-9a-f]{40}$ ]] || { echo 'Invalid commit SHA.' >&2; exit 1; }
qa_dir=$(mktemp -d /tmp/upperrealms-scoring.XXXXXXXX)
git clone --no-checkout --single-branch --branch feature/season-scoring-contracts-phase-2c2b \
  https://github.com/George-god/UpperRealms.git "$qa_dir/repo"
cd "$qa_dir/repo"
git checkout --detach "$reviewed_commit"
[[ "$(git rev-parse HEAD)" == "$reviewed_commit" ]]
git merge-base --is-ancestor "$baseline" HEAD
[[ -z "$(git status --porcelain)" ]]
[[ ! -e .env && ! -e bootstrap/cache/config.php ]]
# Only after exact commit verification:
composer install --no-interaction --prefer-dist --no-scripts --no-plugins
php -r 'exit(in_array("mysql", PDO::getAvailableDrivers(), true) ? 0 : 1);'

export SEASON_TEST_INSTANCE_TOKEN
SEASON_TEST_INSTANCE_TOKEN=$(openssl rand -hex 16)
container="upperrealms-scoring-${SEASON_TEST_INSTANCE_TOKEN:0:12}"
if timeout --kill-after=5s 10s docker inspect "$container" >/dev/null 2>&1; then
  echo 'Unexpected pre-existing container; stop and inspect.' >&2
  exit 1
fi
cleanup() {
  status=$?
  trap - EXIT
  actual_label=$(timeout --kill-after=5s 10s docker inspect \
    --format '{{ index .Config.Labels "upperrealms.scoring-test" }}' "$container") || {
      echo "ERROR: cannot verify cleanup ownership for $container; inspect manually." >&2
      exit 1
    }
  if [[ "$actual_label" != "$SEASON_TEST_INSTANCE_TOKEN" ]]; then
    echo 'ERROR: cleanup label mismatch; refusing container removal.' >&2
    exit 1
  fi
  if ! timeout --signal=TERM --kill-after=5s 20s docker rm --force --volumes "$container"; then
    echo "ERROR: cleanup failed/timed out; manually inspect $container." >&2
    status=1
  fi
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
timeout --kill-after=10s 180s docker run --detach --name "$container" \
  --label "upperrealms.scoring-test=$SEASON_TEST_INSTANCE_TOKEN" \
  --publish 127.0.0.1:33308:3306 --tmpfs /var/lib/mysql:rw \
  --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 --env MARIADB_ROOT_HOST=% mariadb:11.4
ready=0
for attempt in $(seq 1 30); do
  if timeout --kill-after=2s 3s docker exec "$container" healthcheck.sh --connect --innodb_initialized; then
    ready=1
    break
  fi
  sleep 1
done
[[ "$ready" == 1 ]] || { echo 'Disposable server startup failed.' >&2; exit 1; }

# Provision the guard INSIDE the owned container. The PHP harness only reads it.
timeout --kill-after=5s 10s docker exec --interactive "$container" mariadb --user=root <<SQL
CREATE DATABASE upperrealms_test_guard;
CREATE TABLE upperrealms_test_guard.instance (id INT PRIMARY KEY, token CHAR(32) NOT NULL) ENGINE=InnoDB;
INSERT INTO upperrealms_test_guard.instance VALUES (1, '$SEASON_TEST_INSTANCE_TOKEN');
SQL
export APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=''
export DB_HOST=127.0.0.1 DB_PORT=33308 DB_USERNAME=root DB_PASSWORD=''
export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
export CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
export SEASON_TEST_MARIADB=1 SEASON_TEST_DISPOSABLE=1
export SEASON_TEST_PORT=33308 SEASON_TEST_USER=root SEASON_TEST_PASSWORD=''

status=0
timeout --signal=TERM --kill-after=15s 600s bash -c \
  'php vendor/bin/phpunit --filter "SeasonScoringHarnessTest|SeasonScoringDiagnosticsTest" &&
   php vendor/bin/phpunit tests/Feature/SeasonScoringCharacterizationTest.php &&
   php vendor/bin/phpunit --filter "SeasonLifecycleLockTest|SeasonConnectionDisposerTest|SeasonProcessingTest|SeasonConcurrencyTest|ProcessSeasonsCommandTest|RememberTokenMigrationTest"' \
  || status=$?
if [[ "$status" == 124 || "$status" == 137 ]]; then
  echo 'ERROR: runner timed out/was killed; results are incomplete.' >&2
fi
printf 'Isolated checkout retained for diagnostics: %s\n' "$qa_dir/repo"
exit "$status"
```

The overall 600-second limit covers tests, not installation/startup. Each worker is
bounded to 25 seconds; barriers to ten; parent barrier observation to eight. SQL
statement and metadata/row lock limits cover setup, observation and destruction.
An outer kill can bypass PHP teardown; the EXIT trap removes the container and its
tmpfs databases. Save results before closing the shell. Do not suppress cleanup errors.

No test infrastructure can identify every mistakenly opted-in server from its port
alone. New scoring tests therefore require both opt-in flags AND the matching
independently provisioned marker; never provision that marker on a persistent server.
Existing suites keep their original guards and run only against this same container.

## Interpreting results

- New characterization suite: 27 cases (13 methods x two implementations, plus one
  independent-connection SQL-barrier observation). Passing named defect cases means
  the defect was observed; it does not mean it was fixed.
- Harness unit suite: configuration/DDL guards, bounded missing-release failure, and
  two real child processes acknowledging separate barriers. No database required.
- Phase 1 seasonal reward/ranking and Phase 2C-1 tests are retained, not rewritten.
- Missing marker/driver, invalid config, SQLSTATE schema errors, child exit, barrier
  timeout and cleanup failure are test/environment failures, not lifecycle findings.
- Without disposable MariaDB opt-in, all 27 new integration cases are skipped.
  Never report those cases as passed based on syntax checks or unit tests.
- The filter above is a targeted regression run, not the full application suite.

## Safe SQL diagnostics after the diagnostic correction

Set `REVIEWED_COMMIT` to the exact new corrective commit supplied in the delivery
report, once that commit is available remotely. Repeat verification before running
dependencies/tests; the original `4f753f01e42c1ca75e4cc1b8d64b051394dd57c9`
does not contain this diagnostic correction. Do not change fixtures until the new
output has identified the missing objects on the disposable Linux run.

The independent worker now uses `SeasonScoringDiagnostics` to return structured
`diagnostic_details` alongside the unchanged `diagnostic_sqlstates` field and
`unexpected_diagnostic_count`. Strict assertions remain in place. A missing-object
failure includes metadata like this **illustrative synthetic example**, not a
finding about which objects are currently missing:

```json
{"sqlstate":"42S22","kind":"missing_column","driver_code":1054,"identifier":"u.synthetic_missing_column"}
```

Recognized MariaDB 1146/42S02 errors report the missing table name without the schema
prefix. Recognized 1054/42S22 errors report the missing column and optional qualifier.
Only ASCII identifiers of up to 64 characters per component are accepted; unusual
identifiers and values containing known worker credentials/instance token/app key
are redacted. Other SQL errors expose only SQLSTATE and a recognized driver number.
No raw message, SQL statement, bound values, database name, connection string, player
record, credential or log path is returned. Unrecognized message formats therefore
may identify only the error code, rather than echoing unsafe text.

Details are deduplicated and capped at 20 entries. The collector continues checking
for SQLSTATEs and unexpected errors after that cap and sets
`diagnostic_details_truncated`. Scanning is bounded to 256 KiB total and 8 KiB per
line, with at most 20 distinct SQLSTATEs. Read failures, nonregular log paths or
exceeded collection limits set `diagnostic_collection_errors`, which is asserted
empty: incomplete collection cannot silently pass. Repeated errors still contribute
to the unexpected count. The prior synthetic-fault/nested-transaction allowances
have not been expanded to accept any real SQL error.

Both characterization assertion sites append the safe metadata to PHPUnit failure
messages, so it remains visible after existing cleanup removes each worker's private
`diagnostics.log`. No persistent diagnostic artifact or additional database is created.
The collector's unit suite covers real-format missing-table/column messages,
redaction, generic SQL errors, deduplication/output limits, collection limits,
unchanged expected-fault classification and JSON transport from an independent PHP
process. It does not substitute for rerunning the six failed Linux cases.

## Cleanup after shell interruption

Use the exact container name printed by the run. Check its label against the token
from that run before removal. Never substitute another MariaDB container.

```bash
timeout --kill-after=5s 10s docker inspect --format \
  '{{ index .Config.Labels "upperrealms.scoring-test" }}' "$container"
# Compare with the run's SEASON_TEST_INSTANCE_TOKEN first.
timeout --signal=TERM --kill-after=5s 20s docker rm --force --volumes "$container"
```

No host game volume is attached. Normal PHP teardown drops only its randomly named
owned schema, stops children and removes private worker/cache artifacts. Container
removal also removes schemas left after crashes. The isolated checkout is retained
for diagnosis; remove only that explicitly identified temporary checkout when done.

## Local validation record

Prepared on Windows with PHP 8.3.36 and PHPUnit 11.5.55. Local PHP has pdo_sqlite,
but no pdo_mysql; Docker/MariaDB executables are unavailable. MariaDB integration
has not been executed. See the delivery report for the final static/unit totals.
An attempted Phase 1 regression run was blocked before its five test bodies by
Laravel's bootstrap-cache writability check in this Windows sandbox; that is an
environment limitation, not a passing regression run or a demonstrated game defect.
Pint's write checks and Git Bash startup are also restricted in this sandbox; do
not infer Linux formatting or shell-execution validation from local PHP/unit checks.
