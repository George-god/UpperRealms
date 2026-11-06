# Phase 2C-2C Slice 1 review

Baseline: development `bd9f1124f50e8eb0301adcf07ae2987bea8d3be9`.
Local branch: `feature/season-scoring-phase-2c2c-slice1`.

Explicitly composed dependencies, without moving their original branch refs:

| Original commit | Local cherry-pick |
| --- | --- |
| scoring harness `4f753f0` | `068cd37` |
| sanitized diagnostics `ed94283` | `fc968be` |
| fixture correction `73917425897406da1afd9127ae0c38d7fb04a734` | `6332c18` |
| Bloodline existence fix `86f46ae943aef361f36955c8e31e1aea1023dc83` | `4063620` |

## Contract and review decisions

- The ledger uses a case-sensitive ASCII opaque key (16–80 characters), not a binary UUID column. The browser generates 128 random bits and persists the hex key in sessionStorage before sending. Actor and action are part of the unique identity. Records have no foreign-key cascades, expiry, cleanup job, historical backfill, or automatic down migration. An irreversible `down()` protects retained replay identities.
- Fingerprint v1 fixes actor, action, and the empty cultivation parameter object in canonical order. Cultivation currently has no gameplay parameters; the endpoint rejects unknown inputs, excludes transport/CSRF fields, and rejects conflicting header/body keys. Existing persisted fingerprint/version mismatches return conflict before any maintenance, gameplay, or cooldown. Future input/formula migrations must explicitly preserve old replay compatibility; unknown versions fail closed.
- The root coordinator owns native PDO transactions. It shares the exact PDO with helpers, refuses pre-existing transactions/unsafe sessions, and reuses the Phase 2C-1 schema mutex. The configurable advisory wait defaults to 5 seconds and is restricted to 0–5. InnoDB and metadata waits are bounded to 1–5 seconds while admitted work runs, then restored; these are per-lock waits, not a whole-request deadline.
- Authorized replay runs first under the mutex, without opening a gameplay transaction or checking current cooldown. It returns the original committed outcome and display snapshot. It does not re-run optional effects. Status/key/replay/cleanup metadata are transport fields outside the durable outcome.
- For a new key, admission finalizes expired active seasons (`ends_at <= DB time`) and initializes a replacement if needed, in a separate owned maintenance transaction. Required titles and DB notifications join that transaction. Maintenance commit is reported separately, including when gameplay subsequently rolls back. The schedule is re-evaluated at most twice; future-active schedules, overlapping eligible seasons, invalid intervals, missing schema or lookup errors fail closed. After confirmed maintenance, authoritative admission time and the unique season satisfying `starts_at <= t < ends_at` are pinned before the gameplay transaction starts. Gameplay never migrates to the next season because its execution crosses expiry.
- Successful cultivation, cave initialization, level/stat growth, category score, weighted total, and terminal ledger insert commit together. User rows are locked before cooldown/read-modify-write; then helper resources, ranking, ledger. Required helper errors propagate only inside the explicit cultivation/maintenance context; other gameplay callers retain their existing behavior. Schema-feature caches are bypassed and cached user-level results are neither used nor populated in that scope.
- Validation/cooldown rejection rolls back and does not consume the key. Same-key retries are allowed after a confirmed rejection. Successful zero-gain actions are retained and receive `max(1, floor(actualChiGain / 10))`. Weighted totals preserve PHP float multiplication followed by one floor over all categories.
- There are no automatic transaction retries. `busy`, `rolled_back`, `uncertain`, `committed_cleanup_failed`, `conflict`, `rejected`, `committed`, and `replayed` distinguish outcomes. `gameplay_committed` is true/false/null where known/unknown. Unsafe sessions are quarantined and detached; this does not prove server-session termination. Fresh requests must reacquire the same mutex and resolve the retained key; they must never substitute a new key merely because commit acknowledgement was lost. Original and cleanup diagnostics stay in the exception cause chain.
- Stat-cache invalidation is an optional callback after confirmed gameplay commit. Optional exceptions are returned separately and never roll back or rerun gameplay. Required season notifications are database writes inside maintenance, distinct from optional cache/queue effects. A crash after commit may omit optional invalidation; replay does not promise exactly-once external effects.
- Laravel currently routes classic cultivation through `LegacyPhpController` to the same legacy endpoint. Both standalone service implementations are converted; no separate Laravel cultivation endpoint exists to change. The endpoint no longer does fallible DB reads after commit. Missing keys are rejected, so other direct service callers must supply keys too.

## Limits requiring review before rollout

The global mutex serializes converted cultivation and season lifecycle only. PvP, boss, donations, and direct legacy scoring hooks remain unconverted. Their existing unlocked writes can still race with totals/finalization; this slice does not establish system-wide atomic scoring. Deployment must wait for independent Linux validation and the agreed rollout decision. Large season finalizations can hold the mutex longer than the interactive latency target. No production performance claim is made from protocol doubles or five smoke samples.

Cultivation now treats missing required schema/attribute/helper data as failure instead of silently disabling bonuses. Confirm migration completeness before rollout. Standalone legacy retains its existing optional Laravel attribute-growth behavior when the Laravel attribute classes are unavailable; it does not introduce a separate attribute engine. Integration runs through both implementations with the Laravel classes available.

The ledger preserves original response data indefinitely. Retention changes, a ledger rollback strategy, compatibility across future fingerprint versions, restoring optional effects after process death, and replacing the coarse mutex are separate review decisions. The browser preserves pending keys within a tab across reloads; sessionStorage is intentionally not a cross-device or permanent account recovery store. Browser interaction checks should verify network-error/reload retry, missing-key rejection, conflict display, and storage-unavailable handling before rollout.

## Validation evidence

Final local run: PHP 8.3.36, PHPUnit 11.5.55, **42 tests / 488 assertions**, 0.823 seconds, 14MB. Suites: `CultivationOperationCoordinatorTest`, `SeasonLifecycleLockTest`, `SeasonConnectionDisposerTest`, `SeasonScoringHarnessTest`, `SeasonScoringDiagnosticsTest`, and `BloodlineUnlockExistenceTest`. Expected lifecycle-release warning logs come from injected cleanup-failure tests. Protocol tests use doubles; the Bloodline regression uses SQLite memory; harness tests use private temporary files/child processes. No test uses a persistent database.

Local PHPUnit uses `--no-configuration --bootstrap ../upperrealms-test-bootstrap.php --do-not-cache-result`, with the six explicit unit file paths, and the pre-existing external PHP/vendor runtime. The bootstrap prepends this checkout's App and Tests namespaces. The initial sandbox run could not read the bootstrap; the approved retry only lifted that filesystem execution limitation for these safe tests.

PHP syntax passed for **43 changed PHP files across the complete baseline delta** (including dependencies). `node --check legacy/cultivation.js` and `git diff bd9f1124f50e8eb0301adcf07ae2987bea8d3be9 --check` passed. The full baseline-relative file manifest is [season-scoring-slice1-files.txt](season-scoring-slice1-files.txt). See the handoff for final commit SHAs.

Local execution is limited to those unit/static checks. No Laravel application bootstrap, network DB integration, Docker provisioning, persistent DB access, production change, push, PR, merge, or deployment is performed in Codex.

The disposable Linux suite and performance smoke measurements are **authored, not executed locally**. Commands and identity-checked cleanup are in [season-scoring-slice1-linux-validation.md](season-scoring-slice1-linux-validation.md). Cultivation defect assertions in the earlier characterization suite are superseded by the new protocol tests; other gameplay characterizations remain.
