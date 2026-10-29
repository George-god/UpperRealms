<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\SeasonService;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Opt-in MariaDB characterization tests. Never use DB_* or an existing schema.
 * Each test creates and drops its own random database on a disposable local server.
 */
abstract class DisposableSeasonDatabase extends TestCase
{
    protected ?PDO $admin = null;

    protected ?string $database = null;

    protected function setUp(): void
    {
        if (getenv('SEASON_TEST_MARIADB') !== '1') {
            $this->markTestSkipped('Requires opt-in disposable loopback MariaDB; see docs/season-processing-tests.md.');
        }

        if (getenv('SEASON_TEST_DISPOSABLE') !== '1') {
            $this->fail('Confirm a disposable local server with SEASON_TEST_DISPOSABLE=1.');
        }
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/bootstrap/cache/config.php', 'Remove cached config in the isolated test checkout first.');

        parent::setUp();

        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame('sqlite', config('database.default'), 'Start with the isolated PHPUnit SQLite configuration.');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertContains('mysql', PDO::getAvailableDrivers(), 'pdo_mysql is required.');
        $port = (int) (getenv('SEASON_TEST_PORT') ?: 3307);
        $this->assertGreaterThan(0, $port);
        $this->assertLessThanOrEqual(65535, $port);
        $this->assertNotSame(3306, $port, 'Do not use the normal game database port.');
        $user = getenv('SEASON_TEST_USER') ?: 'root';
        $password = getenv('SEASON_TEST_PASSWORD') ?: '';
        // Host is deliberately not configurable. This must be a disposable local instance.
        $this->admin = new PDO("mysql:host=127.0.0.1;port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $database = 'upperrealms_season_test_'.bin2hex(random_bytes(8));
        $this->admin->exec("CREATE DATABASE `{$database}`");
        // Teardown only owns a schema after CREATE succeeded.
        $this->database = $database;
        config(['database.default' => 'season_test', 'database.connections.season_test' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $port,
            'database' => $this->database, 'username' => $user, 'password' => $password,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
        ]]);

        // Minimal synthetic schema for the actual SQL used by finalization and its helpers.
        foreach ([
            'CREATE TABLE users (id INT PRIMARY KEY, gold BIGINT NOT NULL DEFAULT 0, spirit_stones INT NOT NULL DEFAULT 0) ENGINE=InnoDB',
            "CREATE TABLE seasons (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120), slug VARCHAR(64) UNIQUE, starts_at DATETIME, ends_at DATETIME, status VARCHAR(16), weight_pvp DECIMAL(12,6) DEFAULT 1, weight_boss DECIMAL(12,6) DEFAULT 0.01, weight_cultivation DECIMAL(12,6) DEFAULT 1, weight_sect DECIMAL(12,6) DEFAULT 5, INDEX idx_status_ends (status, ends_at), INDEX idx_active (status, starts_at, ends_at)) ENGINE=InnoDB",
            'CREATE TABLE season_rankings (season_id INT, user_id INT, total_score BIGINT DEFAULT 0, score_pvp INT DEFAULT 0, score_world_boss BIGINT DEFAULT 0, score_cultivation INT DEFAULT 0, score_sect INT DEFAULT 0, rank_overall INT NULL, rank_pvp INT NULL, rank_boss INT NULL, rank_cultivation INT NULL, rank_sect INT NULL, PRIMARY KEY (season_id, user_id)) ENGINE=InnoDB',
            'CREATE TABLE titles (id INT PRIMARY KEY, name VARCHAR(100)) ENGINE=InnoDB',
            'CREATE TABLE user_titles (user_id INT, title_id INT, PRIMARY KEY (user_id, title_id)) ENGINE=InnoDB',
            'CREATE TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, type VARCHAR(40), title VARCHAR(255), message TEXT, related_id INT NULL, related_type VARCHAR(40) NULL) ENGINE=InnoDB',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::statement('ALTER TABLE users ADD username VARCHAR(30) NULL');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
        foreach (range(13, 18) as $id) {
            DB::table('titles')->insert(['id' => $id, 'name' => 'Synthetic title '.$id]);
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->database !== null) {
                DB::disconnect('season_test');
                $this->admin->exec("DROP DATABASE `{$this->database}`");
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function season(string $offset, string $status = 'active'): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => 'Synthetic season', 'slug' => bin2hex(random_bytes(8)),
            'starts_at' => DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'ends_at' => DB::raw('DATE_ADD(NOW(), INTERVAL '.$offset.')'), 'status' => $status,
        ]);
    }
}
