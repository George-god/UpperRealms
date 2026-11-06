# Slice 1 isolated Linux validation

Do not run this against the persistent game database. These instructions do not deploy or migrate an application. All tests use synthetic tables in token-verified, random schemas inside a disposable MariaDB server. The test fixture creates the ledger using the migration's shared DDL; it never runs the legacy SQL import or application migrations.

## Transfer without publishing a branch

On the implementation machine, after the final commit, create an incremental bundle outside the repository:

```powershell
git bundle create ../UpperRealms-phase2c2c-slice1.bundle feature/season-scoring-phase-2c2c-slice1 ^bd9f1124f50e8eb0301adcf07ae2987bea8d3be9
git rev-parse feature/season-scoring-phase-2c2c-slice1
```

Transfer that bundle by your approved file-transfer method. No push is needed. On Linux, set these to actual local paths and the exact reviewed SHA from the handoff:

```bash
export SOURCE_REPO=/absolute/path/to/existing/UpperRealms
export SLICE_BUNDLE=/absolute/path/to/UpperRealms-phase2c2c-slice1.bundle
export EXPECTED_SLICE_SHA=REPLACE_WITH_REVIEWED_40_CHARACTER_SHA
test -d "$SOURCE_REPO/.git" && test -f "$SLICE_BUNDLE"
[[ "$EXPECTED_SLICE_SHA" =~ ^[0-9a-f]{40}$ ]] || exit 1
export VALIDATION_ROOT=$(mktemp -d /tmp/upperrealms-slice1.XXXXXXXX)
git clone --no-hardlinks --no-checkout "$SOURCE_REPO" "$VALIDATION_ROOT/repo"
cd "$VALIDATION_ROOT/repo"
git bundle verify "$SLICE_BUNDLE"
git fetch "$SLICE_BUNDLE" feature/season-scoring-phase-2c2c-slice1
test "$(git rev-parse FETCH_HEAD)" = "$EXPECTED_SLICE_SHA" || exit 1
git checkout --detach "$EXPECTED_SLICE_SHA"
test -z "$(git status --porcelain)" || exit 1
test ! -e .env && test ! -e bootstrap/cache/config.php || exit 1
git merge-base --is-ancestor bd9f1124f50e8eb0301adcf07ae2987bea8d3be9 HEAD
composer install --no-interaction --prefer-dist --no-scripts --no-plugins
php -r 'foreach (["mysql", "sqlite"] as $d) { if (!in_array($d, PDO::getAvailableDrivers(), true)) { throw new RuntimeException("Missing driver: ".$d); } }'
```

The source checkout is read only during this procedure; branch changes and fetched objects belong to the temporary clone. Do not cherry-pick dependencies again: the implementation branch explicitly contains their separate commits.

## Disposable container and identity

Run in one shell with `set -euo pipefail`. Docker may require `sudo` on this host. Nothing mounts the host database or source tree into the container.

```bash
set -euo pipefail
export SEASON_TEST_INSTANCE_TOKEN=$(openssl rand -hex 16)
export SCORING_CONTAINER="upperrealms-slice1-${SEASON_TEST_INSTANCE_TOKEN:0:12}"
if sudo docker container inspect "$SCORING_CONTAINER" >/dev/null 2>&1; then
  echo 'STOP: container already exists'; exit 1
fi
sudo docker run --detach --name "$SCORING_CONTAINER" \
  --label "upperrealms.scoring-test=$SEASON_TEST_INSTANCE_TOKEN" \
  --publish 127.0.0.1::3306 --tmpfs /var/lib/mysql:rw \
  --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 --env MARIADB_ROOT_HOST=% mariadb:11.4
ready=0
for attempt in $(seq 1 30); do
  if sudo docker exec "$SCORING_CONTAINER" healthcheck.sh --connect --innodb_initialized; then ready=1; break; fi
  sleep 1
done
test "$ready" = 1 || exit 1
test "$(sudo docker inspect --format '{{ index .Config.Labels "upperrealms.scoring-test" }}' "$SCORING_CONTAINER")" = "$SEASON_TEST_INSTANCE_TOKEN"
binding=$(sudo docker port "$SCORING_CONTAINER" 3306/tcp)
[[ "$binding" =~ ^127\.0\.0\.1:([0-9]+)$ ]] || exit 1
export SEASON_TEST_PORT=${BASH_REMATCH[1]}
test "$SEASON_TEST_PORT" != 3306 || exit 1
sudo docker exec --interactive "$SCORING_CONTAINER" mariadb --user=root <<SQL
CREATE DATABASE upperrealms_test_guard;
CREATE TABLE upperrealms_test_guard.instance (id INT PRIMARY KEY, token CHAR(32) NOT NULL) ENGINE=InnoDB;
INSERT INTO upperrealms_test_guard.instance VALUES (1, '$SEASON_TEST_INSTANCE_TOKEN');
SQL
test "$(sudo docker exec "$SCORING_CONTAINER" mariadb --user=root --batch --skip-column-names -e 'SELECT token FROM upperrealms_test_guard.instance WHERE id = 1;')" = "$SEASON_TEST_INSTANCE_TOKEN"
export APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=''
export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
export DB_HOST=127.0.0.1 DB_PORT="$SEASON_TEST_PORT" DB_USERNAME=root DB_PASSWORD=''
export CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
export SEASON_TEST_MARIADB=1 SEASON_TEST_DISPOSABLE=1 SEASON_TEST_USER=root SEASON_TEST_PASSWORD=''
export SEASON_SCORING_LOCK_WAIT_SECONDS=5
```

