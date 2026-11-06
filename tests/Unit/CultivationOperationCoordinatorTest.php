<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CultivationOperationCoordinator;
use App\Support\GameplayFingerprint;
use App\Support\GameplayOperationContext;
use App\Support\SeasonLifecycleLock;
use App\Support\SeasonGameplayAdmission;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Protocol doubles: no application bootstrap, network, filesystem or database connection. */
final class CultivationOperationCoordinatorTest extends TestCase
{
    private const KEY = 'cultivation_test_key_0001';

    private function execute(OperationPdo $db, ?callable $action = null): array
    {
        return (new CultivationOperationCoordinator)->execute($db, 1, self::KEY, $action ?? static function () use ($db): array {
            $db->gameplay++;
            return ['success' => true, 'chi_gained' => $db->gain];
        }, admit: static function () use ($db): array {
            if (count($db->seasons) !== 1) { throw new RuntimeException('Ambiguous season'); }
            return ['admitted_at' => $db->query('SELECT NOW(6)')->fetchColumn(), 'season' => $db->seasons[0]];
        });
    }

    public function test_atomic_success_replay_precedes_gameplay_and_scoring(): void
    {
        $db = new OperationPdo;
        $first = $this->execute($db);
        $second = $this->execute($db, fn () => $this->fail('Replay executed gameplay or cooldown'));
        self::assertSame('committed', $first['operation_status']);
        self::assertSame('replayed', $second['operation_status']);
        self::assertSame($first['scoring'], $second['scoring']);
        self::assertSame(1, $db->gameplay);
        self::assertSame(2, $db->points);
        self::assertSame(4, $db->total);
        self::assertFalse($db->transaction);
        self::assertLessThan(array_search('begin', $db->events), array_search('admission', $db->events));
    }

    public function test_zero_gain_is_durable_and_missing_eligibility_fails_closed(): void
    {
        foreach ([true, false] as $eligible) {
            $db = new OperationPdo;
            $db->gain = 0;
            if (!$eligible) { $db->seasons = []; }
            $result = $this->execute($db);
            self::assertSame($eligible, $result['success']);
            self::assertSame($eligible ? 1 : 0, $db->points);
            self::assertSame($eligible, $db->ledger !== null);
        }
    }

    public function test_conflict_and_version_mismatch_never_execute_gameplay(): void
    {
        foreach (['fingerprint', 'fingerprint_version'] as $field) {
            $db = new OperationPdo;
            $this->execute($db);
            $db->ledger[$field] = $field === 'fingerprint' ? str_repeat('0', 64) : 2;
            $result = $this->execute($db, fn () => $this->fail('Conflict ran gameplay'));
            self::assertSame('conflict', $result['operation_status']);
            self::assertSame(1, $db->gameplay);
        }
    }

    public function test_all_required_write_failures_roll_back_gameplay_score_and_ledger(): void
    {
        foreach (['category', 'total', 'ledger'] as $fault) {
            $db = new OperationPdo;
            $db->fault = $fault;
            $result = $this->execute($db);
            self::assertSame('rolled_back', $result['operation_status']);
            self::assertSame(0, $db->gameplay);
            self::assertSame(0, $db->points);
            self::assertNull($db->ledger);
            self::assertFalse($db->transaction);
        }
    }

    public function test_ambiguous_eligibility_and_lock_timeout_do_not_start_gameplay(): void
    {
        foreach (['overlap', 'busy'] as $fault) {
            $db = new OperationPdo;
            if ($fault === 'overlap') { $db->seasons[] = $db->seasons[0]; } else { $db->acquire = 0; }
            self::assertFalse($this->execute($db)['success']);
            self::assertSame(0, $db->gameplay);
            self::assertNull($db->ledger);
        }
    }

    public function test_commit_acknowledgement_loss_quarantines_and_never_reruns(): void
    {
        $db = new OperationPdo;
        $db->fault = 'after_commit';
        $result = $this->execute($db);
        self::assertSame('uncertain', $result['operation_status']);
        self::assertTrue(SeasonLifecycleLock::isUnsafe($db));
        self::assertNotNull($db->ledger);
        $this->execute($db, fn () => $this->fail('Unsafe PDO reused'));
        self::assertSame(1, $db->gameplay);
        // A fresh connection resolves uncertainty using the committed ledger.
        $fresh = new OperationPdo;
        $fresh->ledger = $db->ledger;
        self::assertSame('replayed', $this->execute($fresh)['operation_status']);
        self::assertSame(0, $fresh->gameplay);
    }

