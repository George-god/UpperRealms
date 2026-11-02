<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\SeasonScoringBarrier;
use Tests\Support\SeasonScoringEnvironment;
use Tests\Support\SeasonScoringSchema;

class SeasonScoringHarnessTest extends TestCase
{
    public function test_schema_extraction_contains_only_allowlisted_ddl_and_no_bundled_seed_data(): void
    {
        $statements = SeasonScoringSchema::statements();
        $this->assertCount(count(SeasonScoringSchema::TABLES) + 6, $statements);
        foreach ($statements as $statement) {
            $this->assertMatchesRegularExpression('/^(CREATE TABLE IF NOT EXISTS [a-z_]+ \(|ALTER TABLE (users|realms)\R)/', $statement);
            $this->assertDoesNotMatchRegularExpression('/^(USE |CREATE DATABASE |INSERT |REPLACE |DROP |DELETE )/m', $statement);
            $this->assertSame(1, substr_count($statement, ';'));
        }
        foreach (SeasonScoringSchema::TABLES as $index => $table) {
            $this->assertStringStartsWith('CREATE TABLE IF NOT EXISTS '.$table.' (', $statements[$index]);
        }
    }

    public function test_guard_requires_opt_in_marker_valid_port_and_random_database_name(): void
    {
        $env = ['APP_ENV' => 'testing', 'SEASON_TEST_MARIADB' => '1', 'SEASON_TEST_DISPOSABLE' => '1', 'SEASON_TEST_INSTANCE_TOKEN' => str_repeat('a', 32)];
        $original = array_map('getenv', array_keys($env));
        try {
            foreach ($env as $name => $value) {
                putenv($name.'='.$value);
            }
            $safe = ['port' => 3307, 'database' => 'upperrealms_season_test_0123456789abcdef'];
            SeasonScoringEnvironment::validate($safe);
            $this->addToAssertionCount(1);
            foreach ([['port' => 3306], ['port' => 0], ['port' => 65536], ['port' => '3307'], ['database' => 'cultivation_rpg']] as $change) {
                $this->assertRejected(array_replace($safe, $change));
            }
            foreach (['APP_ENV', 'SEASON_TEST_MARIADB', 'SEASON_TEST_DISPOSABLE', 'SEASON_TEST_INSTANCE_TOKEN'] as $name) {
                putenv($name.'=');
                $this->assertRejected($safe);
                putenv($name.'='.$env[$name]);
            }
        } finally {
            foreach (array_keys($env) as $index => $name) {
                putenv($original[$index] === false ? $name : $name.'='.$original[$index]);
            }
        }
    }

    private function assertRejected(array $config): void
    {
        try {
            SeasonScoringEnvironment::validate($config);
            $this->fail('Unsafe configuration was accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Unsafe disposable scoring', $error->getMessage());
        }
    }

    public function test_missing_release_produces_a_named_bounded_barrier_failure(): void
    {
        $directory = sys_get_temp_dir().'/scoring_barrier_'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory, 0700));
        try {
            try {
                SeasonScoringBarrier::point($directory, 'worker', 'before_commit', 0.02);
                $this->fail('Missing release was ignored.');
            } catch (RuntimeException $error) {
                $this->assertSame('Scoring barrier timed out: worker.before_commit', $error->getMessage());
            }
        } finally {
            $this->assertTrue(unlink($directory.'/worker.before_commit.ready'));
            $this->assertTrue(rmdir($directory));
        }
    }

    public function test_two_real_processes_acknowledge_and_wait_for_individual_releases(): void
    {
        $directory = sys_get_temp_dir().'/scoring_barrier_'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory, 0700));
        $workers = [];
        try {
            foreach (['first', 'second'] as $name) {
                $worker = new Process([PHP_BINARY, '-r',
                    'require $argv[1]; Tests\\Support\\SeasonScoringBarrier::point($argv[2], $argv[3], "admitted", 3); echo "released";',
                    dirname(__DIR__).'/Support/SeasonScoringBarrier.php', $directory, $name,
                ], null, null, null, 5);
                $worker->start();
                $workers[$name] = $worker;
            }
            $deadline = hrtime(true) + 2_000_000_000;
            do {
                clearstatcache();
                if (is_file($directory.'/first.admitted.ready') && is_file($directory.'/second.admitted.ready')) {
                    break;
                }
                foreach ($workers as $worker) {
                    $worker->checkTimeout();
                }
                usleep(10000);
            } while (hrtime(true) < $deadline);
            $this->assertFileExists($directory.'/first.admitted.ready');
            $this->assertFileExists($directory.'/second.admitted.ready');
            $this->assertTrue($workers['first']->isRunning());
            $this->assertTrue($workers['second']->isRunning());
            $this->assertNotSame($workers['first']->getPid(), $workers['second']->getPid());
            $this->assertNotFalse(file_put_contents($directory.'/first.admitted.release', '1'));
            $this->assertSame(0, $workers['first']->wait());
            $this->assertTrue($workers['second']->isRunning());
            $this->assertNotFalse(file_put_contents($directory.'/second.admitted.release', '1'));
            $this->assertSame(0, $workers['second']->wait());
            foreach ($workers as $worker) {
                $this->assertSame('released', $worker->getOutput());
            }
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(0.2);
                }
                $this->assertFalse($worker->isRunning());
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                $this->assertTrue(unlink($file));
            }
            $this->assertTrue(rmdir($directory));
        }
    }
}
