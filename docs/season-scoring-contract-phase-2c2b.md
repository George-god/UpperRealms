# Phase 2C-2B: seasonal scoring contract and baseline tests

Required starting commit: `bd9f1124f50e8eb0301adcf07ae2987bea8d3be9` on `development`.
Branch: `feature/season-scoring-contracts-phase-2c2b`.

This is documentation and test preparation only. **None of the proposed admission,
ledger, atomicity, or replay guarantees below exists merely because these tests pass.**
No application, endpoint, migration, schema import, scheduling, or reward code is changed.

## Baseline versus proposed behavior

| Area | Baseline observed in source / characterized by tests | Proposed implementation contract |
| --- | --- | --- |
| Scoring | Gameplay commits, then a void seasonal hook runs independently. Errors may be swallowed. | Gameplay, category delta, unchanged weighted total and terminal ledger entry commit together. |
| Identity | Seasonal hooks receive the beneficiary ID, not a request identity. | Authenticate actor independently. Persist actor and beneficiary separately; the accepting PvP defender need not win. |
| Operations | Repeating hooks increments again; there is no operation-key input. | Stable key for one intended action; retries reuse it. Distinct intended actions get distinct keys. |
| Season | Active lookup uses `ends_at > NOW()`, ignores `starts_at`, and catches lookup errors as null. | Under the lifecycle lock use DB time `t`: `status=active AND starts_at <= t AND t < ends_at`. Fail closed on database errors and inconsistent overlapping/future-active data. |
| Expiry | Finalization uses `< NOW()`, leaving an equality gap. | Expired means `ends_at <= t`; admission at expiry uses a replacement, not the old season. |
| No season | Cultivation can succeed without scoring or creating a replacement. | Finalize as necessary and initialize a replacement before gameplay writes. |
| Locking | Phase 2C-1 protects creation/finalization, but scoring bypasses it. | Use the exact same schema-derived lock, same authoritative PDO, bounded acquisition (maximum five seconds), no unlocked fallback. |
| Transactions | Challenge acceptance and battle simulation both begin transactions. | One root owner; subordinate battle and reward helpers do not begin, commit or roll back independently. |
| Commit loss | A real committed cultivation can be reported failed with no seasonal score. | Report unknown persistence and reconcile the ledger under the lock before re-execution. |

The `starts_at` gate, equality correction, and fail-closed handling are explicit
proposals requiring implementation review; they are not descriptions of current SQL.

## Stable keys, fingerprints and outcomes

- Require an opaque 128-bit key for each intended gameplay action. Preserve it across
  double submission, network retry and uncertain responses; do not create a fresh key on retry.
- Scope uniqueness to `(authenticated actor, action type, operation key)`.
  A key is not authentication or authorization. Recheck authorization before revealing a replay.
- Use a separate unique natural business identity for challenge execution, so a
  second operation key cannot execute the same challenge twice. Boss damage aggregates
  are not per-attack identities. Cultivation and donation are repeatable legitimate actions.
- SHA-256 fingerprint a versioned canonical encoding of validated business inputs:
  target ID, donation amount, technique options and equivalent action parameters.
  Use explicit integer/boolean fields and stable field order, not arbitrary JSON serialization.
  Exclude credentials, CSRF tokens, client clock, randomness and calculated results.
- Both implementations must share canonicalization. Retain its version so an old
  key is compared using its original fingerprint contract after upgrades.
- Same key + same fingerprint returns the original committed logical outcome and
  season. Same key + different fingerprint is a non-mutating conflict.
- Retain computed damage, beneficiary, score delta and immutable battle/result IDs.
  Never reroll rewards or randomness during replay. Keep credentials and unnecessary
  full logs out of the ledger. Validate serialized outcome size before committing.
- Successful operations receive terminal ledger records in the action transaction.
  Validation failures do not consume keys in this proposal. No separately committed
  pending reservation, queue or worker is proposed.

**Ledger design proposal, not a migration:** InnoDB table with actor/action/key primary
uniqueness, versioned fingerprint, nullable unique natural business key, season and
beneficiary IDs, category/delta, DB admission/completion timestamps and versioned JSON
outcome. No table is created by this phase. Initially retain uniqueness/fingerprint/
outcome records without automatic purge or cascading deletion. Deleting an old key
can enable duplicate execution. Privacy, account deletion, tombstones and eventual
retention periods remain approval gates.

## Lock, admission and transaction ownership

1. Authenticate and perform pure request-shape checks. No mutating lookup, repair,
   cooldown update or boss synchronization is allowed before acquiring the lock.
2. Reject unsafe PDOs and an existing transaction at the coordinator boundary.
   Acquire the existing `upperrealms:season:<schema hash>` advisory lock on the exact
   write PDO. No read replica, second lock PDO, transparent reconnect or recursion.
