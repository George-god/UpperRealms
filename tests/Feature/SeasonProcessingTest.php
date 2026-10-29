<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SeasonService;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Opt-in MariaDB characterization tests. Never use DB_* or an existing schema.
 * Each test creates and drops its own random database on a disposable local server.
 */
class SeasonProcessingTest extends TestCase
{
    private ?PDO $admin = null;

    private ?string $database = null;

    protected function setUp(): void
    {
        if (getenv('SEASON_TEST_MARIADB') !== '1') {
            $this->markTestSkipped('Requires opt-in disposable loopback MariaDB; see docs/season-processing-tests.md.');
        }

        parent::setUp();

        $this->assertTrue($this->app->environment('testing'));
        $this->assertContains('mysql', PDO::getAvailableDrivers(), 'pdo_mysql is required.');
        $port = (int) (getenv('SEASON_TEST_PORT') ?: 3307);
        $user = getenv('SEASON_TEST_USER') ?: 'root';
        $password = getenv('SEASON_TEST_PASSWORD') ?: '';
        // Host is deliberately not configurable. This must be a disposable local instance.
        $this->admin = new PDO("mysql:host=127.0.0.1;port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database = 'upperrealms_season_test_'.bin2hex(random_bytes(8));
        $this->admin->exec("CREATE DATABASE `{$this->database}`");
        config(['database.default' => 'season_test', 'database.connections.season_test' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $port,
            'database' => $this->database, 'username' => $user, 'password' => $password,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
        ]]);

        // Minimal synthetic schema for the actual SQL used by finalization and its helpers.
        foreach ([
            'CREATE TABLE users (id INT PRIMARY KEY, gold BIGINT NOT NULL DEFAULT 0, spirit_stones INT NOT NULL DEFAULT 0) ENGINE=InnoDB',
            "CREATE TABLE seasons (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120), slug VARCHAR(64) UNIQUE, starts_at DATETIME, ends_at DATETIME, status VARCHAR(16), weight_pvp DECIMAL(12,6) DEFAULT 1, weight_boss DECIMAL(12,6) DEFAULT 0.01, weight_cultivation DECIMAL(12,6) DEFAULT 1, weight_sect DECIMAL(12,6) DEFAULT 5) ENGINE=InnoDB",
            'CREATE TABLE season_rankings (season_id INT, user_id INT, total_score BIGINT DEFAULT 0, score_pvp INT DEFAULT 0, score_world_boss BIGINT DEFAULT 0, score_cultivation INT DEFAULT 0, score_sect INT DEFAULT 0, rank_overall INT NULL, rank_pvp INT NULL, rank_boss INT NULL, rank_cultivation INT NULL, rank_sect INT NULL, PRIMARY KEY (season_id, user_id)) ENGINE=InnoDB',
            'CREATE TABLE titles (id INT PRIMARY KEY, name VARCHAR(100)) ENGINE=InnoDB',
            'CREATE TABLE user_titles (user_id INT, title_id INT, PRIMARY KEY (user_id, title_id)) ENGINE=InnoDB',
            'CREATE TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, type VARCHAR(40), title VARCHAR(255), message TEXT, related_id INT NULL, related_type VARCHAR(40) NULL) ENGINE=InnoDB',
        ] as $sql) {
            DB::statement($sql);
        }
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

    public function test_rewards_ranks_titles_notifications_and_repeat_execution(): void
    {
        $season = $this->season('-1 DAY');
        foreach (range(1, 12) as $id) {
            DB::table('users')->insert(['id' => $id]);
            DB::table('season_rankings')->insert([
                'season_id' => $season, 'user_id' => $id,
                'total_score' => $id === 12 ? 0 : 100,
                'score_pvp' => $id === 2 ? 10 : 0,
                'score_world_boss' => $id === 3 ? 10 : 0,
                'score_cultivation' => $id === 4 ? 10 : 0,
                'score_sect' => $id === 5 ? 10 : 0,
            ]);
        }
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['processed']);
        $this->assertSame(17100, $result['detail'][0]['rewards']['gold']);
        foreach (range(1, 12) as $id) {
            $row = DB::table('users')->find($id);
            $gold = match (true) { $id === 1 => 5000, $id <= 3 => 2500, $id <= 10 => 1000, $id === 11 => 100, default => 0 };
            $stones = match (true) { $id === 1 => 50, $id <= 3 => 25, $id <= 10 => 10, default => 0 };
            $this->assertSame($gold, (int) $row->gold);
            $this->assertSame($stones, (int) $row->spirit_stones);
            $this->assertSame($id, (int) DB::table('season_rankings')->where('user_id', $id)->value('rank_overall'));
        }
        foreach ([[1, 13], [2, 14], [3, 14], [2, 15], [3, 16], [4, 17], [5, 18]] as [$user, $title]) {
            $this->assertTrue(DB::table('user_titles')->where(['user_id' => $user, 'title_id' => $title])->exists());
        }
        $this->assertSame(14, DB::table('notifications')->where('type', 'season_reward')->count());
        $before = DB::table('notifications')->count();
        $again = (new SeasonService)->processEndedSeasons();
        $this->assertSame(0, $again['processed']);
        $this->assertSame(17100, (int) DB::table('users')->sum('gold'));
        $this->assertSame($before, DB::table('notifications')->count());
        $this->assertSame(7, DB::table('user_titles')->count());
    }

