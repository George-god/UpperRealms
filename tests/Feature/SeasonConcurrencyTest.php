<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\DisposableSeasonDatabase;
use Throwable;

/** Characterizations of specific interleavings, not a proof of general concurrency safety. */
class SeasonConcurrencyTest extends DisposableSeasonDatabase
{
    private array $workers = [];

    private ?string $barriers = null;

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            try {
                if ($worker->isRunning()) {
                    $worker->stop(1);
                }
                if ($worker->isRunning()) {
                    $this->cleanupErrors[] = 'Child test worker is still running after stop.';
                }
            } catch (Throwable $error) {
                $this->cleanupErrors[] = 'Child worker cleanup failed ('.$error::class.').';
            }
        }
        if ($this->barriers !== null) {
            $files = glob($this->barriers.'/*');
            if ($files === false) {
                $this->cleanupErrors[] = 'Could not enumerate test barrier files.';
            } else {
                foreach ($files as $file) {
                    if (! @unlink($file)) {
                        $this->cleanupErrors[] = 'Could not remove test barrier file '.basename($file).'.';
                    }
                }
            }
            if (! @rmdir($this->barriers)) {
                $this->cleanupErrors[] = 'Could not remove test barrier directory '.$this->barriers.'.';
            }
        }
        // Always attempt database/Laravel cleanup, and report all accumulated failures.
        parent::tearDown();
    }

    public function test_two_processors_wait_on_the_shared_lock_and_reward_once(): void
    {
        $expired = $this->rewardFixture();
        // Remove replacement-creation races from this particular lock test.
        $this->season('+2 DAY');
        $first = $this->start('first', 'process', 'laravel', 'locked');
        $this->awaitMarker('first', 'ready');
        $second = $this->start('second', 'process');
        $this->awaitMarker('second', 'attempt');
        $this->awaitAdvisoryWait('first', 'second');
        $this->release('first');
        $a = $this->finishWorker($first);
        $b = $this->finishWorker($second);
        $this->assertTrue($a['ok']);
        $this->assertTrue($b['ok']);
        $this->assertSame([1, 0], [$a['processed'], $b['processed']], 'Observed service results: '.json_encode([$a, $b]));
        $this->assertSame('ended', DB::table('seasons')->where('id', $expired)->value('status'));
        $this->assertRewardOnce();
    }

    public static function creationPaths(): array
    {
        return [['laravel', 'laravel'], ['legacy', 'legacy'], ['laravel', 'legacy']];
    }

    #[DataProvider('creationPaths')]
    public function test_competing_creators_produce_one_active_season(string $left, string $right): void
    {
        $a = $this->start('first', 'create', $left, 'create');
        $this->awaitMarker('first', 'ready');
        $b = $this->start('second', 'create', $right, 'create');
        $this->awaitMarker('second', 'attempt');
        $this->awaitAdvisoryWait('first', 'second');
        $this->assertNotSame(file_get_contents($this->barriers.'/first.connection'), file_get_contents($this->barriers.'/second.connection'));
        $this->release('first');
        $this->assertTrue($this->finishWorker($a)['created_call_completed']);
        $this->assertTrue($this->finishWorker($b)['created_call_completed']);
        // The contender rechecks after the winner has inserted and released its lock.
        $this->assertSame(1, $this->activeCount());
    }

    public function test_replacement_and_legacy_rankings_page_create_only_one_season(): void
    {
        $expired = $this->rewardFixture();
        $processor = $this->start('processor', 'process', 'laravel', 'create');
        $this->awaitMarker('processor', 'ready');
        // Processor has committed rewards but has not inserted its replacement.
        $this->assertSame('ended', DB::table('seasons')->where('id', $expired)->value('status'));
        $page = $this->start('page', 'page', 'legacy', 'create');
        $this->awaitMarker('page', 'attempt');
        $this->awaitAdvisoryWait('processor', 'page');
        $this->release('processor');
        $this->assertTrue($this->finishWorker($processor)['ok']);
        $this->assertNotNull($this->finishWorker($page)['season']);
        $this->assertSame(1, $this->activeCount());
        $this->assertRewardOnce();
    }

    public function test_existing_bug_inflight_score_can_update_an_already_finalized_season(): void
    {
        DB::statement('SET timestamp = 1800000000');
        $expired = $this->rewardFixture('+5 SECOND');
        $score = $this->start('scorer', 'score', 'laravel', 'score', 1800000000);
        $this->awaitMarker('scorer', 'ready');
        // Scoring already read the old active season; another connection now sees it as expired.
        $processor = $this->start('processor', 'process', 'laravel', '', 1800000010);
        $this->assertTrue($this->finishWorker($processor)['ok']);
        $this->release('scorer');
        $this->assertTrue($this->finishWorker($score)['score_call_completed']);
        $row = DB::table('season_rankings')->where('season_id', $expired)->first();
        $this->assertSame('ended', DB::table('seasons')->where('id', $expired)->value('status'));
        $this->assertSame(10, (int) $row->score_pvp);
        $this->assertSame(110, (int) $row->total_score);
        $this->assertSame(1, (int) $row->rank_overall);
        $replacement = DB::table('seasons')->where('id', '<>', $expired)->value('id');
        $this->assertSame(0, DB::table('season_rankings')->where('season_id', $replacement)->count());
        $this->assertRewardOnce();
        // This forces an expiry crossing using session clocks, not elapsed sleeps or changed gameplay SQL.
    }

    public function test_late_transaction_failure_rolls_back_currency_titles_notifications_and_ranks(): void
    {
        $expired = $this->rewardFixture();
        DB::unprepared("CREATE TRIGGER phase2b_fail_finalization BEFORE UPDATE ON seasons FOR EACH ROW BEGIN IF NEW.status = 'ended' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'phase2b_finalization_fault'; END IF; END");
        $worker = $this->start('processor', 'process');
        $result = $this->finishWorker($worker);
        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['processed']);
        $this->assertSame('active', DB::table('seasons')->where('id', $expired)->value('status'));
        $this->assertSame(0, (int) DB::table('users')->sum('gold'));
        $this->assertSame(0, (int) DB::table('users')->sum('spirit_stones'));
        $this->assertSame(0, DB::table('user_titles')->count());
        $this->assertSame(0, DB::table('notifications')->count());
        foreach (['rank_overall', 'rank_pvp', 'rank_boss', 'rank_cultivation', 'rank_sect'] as $column) {
            $this->assertNull(DB::table('season_rankings')->value($column));
        }
        $this->assertSame(1, DB::table('seasons')->count());
        // PHP error_log is outside the database transaction and survives the rollback.
        $this->assertSame('finalization_failed', $result['error_code']);
        $this->assertStringContainsString('finalization_failed', $worker->getErrorOutput());
        DB::unprepared('DROP TRIGGER phase2b_fail_finalization');
        $this->assertTrue($this->finishWorker($this->start('retry', 'process'))['ok']);
        $this->assertRewardOnce();
    }

    public function test_replacement_failure_reports_partial_completion_and_retry_does_not_reward_again(): void
    {
        $expired = $this->rewardFixture();
        DB::unprepared("CREATE TRIGGER phase2b_fail_creation BEFORE INSERT ON seasons FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'phase2b_creation_fault'");
        $first = $this->start('first', 'process');
        $result = $this->finishWorker($first);
        $this->assertFalse($result['ok']);
        $this->assertSame('partial', $result['status']);
        $this->assertTrue($result['finalization_committed']);
        $this->assertSame(1, $result['processed']);
        $this->assertSame('ended', DB::table('seasons')->where('id', $expired)->value('status'));
        $this->assertSame(0, $this->activeCount());
        $this->assertRewardOnce();
        $notifications = DB::table('notifications')->count();
        $this->assertStringContainsString('replacement_failed', $first->getErrorOutput());
        $retry = $this->finishWorker($this->start('retry', 'process'));
        $this->assertFalse($retry['ok']);
        $this->assertSame('partial', $retry['status']);
        $this->assertSame(0, $retry['processed']);
        $this->assertSame(0, $this->activeCount());
        DB::unprepared('DROP TRIGGER phase2b_fail_creation');
        $repaired = $this->finishWorker($this->start('repair', 'process'));
        $this->assertTrue($repaired['ok']);
        $this->assertSame(0, $repaired['processed']);
        $this->assertSame(1, $this->activeCount());
        $this->assertRewardOnce();
        $this->assertSame($notifications, DB::table('notifications')->count());
    }

    public function test_lock_timeout_is_reported_without_mutation_and_exception_releases_lock(): void
    {
        $holder = $this->start('holder', 'create', 'legacy', 'create');
        $this->awaitMarker('holder', 'ready');
        $busy = $this->finishWorker($this->start('busy', 'process'));
        $this->assertFalse($busy['ok']);
        $this->assertSame('busy', $busy['error_code']);
        $this->assertSame(0, DB::table('seasons')->count());
        $this->release('holder');
        $this->finishWorker($holder);
        $this->assertTrue($this->finishWorker($this->start('thrower', 'throw'))['caught']);
        $name = \App\Support\SeasonLifecycleLock::name(DB::connection()->getPdo());
        $stmt = $this->admin->prepare('SELECT IS_USED_LOCK(?)');
        $stmt->execute([$name]);
        $this->assertNull($stmt->fetchColumn());
        $this->assertTrue($this->finishWorker($this->start('retry', 'process'))['ok']);
    }

    public function test_only_owner_can_release_and_terminated_owner_frees_lock(): void
    {
        $holder = $this->start('holder', 'create', 'laravel', 'create');
        $this->awaitMarker('holder', 'ready');
        $name = \App\Support\SeasonLifecycleLock::name(DB::connection()->getPdo());
        $stmt = $this->admin->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
        $holder->stop(1);
        $this->await(function () use ($name) {
            $stmt = $this->admin->prepare('SELECT IS_USED_LOCK(?)');
            $stmt->execute([$name]);
            return $stmt->fetchColumn() === null;
        }, 'Terminated owner did not release its connection-owned lock.');
        $this->assertTrue($this->finishWorker($this->start('retry', 'process'))['ok']);
        $this->assertSame(1, $this->activeCount());
    }

    private function rewardFixture(string $offset = '-1 DAY'): int
    {
        $id = $this->season($offset);
        DB::table('users')->insert(['id' => 1, 'username' => 'synthetic']);
        DB::table('season_rankings')->insert(['season_id' => $id, 'user_id' => 1, 'total_score' => 100, 'score_cultivation' => 100]);

        return $id;
    }

    private function assertRewardOnce(): void
    {
        $this->assertSame(5000, (int) DB::table('users')->sum('gold'));
        $this->assertSame(50, (int) DB::table('users')->sum('spirit_stones'));
        $this->assertSame(1, DB::table('user_titles')->where('title_id', 13)->count());
        $this->assertSame(1, DB::table('user_titles')->where('title_id', 17)->count());
        $this->assertSame(2, DB::table('user_titles')->count());
        $this->assertSame(2, DB::table('notifications')->where('type', 'season_reward')->count());
        $this->assertSame(2, DB::table('notifications')->where('type', 'title_unlock')->count());
    }

    private function activeCount(): int
    {
        return DB::table('seasons')->where('status', 'active')->whereRaw('ends_at > NOW()')->count();
    }

    private function start(string $name, string $operation, string $path = 'laravel', string $barrier = '', ?int $clock = null): Process
    {
        if ($this->barriers === null) {
            $this->barriers = sys_get_temp_dir().'/upperrealms_barrier_'.bin2hex(random_bytes(8));
            mkdir($this->barriers, 0700);
        }
        $connection = config('database.connections.season_test');
        $input = array_merge($connection, ['name' => $name, 'operation' => $operation, 'path' => $path, 'barrier' => $barrier, 'directory' => $this->barriers, 'clock' => $clock]);
        $worker = new Process([PHP_BINARY, '-d', 'error_log=', '-d', 'log_errors=1', base_path('tests/Support/season-worker.php')], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'SEASON_TEST_DISPOSABLE' => '1',
        ], json_encode($input, JSON_THROW_ON_ERROR), 20);
        $worker->start();
        $this->workers[] = $worker;

        return $worker;
    }

    private function awaitMarker(string $name, string $marker): void
    {
        $this->await(fn () => is_file($this->barriers.'/'.$name.'.'.$marker), 'Worker did not reach '.$name.'.'.$marker);
    }

    private function awaitAdvisoryWait(string $owner, string $waiter): void
    {
        $ownerId = (int) file_get_contents($this->barriers.'/'.$owner.'.connection');
        $waiterId = (int) file_get_contents($this->barriers.'/'.$waiter.'.connection');
        $this->assertNotSame($ownerId, $waiterId);
        $name = \App\Support\SeasonLifecycleLock::name(DB::connection()->getPdo());
        $this->await(function () use ($name, $ownerId, $waiterId) {
            return $this->parentDatabaseOperation('observe advisory lock contender', function () use ($name, $ownerId, $waiterId) {
                $stmt = $this->admin->prepare('SELECT IS_USED_LOCK(?)');
                $stmt->execute([$name]);
                return (int) $stmt->fetchColumn() === $ownerId
                    && (int) $this->admin->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID = {$waiterId} AND STATE LIKE '%lock%' AND INFO LIKE '%GET_LOCK%'")->fetchColumn() === 1;
            });
        }, 'Contender did not enter an observable advisory-lock wait.');
    }

    private function release(string $name): void
    {
        file_put_contents($this->barriers.'/'.$name.'.release', '1');
    }

    private function await(callable $condition, string $message): void
    {
        $deadline = microtime(true) + 4;
        do {
            clearstatcache();
            if ($condition()) {
                return;
            }
            foreach ($this->workers as $worker) {
                $worker->checkTimeout();
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail($message);
    }

    private function finishWorker(Process $worker): array
    {
        $worker->wait();
        $this->assertSame(0, $worker->getExitCode(), 'Worker failed before returning a service result; inspect disposable-server/test setup.');

        return json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