3. Read the committed ledger first. Replay precedes cooldown/challenge-status checks
   and season maintenance; a replay must not create a new season.
4. For a new operation, finalize expired seasons using an already-locked internal
   helper, confirm its commit, and then ensure a replacement using Phase 2C-1's
   already-locked creation helper. Do not call a public lock-acquiring method recursively.
5. Sample authoritative DB time after maintenance on the same connection. Use one
   timezone contract for both implementations and all existing season datetimes.
   Reject duplicate eligible seasons or inconsistent schedules; do not auto-repair data.
6. Pin admission time and season ID, then begin the owned gameplay transaction.
   Crossing expiry after admission does not move the action to another season.
7. Persist required gameplay writes, score, unchanged weighted total and terminal
   ledger outcome; commit once. End every rejection/exception path explicitly.
8. Release only after a confirmed transaction boundary. Non-database effects follow
   confirmed commit; their failure must not re-execute gameplay.

Maintenance and gameplay are different persistence boundaries: old-season rewards
can commit before replacement creation or gameplay fails. Report those facts separately.
If maintenance crosses another expiry, re-evaluate admission with a bounded loop.

Lock ordering: lifecycle mutex -> maintenance transaction completion -> gameplay
transaction -> natural source row -> user IDs ascending -> resource rows -> ranking
row -> terminal ledger write -> commit -> release. Audit other writers for inverse
orders; the advisory lock does not remove unrelated InnoDB deadlocks.

ChallengeService must own challenge state, stamina, battle persistence, rewards,
score and ledger. BattleService needs an internal owner-supplied path and a root
facade for standalone use. Avoid mixing native PDO ownership with Laravel transaction
counters or framework `afterCommit` callbacks that cannot see native transactions.

## Failure and recovery states (proposed)

| Situation | Required result |
| --- | --- |
| Acknowledged action commit and release | `complete`, `gameplay_committed=true`; retain outcome. |
| Matching terminal operation | Replay original outcome; no new gameplay or maintenance. |
| Fingerprint conflict | Non-mutating conflict. |
| Definite lock timeout | Retryable failure, `gameplay_committed=false`; no protected mutation. |
| Uncertain acquisition | No protected callback; `recovery_required`; quarantine and detach connection. |
| Confirmed gameplay rollback | `failed`, `gameplay_committed=false`; retry with same key. |
| Rollback false/throws/remains active | `recovery_required`; preserve original and rollback errors; no rollback claim. |
| Commit acknowledgement uncertain | `gameplay_committed=null`; no confirmed rewards/counts; reconcile first. |
| Commit confirmed but release fails | `recovery_required`, `gameplay_committed=true`; do not execute again. |
| Finalization committed, replacement failed | Partial maintenance, no gameplay; retry without duplicate season rewards. |
| Compound operation/cleanup failure | Preserve both; unresolved ownership overrides ordinary failed/partial status. |

On uncertainty, use Phase 2C-1 quarantine and connection disposal for both managers.
Detachment is not proof that a retained PDO's server session ended. Do not kill a
guessed session or reconnect in the protected operation. On a fresh connection,
reacquire the same mutex before resolving ledger absence: a matching terminal row
means replay; confirmed absence after ownership resolution permits same-key execution.
An unlocked absence read is not permission to retry an uncertain commit. Public
responses must not expose SQL, credentials, traces or attempted rewards as persisted.

## Participating side effects and newly visible preflight risk

Database reward/stamina/chi/battle/boss/sect writes must share the exact owned PDO.
Dao logging must receive that PDO explicitly. Audit activity rewards, titles,
bloodline unlocks and notifications before claiming action atomicity. Their current
swallowed-error behavior cannot silently become an atomicity guarantee.

Move file-cache invalidation after confirmed commit. Logs, response delivery and
external I/O cannot be rolled back. Do not retry all gameplay to compensate for them.

In addition to boss synchronization and leader-membership repair, **sect bonus
lookup initializes a sect base/buildings/NPCs**: `SectBaseService::getPassiveBonusesForSect()`
calls `initializeSectBase()` (`app/Services/SectBaseService.php:228-239`, with a matching
legacy implementation). These mutating reads and their transaction ownership need
explicit admission treatment. The tests use real helpers, not mocked bonus services.

## Characterization coverage and infrastructure limits

`SeasonScoringCharacterizationTest` executes both service implementations in private
processes against synthetic InnoDB fixtures. It covers all four categories, custom
weights and flooring, repeated increments, normal and zero-gain cultivation, no-season
loss of scoring, swallowed scoring failure, stale total after failure, real commit
acknowledgement loss, donations, standalone PvP rewards, nested challenge transactions,
boss damage/cooldown, killing-blow cap/rewards, boss reward brackets, and the
open transaction returned for a missing challenge (isolated by child-process exit).

