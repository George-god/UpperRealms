<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

abstract class DisposableSeasonScoringDatabase extends DisposableSeasonDatabase
{
    private array $children = [];

    private ?string $scratch = null;

    protected function verifyDisposableServer(PDO $admin): void
    {
        SeasonScoringEnvironment::validate([
            'port' => (int) (getenv('SEASON_TEST_PORT') ?: 3307),
            'database' => 'upperrealms_season_test_0000000000000000',
        ]);
        $this->parentDatabaseOperation('verify disposable scoring instance marker', fn () => SeasonScoringEnvironment::verifyServer($admin));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->scratch = sys_get_temp_dir().'/upperrealms_scoring_'.bin2hex(random_bytes(8));
        if (! mkdir($this->scratch, 0700)) {
            throw new RuntimeException('Cannot create private scoring test directory.');
        }
        $this->parentDatabaseOperation('replace minimal fixture with allowlisted gameplay DDL', function (): void {
            // This is the random schema owned by the parent; never run application migrations/imports.
            $this->assertSame($this->database, DB::scalar('SELECT DATABASE()'));
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            try {
                foreach (['users', 'seasons', 'season_rankings', 'titles', 'user_titles', 'notifications'] as $table) {
                    DB::statement('DROP TABLE `'.$table.'`');
                }
                foreach (SeasonScoringSchema::statements() as $sql) {
                    DB::unprepared($sql);
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
            DB::statement("SET time_zone = '+00:00'");
            DB::table('realms')->insert(['id' => 1, 'name' => 'Synthetic realm', 'max_level' => 1]);
            DB::table('cave_environments')->insert(['env_key' => 'balanced', 'display_name' => 'Synthetic neutral cave']);
            foreach ([1, 2] as $id) {
                DB::table('users')->insert([
                    'id' => $id, 'username' => 'synthetic'.$id, 'email' => 'synthetic'.$id.'@example.invalid',
                    'password_hash' => 'not-a-login-credential', 'gold' => 10000,
                    'chi' => 100, 'max_chi' => 1000, 'attack' => 20, 'defense' => 10,
                ]);
            }
            foreach (range(13, 18) as $id) {
                DB::table('titles')->insert(['id' => $id, 'slug' => 'synthetic-'.$id, 'name' => 'Synthetic '.$id, 'unlock_type' => 'season_rank']);
            }
        });
    }

    protected function startScoring(string $path, string $operation, array $options = []): Process
    {
        $name = 'worker'.count($this->children);
        $directory = $this->scratch.'/'.$name;
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create child test directory.');
        }
        $config = config('database.connections.season_test');
        $input = array_merge($options, [
            'database' => $this->database, 'port' => (int) $config['port'],
            'username' => $config['username'], 'password' => $config['password'],
            'directory' => $directory, 'worker' => $name, 'path' => $path, 'operation' => $operation,
        ]);
        $process = new Process([PHP_BINARY, __DIR__.'/season-scoring-worker.php'], dirname(__DIR__, 2), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ], json_encode($input, JSON_THROW_ON_ERROR), 25);
        $this->children[] = [$process, $directory, $name];
        $process->start();

        return $process;
    }

    protected function finishScoring(Process $process): array
    {
        $process->wait();
        $this->assertSame(0, $process->getExitCode(), 'Scoring worker failed before a result; inspect isolated test setup.');

        return json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
    }

    protected function awaitScoringBarrier(Process $process, string $event): void
    {
        $prefix = $this->barrierPrefix($process, $event);
        $deadline = hrtime(true) + 8_000_000_000;
        do {
            clearstatcache(true, $prefix.'.ready');
            if (is_file($prefix.'.ready')) {
                return;
            }
            $process->checkTimeout();
            $this->assertTrue($process->isRunning(), 'Worker exited before the requested SQL barrier.');
            usleep(10000);
        } while (hrtime(true) < $deadline);
        $this->fail('Scoring barrier was not reached: '.$event);
    }

    protected function releaseScoringBarrier(Process $process, string $event): void
    {
        $this->assertNotFalse(file_put_contents($this->barrierPrefix($process, $event).'.release', '1'));
    }

    protected function awaitScoringLockWait(Process $owner, Process $waiter): void
    {
        $files = [];
        foreach ($this->children as [$process, $directory, $name]) {
            if ($process === $owner) { $files['owner'] = $directory.'/'.$name.'.connection'; }
            if ($process === $waiter) { $files['waiter'] = $directory.'/'.$name.'.connection'; }
        }
        $this->assertCount(2, $files);
        $lock = \App\Support\SeasonLifecycleLock::name(DB::connection()->getPdo());
        $deadline = hrtime(true) + 4_000_000_000;
        do {
            clearstatcache();
            $owner->checkTimeout();
            $waiter->checkTimeout();
            if (is_file($files['owner']) && is_file($files['waiter'])) {
                $ownerId = (int) file_get_contents($files['owner']);
                $waiterId = (int) file_get_contents($files['waiter']);
                $this->assertNotSame($ownerId, $waiterId);
                $used = $this->admin->prepare('SELECT IS_USED_LOCK(?)');
                $used->execute([$lock]);
                $waiting = $this->admin->prepare("SELECT COUNT(*) FROM information_schema.PROCESSLIST
                    WHERE ID = ? AND DB = ? AND STATE LIKE '%lock%' AND INFO LIKE '%GET_LOCK%'");
                $waiting->execute([$waiterId, $this->database]);
                if ((int) $used->fetchColumn() === $ownerId && (int) $waiting->fetchColumn() === 1) { return; }
            }
            $this->assertTrue($owner->isRunning() && $waiter->isRunning(), 'Workers exited before observable contention.');
            usleep(10000);
        } while (hrtime(true) < $deadline);
        $this->fail('Contender did not enter an observable lifecycle lock wait within four seconds.');
    }

    private function barrierPrefix(Process $process, string $event): string
    {
        foreach ($this->children as [$child, $directory, $name]) {
            if ($child === $process) {
                return $directory.'/'.$name.'.'.$event;
            }
        }
        throw new RuntimeException('Unknown owned test worker.');
    }

    protected function tearDown(): void
    {
        foreach ($this->children as [$child]) {
            try {
                if ($child->isRunning()) {
                    $child->stop(1);
                }
                if ($child->isRunning()) {
                    throw new RuntimeException('Scoring child survived bounded stop.');
                }
            } catch (Throwable $error) {
                $this->cleanupErrors[] = 'Scoring worker cleanup failed ('.$error::class.').';
            }
        }
        if ($this->scratch !== null) {
            try {
                // Only enumerate/delete descendants of the exact private directory this instance created.
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->scratch, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                $deadline = hrtime(true) + 10_000_000_000;
                foreach ($iterator as $entry) {
                    if (hrtime(true) > $deadline) {
                        throw new RuntimeException('Private artifact cleanup exceeded ten seconds.');
                    }
                    $removed = $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                    if (! $removed) {
                        throw new RuntimeException('Could not remove private scoring artifact.');
                    }
                }
                if (! rmdir($this->scratch)) {
                    throw new RuntimeException('Could not remove private scoring directory.');
                }
            } catch (Throwable $error) {
                $this->cleanupErrors[] = 'Scoring artifact cleanup failed ('.$error::class.').';
            }
        }
        parent::tearDown();
    }
}
