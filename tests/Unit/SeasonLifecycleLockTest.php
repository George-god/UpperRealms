<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SeasonLifecycleLock;
use App\Support\SeasonLifecycleLockException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SeasonLifecycleLockTest extends TestCase
{
    public function test_timeout_and_null_result_never_execute_callback(): void
    {
        foreach ([0, null] as $result) {
            $db = new LockProtocolPdo;
            $db->acquisition = $result;
            try {
                SeasonLifecycleLock::run($db, fn () => $this->fail('Unlocked mutation'));
                $this->fail('Acquisition failure was ignored');
            } catch (SeasonLifecycleLockException $error) {
                $this->assertSame($result === 0 ? 'busy' : 'acquisition_failed', $error->reason);
                $this->assertSame(0, $db->releases);
            }
        }
    }

    public function test_exception_is_preserved_and_lock_is_released(): void
    {
        $db = new LockProtocolPdo;
        $original = new RuntimeException('original');
        try {
            SeasonLifecycleLock::run($db, fn () => throw $original);
        } catch (RuntimeException $error) {
            $this->assertSame($original, $error);
        }
        $this->assertSame(1, $db->releases);
    }

    public function test_release_failure_is_reported_and_original_exception_survives(): void
    {
        foreach ([false, true] as $throwOriginal) {
            $db = new LockProtocolPdo;
            $db->release = 0;
            $original = new RuntimeException('original');
            try {
                SeasonLifecycleLock::run($db, fn () => $throwOriginal ? throw $original : 'success');
                $this->fail('Release failure ignored');
            } catch (RuntimeException $error) {
                if ($throwOriginal) {
                    $this->assertSame($original, $error->getPrevious());
                    $this->assertSame('operation_and_release_failed', $error->reason);
                    $this->assertArrayHasKey('release', $error->cleanupFailures);
                } else {
                    $this->assertSame('release_failed', $error->reason);
                }
            }
            $this->assertSame(1, $db->releases);
            try {
                SeasonLifecycleLock::run($db, fn () => $this->fail('Poisoned connection reused'));
            } catch (SeasonLifecycleLockException $error) {
                $this->assertSame('unsafe_connection', $error->reason);
            }
        }
    }

    public function test_nested_same_or_second_pdo_is_rejected_without_reacquiring(): void
    {
        $db = new LockProtocolPdo;
        SeasonLifecycleLock::run($db, function () use ($db) {
            foreach ([$db, new LockProtocolPdo] as $nested) {
                try {
                    SeasonLifecycleLock::run($nested, fn () => $this->fail('Nested mutation'));
                } catch (SeasonLifecycleLockException $error) {
                    $this->assertSame('recursive_acquisition', $error->reason);
                }
            }
        });
        $this->assertSame(1, $db->releases);
    }

    public function test_existing_transaction_is_not_touched(): void
    {
        $db = new LockProtocolPdo;
        $db->transaction = true;
        $this->expectException(SeasonLifecycleLockException::class);
        SeasonLifecycleLock::run($db, fn () => $this->fail('Transaction accepted'));
    }

    public function test_acquisition_exception_quarantines_without_executing_operation(): void
    {
        $db = new LockProtocolPdo;
        $db->throwAcquire = true;
        try {
            SeasonLifecycleLock::run($db, fn () => $this->fail('Unacknowledged acquisition permitted mutation'));
            $this->fail('Acquisition uncertainty ignored');
        } catch (SeasonLifecycleLockException $error) {
            $this->assertSame('acquisition_uncertain', $error->reason);
            $this->assertTrue($error->discardConnection);
            $this->assertInstanceOf(RuntimeException::class, $error->getPrevious());
            $this->assertTrue(SeasonLifecycleLock::isUnsafe($db));
        }
    }

    public function test_connection_change_cannot_be_reported_as_success(): void
    {
        $db = new LockProtocolPdo;
        $this->expectException(SeasonLifecycleLockException::class);
        SeasonLifecycleLock::run($db, function () use ($db) { $db->connectionId = 456; });
    }
}

/** Protocol unit doubles only; cross-process semantics are tested with real MariaDB. */
final class LockProtocolPdo extends PDO
{
    public mixed $acquisition = 1;
    public mixed $release = 1;
    public int $releases = 0;
    public bool $transaction = false;
    public int $connectionId = 123;
    public bool $throwAcquire = false;
    public function __construct() {}
    public function inTransaction(): bool { return $this->transaction; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return new LockProtocolStatement(str_contains($query, 'DATABASE()') ? 'unit_test' : $this->connectionId);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'RELEASE_LOCK')) {
            $this->releases++;
            return new LockProtocolStatement($this->release);
        }
        return new LockProtocolStatement(str_contains($query, 'IS_USED_LOCK') ? null : $this->acquisition, $this->throwAcquire && str_contains($query, 'GET_LOCK'));
    }
}

final class LockProtocolStatement extends PDOStatement
{
    public function __construct(private mixed $value, private bool $throw = false) {}
    public function execute(?array $params = null): bool
    {
        if ($this->throw) { throw new RuntimeException('Synthetic acquisition acknowledgement failure'); }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->value; }
}