The harness independently verifies the token through the database before creating or deleting each random schema. A valid Docker label alone is insufficient.

## Bounded tests and measurements

```bash
timeout --signal=TERM --kill-after=15s 600s bash -euc '
  php vendor/bin/phpunit --do-not-cache-result tests/Unit/CultivationOperationCoordinatorTest.php tests/Unit/SeasonLifecycleLockTest.php tests/Unit/SeasonConnectionDisposerTest.php tests/Unit/SeasonScoringHarnessTest.php tests/Unit/SeasonScoringDiagnosticsTest.php tests/Unit/BloodlineUnlockExistenceTest.php
  php vendor/bin/phpunit --do-not-cache-result tests/Feature/CultivationOperationTest.php
  php vendor/bin/phpunit --do-not-cache-result tests/Feature/SeasonScoringCharacterizationTest.php
  php vendor/bin/phpunit --do-not-cache-result --filter "SeasonProcessingTest|SeasonConcurrencyTest|ProcessSeasonsCommandTest|RememberTokenMigrationTest"
' 2>&1 | tee "$VALIDATION_ROOT/validation.log"
git diff --check
git status --short
```

Do not call `artisan migrate`, `migrate:fresh`, Composer setup scripts, or the full legacy import. The new suite replaces only cultivation defect characterizations; 14 unchanged characterization cases still cover the other scoring paths.

New coverage includes both services, zero gain, weighted totals, realm caps and attribute growth, replay snapshots before cooldown, conflicts, missing-season replacement, future/overlapping eligibility, exact end time, an advancing DB clock across expiry, maintenance/gameplay commit separation, required helper failures, same-key and different-key workers, lifecycle finalization, process termination, commit acknowledgement loss, rollback/release failures, and five latency samples per implementation. Each worker is limited to 25 seconds, barriers to 10 seconds, parent barrier admission to 8 seconds, and the overall command to 600 seconds. The dedicated timeout test intentionally waits about five seconds per implementation.

Performance output reports worker-inclusive, operation, and mutex-hold median/max milliseconds. Five samples are a smoke measurement, not a reliable p95 or a production contention result. The audit's cultivation mutex-hold p95 <100ms and interactive p95 <250ms at an agreed population/arrival rate remain rollout gates requiring a separate, larger isolated load experiment. Do not claim those gates pass from unit doubles or these five samples. Store the log and actual environment details for review.

## Token-verified cleanup, including after test failure

Run this even if tests fail. If either identity check fails, stop and inspect; do not substitute a broad Docker cleanup command.

```bash
test "$SEASON_TEST_INSTANCE_TOKEN" = "$(sudo docker inspect --format '{{ index .Config.Labels "upperrealms.scoring-test" }}' "$SCORING_CONTAINER")" &&
test "$SEASON_TEST_INSTANCE_TOKEN" = "$(sudo docker exec "$SCORING_CONTAINER" mariadb --user=root --batch --skip-column-names -e 'SELECT token FROM upperrealms_test_guard.instance WHERE id = 1;')" &&
sudo docker rm --force "$SCORING_CONTAINER"
sudo docker ps -a --filter "name=$SCORING_CONTAINER"
```

Keep the temporary clone and log until review. No source checkout, existing branch, or persistent database should be deleted or modified by cleanup.
