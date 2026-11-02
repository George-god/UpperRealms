<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class BloodlineUnlockExistenceTest extends TestCase
{
    public static function implementations(): array
    {
        return [
            'laravel without active' => [\App\Services\BloodlineService::class, false],
            'laravel with active' => [\App\Services\BloodlineService::class, true],
            'legacy without active' => [\Game\Service\BloodlineService::class, false],
            'legacy with active' => [\Game\Service\BloodlineService::class, true],
        ];
    }

    #[DataProvider('implementations')]
    public function test_unlocks_against_composite_key_without_id(string $class, bool $hasActive): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requires pdo_sqlite; uses only an in-memory database.');
        }
        require_once dirname(__DIR__, 2).'/legacy/config/database.php';
        require_once dirname(__DIR__, 2).'/legacy/services/BloodlineService.php';
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Minimal projections for every query made by syncUnlocksForUser/fetchProgress.
        // Match the authoritative ownership key, deliberately without a synthetic id.
        $pdo->exec('CREATE TABLE user_bloodlines (user_id INTEGER, bloodline_id INTEGER, awakening_level INTEGER DEFAULT 1, is_active INTEGER DEFAULT 0, PRIMARY KEY (user_id, bloodline_id))');
        $pdo->exec('CREATE TABLE bloodlines (id INTEGER PRIMARY KEY, sort_order INTEGER, unlock_type TEXT, unlock_value INTEGER)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, wins INTEGER)');
        $pdo->exec('CREATE TABLE boss_damage_log (user_id INTEGER, damage_dealt INTEGER)');
        $pdo->exec('CREATE TABLE tribulations (user_id INTEGER, success INTEGER)');
        $pdo->exec('CREATE TABLE dungeon_runs (user_id INTEGER, is_completed INTEGER)');
        $pdo->exec('INSERT INTO users VALUES (7, 10)');
        $pdo->exec("INSERT INTO bloodlines VALUES (1, 1, 'pvp_wins', 1), (2, 2, 'pvp_wins', 2), (3, 3, 'pvp_wins', 3), (4, 4, 'pvp_wins', 11)");
        $pdo->exec('INSERT INTO user_bloodlines VALUES (7, 1, 3, '.(int)$hasActive.')');
        $pdo->exec('INSERT INTO user_bloodlines VALUES (8, 1, 1, 1)');

        $legacy = new ReflectionProperty(\Game\Config\Database::class, 'instance');
        $oldLegacy = $legacy->getValue();
        $cache = new ReflectionProperty($class, 'tablesOk');
        $oldCache = $cache->getValue();
        $oldDb = DB::getFacadeRoot();
        try {
            $legacy->setValue(null, $pdo);
            $cache->setValue(null, null);
            $connection = Mockery::mock();
            $connection->shouldReceive('getPdo')->andReturn($pdo);
            $manager = Mockery::mock();
            $manager->shouldReceive('connection')->andReturn($connection);
            DB::swap($manager);
            $service = new $class;
            $this->assertSame(2, $service->syncUnlocksForUser(7));
            $rows = $pdo->query('SELECT bloodline_id, awakening_level, is_active FROM user_bloodlines WHERE user_id = 7 ORDER BY bloodline_id')->fetchAll(PDO::FETCH_NUM);
            $this->assertSame([
                [1, 3, (int)$hasActive],
                [2, 1, (int)!$hasActive],
                [3, 1, 0],
            ], $rows);
            $this->assertSame(0, $service->syncUnlocksForUser(7));
            $this->assertSame($rows, $pdo->query('SELECT bloodline_id, awakening_level, is_active FROM user_bloodlines WHERE user_id = 7 ORDER BY bloodline_id')->fetchAll(PDO::FETCH_NUM));
        } finally {
            $legacy->setValue(null, $oldLegacy);
            $cache->setValue(null, $oldCache);
            if ($oldDb === null) {
                DB::clearResolvedInstance('db');
            } else {
                DB::swap($oldDb);
            }
            Mockery::close();
        }
    }
}