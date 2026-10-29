<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\DisposableSeasonDatabase;

/** Characterizations of specific interleavings, not a proof of general concurrency safety. */
class SeasonConcurrencyTest extends DisposableSeasonDatabase
{
    private array $workers = [];

    private ?string $barriers = null;

    protected function tearDown(): void
    {
        try {
            foreach ($this->workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(1);
                }
            }
            if ($this->barriers !== null) {
                foreach (glob($this->barriers.'/*') as $file) {
                    unlink($file);
                }
                rmdir($this->barriers);
            }
        } finally {
            // Stop children before dropping the random database, including on assertion failure.
            parent::tearDown();
        }
    }

    public function test_two_processors_wait_on_a_real_row_lock_and_reward_once(): void
    {
        $expired = $this->rewardFixture();
        // Remove replacement-creation races from this particular lock test.
        $this->season('+2 DAY');
        $first = $this->start('first', 'process', 'laravel', 'locked');
        $this->awaitMarker('first', 'ready');
        $second = $this->start('second', 'process');
        $this->awaitMarker('second', 'attempt');
        $firstId = (int) file_get_contents($this->barriers.'/first.connection');
        $secondId = (int) file_get_contents($this->barriers.'/second.connection');
        $this->assertNotSame($firstId, $secondId);
        $this->await(function () use ($firstId, $secondId) {
            return (int) $this->admin->query("SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id WHERE r.trx_mysql_thread_id = {$secondId} AND b.trx_mysql_thread_id = {$firstId}")->fetchColumn() > 0;
        }, 'Second processor never entered an observable database lock wait.');
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
    public function test_existing_bug_two_empty_checks_can_create_two_active_seasons(string $left, string $right): void
    {
        $a = $this->start('first', 'create', $left, 'create');
        $b = $this->start('second', 'create', $right, 'create');
        $this->awaitMarker('first', 'ready');
        $this->awaitMarker('second', 'ready');
        $this->assertNotSame(file_get_contents($this->barriers.'/first.connection'), file_get_contents($this->barriers.'/second.connection'));
        $this->release('first');
        $this->release('second');
        $this->assertTrue($this->finishWorker($a)['created_call_completed']);
        $this->assertTrue($this->finishWorker($b)['created_call_completed']);
        // EXPECTED EXISTING BUG: both actual SELECTs observed no active season before either INSERT.
        $this->assertSame(2, $this->activeCount());
    }

    public function test_existing_bug_replacement_and_legacy_rankings_page_can_both_create(): void
    {
        $expired = $this->rewardFixture();
        $processor = $this->start('processor', 'process', 'laravel', 'create');
        $this->awaitMarker('processor', 'ready');
        // Processor has committed rewards but has not inserted its replacement.
        $this->assertSame('ended', DB::table('seasons')->where('id', $expired)->value('status'));
        $page = $this->start('page', 'page', 'legacy', 'create');
        $this->awaitMarker('page', 'ready');
        $this->release('processor');
        $this->release('page');
        $this->assertTrue($this->finishWorker($processor)['ok']);
        $this->assertNotNull($this->finishWorker($page)['season']);
        $this->assertSame(2, $this->activeCount(), 'EXPECTED EXISTING BUG: duplicate active replacements.');
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
        $this->assertStringContainsString('phase2b_finalization_fault', $worker->getErrorOutput());
        DB::unprepared('DROP TRIGGER phase2b_fail_finalization');
        $this->assertTrue($this->finishWorker($this->start('retry', 'process'))['ok']);
        $this->assertRewardOnce();
    }

    public function test_existing_bug_replacement_failure_reports_success_but_retry_can_repair_without_rewards_again(): void
    {
        $expired = $this->rewardFixture();
        DB::unprepared("CREATE TRIGGER phase2b_fail_creation BEFORE INSERT ON seasons FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'phase2b_creation_fault'");
        $first = $this->start('first', 'process');
        $result = $this->finishWorker($first);
        $this->assertTrue($result['ok'], 'EXPECTED EXISTING BUG: creation failure is swallowed.');
        $this->assertSame(1, $result['processed']);
        $this->assertSame('ended', DB::table('seasons')->where('id', $expired)->value('status'));
        $this->assertSame(0, $this->activeCount());
        $this->assertRewardOnce();
        $notifications = DB::table('notifications')->count();
        $this->assertStringContainsString('phase2b_creation_fault', $first->getErrorOutput());
        $retry = $this->finishWorker($this->start('retry', 'process'));
        $this->assertTrue($retry['ok']);
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