    public function test_only_expired_active_seasons_are_processed_in_id_order(): void
    {
        $first = $this->season('-2 DAY');
        $future = $this->season('+2 DAY');
        $ended = $this->season('-3 DAY', 'ended');
        $last = $this->season('-1 DAY');
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertSame([$first, $last], array_column($result['detail'], 'season_id'));
        $this->assertSame('active', DB::table('seasons')->where('id', $future)->value('status'));
        $this->assertSame('ended', DB::table('seasons')->where('id', $ended)->value('status'));
        $this->assertSame(4, DB::table('seasons')->count());
    }

    public function test_empty_database_gets_one_28_day_season_with_existing_weights(): void
    {
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['processed']);
        $row = DB::table('seasons')->first();
        $this->assertSame('active', $row->status);
        $this->assertSame(28, (int) DB::scalar('SELECT DATEDIFF(ends_at, starts_at) FROM seasons'));
        $this->assertEquals([1, 0.01, 1, 5], [$row->weight_pvp, $row->weight_boss, $row->weight_cultivation, $row->weight_sect]);
        (new SeasonService)->processEndedSeasons();
        $this->assertSame(1, DB::table('seasons')->count());
    }

    public function test_failure_rolls_back_ranks_and_season_status(): void
    {
        $id = $this->season('-1 DAY');
        DB::table('users')->insert(['id' => 1]);
        DB::table('season_rankings')->insert(['season_id' => $id, 'user_id' => 1, 'total_score' => 10]);
        DB::statement('ALTER TABLE users DROP COLUMN gold');
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['processed']);
        $this->assertSame('active', DB::table('seasons')->where('id', $id)->value('status'));
        $this->assertNull(DB::table('season_rankings')->value('rank_overall'));
        $this->assertSame(0, DB::table('user_titles')->count());
    }

    public function test_category_ties_use_lowest_user_id_and_zero_categories_grant_no_title(): void
    {
        $season = $this->season('-1 DAY');
        foreach ([2, 1] as $id) {
            DB::table('users')->insert(['id' => $id]);
            DB::table('season_rankings')->insert([
                'season_id' => $season, 'user_id' => $id, 'total_score' => 10, 'score_pvp' => 10,
            ]);
        }
        $this->assertTrue((new SeasonService)->processEndedSeasons()['ok']);
        $this->assertSame([1], DB::table('user_titles')->where('title_id', 15)->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('user_titles')->whereIn('title_id', [16, 17, 18])->count());
        $this->assertSame(1, (int) DB::table('season_rankings')->where('user_id', 1)->value('rank_pvp'));
    }

    public function test_exact_expiry_boundary_is_not_yet_finalized(): void
    {
        // Freeze this disposable connection's database clock; application time mocking does not affect NOW().
        DB::statement('SET timestamp = 1800000000');
        $id = $this->season('0 SECOND');
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['processed']);
        $this->assertSame('active', DB::table('seasons')->where('id', $id)->value('status'));
        // Existing behavior: equality is neither finalizable (<) nor a current active season (>).
        $this->assertSame(2, DB::table('seasons')->count());
    }

    private function season(string $offset, string $status = 'active'): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => 'Synthetic season', 'slug' => bin2hex(random_bytes(8)),
            'starts_at' => DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'ends_at' => DB::raw('DATE_ADD(NOW(), INTERVAL '.$offset.')'), 'status' => $status,
        ]);
    }
}
