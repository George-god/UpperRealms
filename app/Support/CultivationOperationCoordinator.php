<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

require_once __DIR__.'/SeasonLifecycleLock.php';
require_once __DIR__.'/SeasonConnectionDisposer.php';
require_once __DIR__.'/GameplayOperationContext.php';
require_once __DIR__.'/GameplayFingerprint.php';
require_once __DIR__.'/GameplayOperationLedger.php';
require_once __DIR__.'/GameplayOperationResult.php';
require_once __DIR__.'/SeasonGameplayAdmission.php';

/** Same protocol for framework and legacy callers. Never retries a transaction internally. */
final class CultivationOperationCoordinator
{
    public function __construct(private readonly int $lockWait = 5)
    {
        if ($lockWait < 0 || $lockWait > 5) { throw new \InvalidArgumentException('Lock wait must be 0..5 seconds.'); }
    }

    public static function configured(): self
    {
        $environment = getenv('SEASON_SCORING_LOCK_WAIT_SECONDS');
        $value = function_exists('config') ? config('season_scoring.lock_wait_seconds', 5)
            : ($environment === false || $environment === '' ? 5 : $environment);
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('Invalid scoring lock wait.');
        }
        return new self((int) $value);
    }

    public function execute(PDO $db, int $actor, string $key, callable $gameplay, callable $admit, array $input = []): array
    {
        try {
            if (! GameplayFingerprint::validKey($key)) { throw new \InvalidArgumentException('A request key is required.'); }
            $fingerprint = GameplayFingerprint::cultivation($actor, $input);
        } catch (\InvalidArgumentException $error) {
            return ['success' => false, 'operation_status' => 'invalid_request', 'error' => $error->getMessage()];
        }
        $committed = false;
        $stage = 'lock';
        $result = null;
        $replay = false;
        $rolledBack = false;
        $maintenance = ['committed' => false, 'finalized' => [], 'optional_effect_failures' => []];
        try {
            SeasonLifecycleLock::run($db, function () use ($db, $actor, $key, $fingerprint, $gameplay, $admit,
                &$committed, &$stage, &$result, &$replay, &$maintenance, &$rolledBack): void {
                if ((int) $db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
                    throw new \RuntimeException('Exception-mode PDO is required.');
                }
                $oldWait = (int) $db->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
                $oldMetadataWait = (int) $db->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn();
                $primary = null;
                try {
                    $stage = 'session';
                    $db->exec('SET SESSION innodb_lock_wait_timeout = '.max(1, $this->lockWait));
                    $db->exec('SET SESSION lock_wait_timeout = '.max(1, $this->lockWait));
                    $stage = 'replay';
                    $row = GameplayOperationLedger::find($db, $actor, $key);
                    if ($row !== null) {
                        if ((int) $row['fingerprint_version'] !== GameplayFingerprint::VERSION
                            || ! hash_equals($row['fingerprint'], $fingerprint)) {
                            $result = ['success' => false, 'operation_status' => 'conflict', 'error' => 'Request key conflicts with original input.'];
                        } else {
                            $result = json_decode($row['outcome'], true, 512, JSON_THROW_ON_ERROR);
                            if (!is_array($result) || ($result['success'] ?? null) !== true) {
                                throw new \RuntimeException('Invalid durable gameplay outcome.');
                            }
                            $replay = true;
                            $committed = true;
                        }
                        $stage = 'release';
                        return;
                    } else {
                        $stage = 'maintenance';
                        $admission = $admit($db, $maintenance);
                        // Pin before the gameplay transaction, after confirmed maintenance.
                        $admitted = $admission['admitted_at'];
                        $stage = 'begin';
                        if (!$db->beginTransaction()) { throw new \RuntimeException('Begin not acknowledged.'); }
                        $stage = 'operation';
                        $season = $admission['season'];
                        if (!is_array($season) || (int) ($season['id'] ?? 0) < 1) {
                            throw new \RuntimeException('Eligible season required.');
                        }
                        $result = GameplayOperationContext::run($db, fn () => $gameplay($db));
                        if (! is_array($result) || ! array_key_exists('success', $result)) {
                            throw new \RuntimeException('Invalid gameplay result.');
                        }
                        if (!$result['success']) {
                            $stage = 'rollback';
                            try {
                                if (!$db->rollBack() || $db->inTransaction()) { throw new \RuntimeException('Rejection rollback not acknowledged.'); }
                                $rolledBack = true;
                            } catch (Throwable $error) {
                                SeasonLifecycleLock::quarantine($db);
                                throw new SeasonLifecycleLockException('rollback_uncertain', $error, true);
                            }
                            $result['operation_status'] = 'rejected';
                            $result['maintenance'] = $maintenance;
                            $stage = 'release';
                            return;
                        }
                        $scoring = ['season_id' => $season === null ? null : (int) $season['id'], 'points' => 0];
                        if ($result['success'] && $season !== null) {
                            $scoring = $this->score($db, $actor, $season, (int) $result['chi_gained']);
                        }
                        $result['scoring'] = $scoring;
                        $result['maintenance'] = $maintenance;
                        GameplayOperationLedger::record($db, $actor, $key, $fingerprint, $admitted,
                            $scoring['season_id'], $result, $scoring);
                    }
                    $stage = 'commit';
                    if (! $db->commit()) { throw new \RuntimeException('Commit not acknowledged.'); }
                    $committed = true;
                    $stage = 'release';
                } catch (Throwable $error) {
                    $primary = $error;
                    if (SeasonLifecycleLock::isUnsafe($db)) { throw $error; }
                    $uncertain = in_array($stage, ['session', 'begin', 'commit'], true);
                    try {
                        if ($db->inTransaction()) {
                            if (!$db->rollBack() || $db->inTransaction()) { throw new \RuntimeException('Rollback not acknowledged.'); }
                            $rolledBack = true;
                        }
                    } catch (Throwable $rollback) {
                        SeasonLifecycleLock::quarantine($db);
                        throw new SeasonLifecycleLockException('rollback_uncertain', $error, true, ['rollback' => $rollback]);
                    }
                    if ($uncertain) {
                        SeasonLifecycleLock::quarantine($db);
                        throw new SeasonLifecycleLockException($stage.'_uncertain', $error, true);
                    }
                    throw $error;
                } finally {
                    try {
                        if (!SeasonLifecycleLock::isUnsafe($db)) {
                            $db->exec('SET SESSION innodb_lock_wait_timeout = '.$oldWait);
                            $db->exec('SET SESSION lock_wait_timeout = '.$oldMetadataWait);
                        }
                    }
                    catch (Throwable $error) {
                        SeasonLifecycleLock::quarantine($db);
                        throw new SeasonLifecycleLockException('session_restore_failed', $primary ?? $error, true, ['session_restore' => $error]);
                    }
                }
            }, $this->lockWait);
        } catch (Throwable $error) {
            $unsafe = SeasonLifecycleLock::isUnsafe($db);
            $detached = false;
            if ($unsafe) {
                try { SeasonConnectionDisposer::discard($db); $detached = true; } catch (Throwable) { /* Quarantine remains authoritative. */ }
            }
            $effects = GameplayOperationContext::effects($committed);
            return array_merge($committed && $result !== null ? $result : ['success' => false,
                'error' => 'Cultivation could not be confirmed. Retry with the same request key.'], [
                'operation_status' => $committed ? 'committed_cleanup_failed' : ($unsafe ? 'uncertain'
                    : ($error instanceof SeasonLifecycleLockException && $error->reason === 'busy' ? 'busy' : 'rolled_back')),
                'operation_key' => $key, 'replayed' => $replay, 'connection_quarantined' => $unsafe,
                'connection_detached' => $detached, 'session_termination_confirmed' => false,
                'failure_stage' => $stage, 'optional_effect_failures' => $effects,
                'failure_reason' => $error instanceof SeasonLifecycleLockException ? $error->reason : 'required_operation_failed',
                'failure_diagnostics' => $error instanceof SeasonLifecycleLockException ? $error->safeDiagnostics()
                    : ['causes' => [['type' => $error::class, 'code' => (string) $error->getCode()]], 'cleanup' => []],
                'maintenance' => $replay ? ($result['maintenance'] ?? $maintenance) : $maintenance,
                'gameplay_committed' => $committed ? true : ($unsafe && !$rolledBack && in_array($stage, ['operation', 'commit', 'rollback'], true) ? null : false),
            ]);
        }
        $effects = GameplayOperationContext::effects($committed);
        return GameplayOperationResult::confirmed($result, $key, $replay, $committed, $effects);
    }

    private function score(PDO $db, int $actor, array $season, int $gain): array
    {
        $sid = (int) $season['id'];
        $points = max(1, (int) floor($gain / 10));
        $db->prepare('INSERT INTO season_rankings (season_id, user_id) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE season_id = season_id')->execute([$sid, $actor]);
        $db->prepare('UPDATE season_rankings SET score_cultivation = score_cultivation + ?
            WHERE season_id = ? AND user_id = ?')->execute([$points, $sid, $actor]);
        $stmt = $db->prepare('SELECT score_pvp, score_world_boss, score_cultivation, score_sect
            FROM season_rankings WHERE season_id = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$sid, $actor]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $row) { throw new \RuntimeException('Ranking missing.'); }
        $total = (int) floor((float) $season['weight_pvp'] * (int) $row['score_pvp']
            + (float) $season['weight_boss'] * (int) $row['score_world_boss']
            + (float) $season['weight_cultivation'] * (int) $row['score_cultivation']
            + (float) $season['weight_sect'] * (int) $row['score_sect']);
        $db->prepare('UPDATE season_rankings SET total_score = ? WHERE season_id = ? AND user_id = ?')
            ->execute([$total, $sid, $actor]);
        return ['season_id' => $sid, 'points' => $points, 'total_score' => $total, 'weights' => array_intersect_key($season,
            array_flip(['weight_pvp', 'weight_boss', 'weight_cultivation', 'weight_sect']))];
    }
}