    public function test_rollback_uncertainty_and_confirmed_commit_release_failure_are_distinct(): void
    {
        $db = new OperationPdo;
        $db->fault = 'rollback';
        $result = $this->execute($db, static fn () => throw new RuntimeException('required helper'));
        self::assertSame('uncertain', $result['operation_status']);
        self::assertTrue($result['connection_quarantined']);
        $db = new OperationPdo;
        $db->release = 0;
        $result = $this->execute($db);
        self::assertTrue($result['success']);
        self::assertSame('committed_cleanup_failed', $result['operation_status']);
        self::assertTrue($result['connection_quarantined']);
    }

    public function test_required_helpers_and_optional_effects_have_different_failure_semantics(): void
    {
        $db = new OperationPdo;
        $effects = 0;
        $result = $this->execute($db, static function () use (&$effects): array {
            GameplayOperationContext::defer(static function () use (&$effects): void { $effects++; throw new RuntimeException('cache'); });
            return ['success' => true, 'chi_gained' => 0];
        });
        self::assertTrue($result['success']);
        self::assertSame(1, $effects);
        self::assertSame(['optional_effect_failed'], $result['optional_effect_failures']);
        $db = new OperationPdo;
        $result = $this->execute($db, static function () use (&$effects): array {
            GameplayOperationContext::defer(static function () use (&$effects): void { $effects++; });
            GameplayOperationContext::required(new RuntimeException('required'));
            return [];
        });
        self::assertSame('rolled_back', $result['operation_status']);
        self::assertSame(1, $effects);
        self::assertFalse(GameplayOperationContext::active());
    }

    public function test_invalid_keys_and_root_transaction_are_rejected_without_mutation(): void
    {
        $db = new OperationPdo;
        self::assertSame('invalid_request', (new CultivationOperationCoordinator)->execute($db, 1, '', fn () => [], fn () => [])['operation_status']);
        $db->transaction = true;
        self::assertFalse($this->execute($db)['success']);
        self::assertTrue($db->transaction);
        self::assertSame([], $db->events);
        self::assertSame(GameplayFingerprint::cultivation(1), GameplayFingerprint::cultivation(1));
        self::assertNotSame(GameplayFingerprint::cultivation(1), GameplayFingerprint::cultivation(2));
    }

    public function test_validation_rejection_does_not_consume_the_key(): void
    {
        $db = new OperationPdo;
        $result = $this->execute($db, static fn () => ['success' => false, 'cooldown_remaining' => 5]);
        self::assertSame('rejected', $result['operation_status']);
        self::assertFalse($result['gameplay_committed']);
        self::assertNull($db->ledger);
        self::assertTrue($this->execute($db)['success']);
    }

    public function test_maintenance_initialization_and_failure_are_separate_from_gameplay(): void
    {
        $db = new OperationPdo;
        $db->seasons = [];
        $result = (new CultivationOperationCoordinator)->execute($db, 1, self::KEY,
            static fn () => throw new RuntimeException('gameplay failed'),
            static function (PDO $pdo, array &$maintenance): array {
                return SeasonGameplayAdmission::prepare($pdo, static fn () => null, $maintenance);
            });
        self::assertFalse($result['success']);
        self::assertTrue($result['maintenance']['committed']);
        self::assertCount(1, $db->seasons);
        self::assertNull($db->ledger);
        self::assertFalse($db->transaction);
    }