Expected defects are named `existing_*` or described explicitly. They are passing
observations of unsafe baseline behavior, not expected-failure tests or fixed guarantees.
Phase 1 reward/rank assertions and Phase 2C-1 tests remain unchanged.

The fixture extracts only allowlisted CREATE TABLE definitions and six user/realm
extensions from bundled schema. It never imports `database_full.sql`, its CREATE
DATABASE/USE instructions or catalog/player INSERTs. Empty modifier catalogs mean
these tests do not validate every bonus combination. Only synthetic accounts are seeded.
Unexpected logged SQLSTATEs or ancillary diagnostics fail characterization instead of
silently legitimizing an incomplete fixture. Only the exact injected faults and known
nested-transaction messages are allowed. Re-run integration tests before trusting
unexecuted assertions.

The new harness additionally verifies a token stored inside a newly provisioned
disposable container before schema creation. Worker configuration repeats the opt-in,
schema, port and marker checks. Existing lifecycle tests retain their existing guards.

`SeasonScoringPdo` observes real SQL: `before/after_begin`, `before/after_commit`,
`before/after_rollback`, `before/after_acquire`, `before/after_release`,
`before/after_score_row`, `before/after_category`, `before/after_total`,
`before/after_replacement`, and `after_season_lookup`. A configured event pauses
once or throws once. `after_commit` occurs only after real `PDO::commit()` returns;
`after_acquire` follows real GET_LOCK execution (tests must separately verify ownership).
Rollback false/throw injection is available for future transaction-recovery tests.
Optional session `clock` supports DB-time boundary tests. No ledger/admission hooks
are invented; those must be added to the test probe when real coordinator SQL exists.

Barriers publish exact-stage readiness, wait for a named release, and expire after
ten seconds. Parent observation expires after eight seconds, children after 25.
Connection/statement/row and metadata lock limits remain bounded, as does cleanup.
Connection IDs are published for future independent-session contention observation.
The unit test proves two processes wait independently; it does not prove DB locking.
The MariaDB barrier test observes committed gameplay before category scoring using
a separate parent connection. It does not assert future transactional admission.

## Remaining gates and T01-T20 ownership

| Future tests | Implementation gate still absent |
| --- | --- |
| T01 normal atomic actions | Shared transaction owner and atomic helper graph for all four actions/both paths. Current normal formula coverage is only a baseline. |
| T02-T04 replay/conflict/natural identity | Ledger migration, unique keys, canonical payloads, authorized retained outcomes, stable client keys. |
| T05-T08 expiry and initialization | Locked DB-time admission, equality correction, already-locked finalization/replacement and partial-maintenance results. |
| T09-T13 concurrent scoring/finalization | Every score writer joins the same lifecycle protocol; independent connections plus observed waits. Existing lifecycle races do not prove scoring safety. |
| T14 timeout | All mutating preflight moves behind admission, including sect base initialization. |
| T15-T16 atomic failure | Gameplay/category/total/ledger rollback as one transaction; participating side-effect audit. |
| T17-T18 ambiguity/recovery | Real commit acknowledgement loss, fresh locked reconciliation, rollback/release compound faults and disposal. Current commit-loss test demonstrates the defect only. |
| T19 ownership | Challenge root and subordinate battle/helper contracts; all early returns close transactions. |
| T20 process/cache failures | Process-death recovery and post-commit cache handling without replaying mutations. |

Do not add tests that pretend these guarantees are implemented. Supersede explicitly
named characterizations when the corresponding production change is separately approved.

**Performance proposals, not measured results:** normal cultivation/donation lock-hold
p95 <=100 ms/p99 <=250 ms; PvP/boss p99 <=500 ms; normal-load request p99 <=1 s;
acquisition timeout rate <0.1%; mutex utilization <60%; representative season maintenance
target <=30 s. Measure wait, maintenance, transaction, hold and release separately.
At 50 ms mean hold, a 60% utilization budget is about 12 operations/s. Agree synthetic
participant counts and expected traffic before treating thresholds as rollout gates.
Boss reward fan-out and season finalization may dominate. No worker is justified yet.

Before the next behavioral phase: execute this new MariaDB matrix, resolve any fixture
errors, review the nested-PvP ownership defect and mutating lookups, agree timezone and
existing duplicate-season handling, and approve ledger retention/side-effect failure semantics.
Deployment topology/engines/session settings require separate authorized operational checks.
See `season-scoring-tests-phase-2c2b.md` for exact-commit execution and cleanup.