    public function test_future_only_and_overlapping_schedules_fail_before_gameplay(): void
    {
        foreach (['future', 'overlap'] as $case) {
            $db = new OperationPdo;
            if ($case === 'future') { $db->seasons[0]['has_started'] = 0; }
            else { $db->seasons[] = $db->seasons[0]; }
            $result = (new CultivationOperationCoordinator)->execute($db, 1, self::KEY,
                fn () => $this->fail('Unsafe schedule admitted gameplay'),
                static function (PDO $pdo, array &$maintenance): array {
                    return SeasonGameplayAdmission::prepare($pdo, static fn () => null, $maintenance);
                });
            self::assertFalse($result['success']);
            self::assertSame(['admission'], $db->events);
            self::assertFalse($result['maintenance']['committed']);
        }
    }
}

final class OperationPdo extends PDO
{
    public bool $transaction = false;
    public int $gameplay = 0, $points = 0, $total = 0, $gain = 29, $acquire = 1, $release = 1;
    public ?array $ledger = null;
    public string $fault = '';
    public array $events = [], $snapshot = [];
    public array $seasons = [['id' => 1, 'starts_at' => '2026-11-01', 'ends_at' => '2026-12-01', 'has_started' => 1, 'has_ended' => 0, 'weight_pvp' => 1, 'weight_boss' => 0.01, 'weight_cultivation' => 2, 'weight_sect' => 5]];
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return PDO::ERRMODE_EXCEPTION; }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool
    {
        $this->events[] = 'begin';
        $this->snapshot = [$this->gameplay, $this->points, $this->total, $this->ledger, $this->seasons];
        return $this->transaction = true;
    }
    public function commit(): bool
    {
        $this->transaction = false;
        if ($this->fault === 'after_commit') { throw new RuntimeException('Lost ACK'); }
        return true;
    }
    public function rollBack(): bool
    {
        if ($this->fault === 'rollback') { throw new RuntimeException('Lost rollback ACK'); }
        [$this->gameplay, $this->points, $this->total, $this->ledger, $this->seasons] = $this->snapshot;
        $this->transaction = false;
        return true;
    }
    public function exec(string $statement): int|false { return 0; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, 'NOW(6)')) { $this->events[] = 'admission'; }
        $value = match (true) {
            str_contains($query, 'DATABASE()') => 'protocol_unit',
            str_contains($query, 'CONNECTION_ID()') => 123,
            str_contains($query, 'NOW(6)') => '2026-11-06 00:00:00.000000',
            default => 50,
        };
        return new OperationStatement($this, $query, $value);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new OperationStatement($this, preg_replace('/\s+/', ' ', trim($query)));
    }
}

final class OperationStatement extends PDOStatement
{
    public function __construct(private OperationPdo $db, private string $sql, private mixed $value = null) {}
    public function execute(?array $params = null): bool
    {
        $db = $this->db;
        if (str_starts_with($this->sql, 'INSERT INTO seasons')) {
            $db->seasons[] = ['id' => 1, 'starts_at' => '2026-11-01', 'ends_at' => '2026-12-01', 'has_started' => 1, 'has_ended' => 0,
                'weight_pvp' => 1, 'weight_boss' => 0.01, 'weight_cultivation' => 1, 'weight_sect' => 5];
        }
        if (str_starts_with($this->sql, 'UPDATE season_rankings SET score_')) {
            if ($db->fault === 'category') { throw new RuntimeException('category'); }
            $db->points += $params[0];
        }
        if (str_starts_with($this->sql, 'UPDATE season_rankings SET total_score')) {
            if ($db->fault === 'total') { throw new RuntimeException('total'); }
            $db->total = $params[0];
        }
        if (str_starts_with($this->sql, 'INSERT INTO season_gameplay_operations')) {
            if ($db->fault === 'ledger') { throw new RuntimeException('ledger'); }
            $db->ledger = ['fingerprint' => $params[2], 'fingerprint_version' => $params[3], 'outcome' => $params[6]];
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        return match (true) {
            str_contains($this->sql, 'IS_USED_LOCK') => null,
            str_contains($this->sql, 'GET_LOCK') => $this->db->acquire,
            str_contains($this->sql, 'RELEASE_LOCK') => $this->db->release,
            default => $this->value,
        };
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (str_contains($this->sql, 'FROM season_gameplay_operations')) { return $this->db->ledger; }
        return ['score_pvp' => 0, 'score_world_boss' => 0, 'score_cultivation' => $this->db->points, 'score_sect' => 0];
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->db->seasons; }
}
