<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeasonScoringDiagnostics;
use Tests\Support\SeasonScoringEnvironment;
use Tests\Support\SeasonScoringPdo;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
SeasonScoringEnvironment::validate($input);
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')
    || ! is_dir($input['directory']) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $input['worker'])
    || ! in_array($input['path'], ['laravel', 'legacy'], true)) {
    throw new RuntimeException('Unsafe scoring worker configuration.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useStoragePath($input['directory'].'/storage');
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Scoring worker must bootstrap with in-memory defaults.');
}
config(['database.default' => 'scoring_worker', 'database.connections.scoring_worker' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $input['port'],
    'database' => $input['database'], 'username' => $input['username'], 'password' => $input['password'],
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
]]);
$pdo = new SeasonScoringPdo($input);
DB::connection()->setPdo($pdo);
DB::connection()->setReadPdo($pdo);
$root = dirname(__DIR__, 2);
ini_set('error_log', $input['directory'].'/diagnostics.log');
if ($input['path'] === 'legacy') {
    // No legacy HTTP bootstrap or fallback configuration. Each child owns its private cache root.
    define('APP_ROOT', $input['directory'].'/legacy');
    if (! mkdir(APP_ROOT, 0700, true) && ! is_dir(APP_ROOT)) {
        throw new RuntimeException('Cannot create isolated legacy cache root.');
    }
    require_once $root.'/legacy/config/database.php';
    spl_autoload_register(static function (string $class) use ($root): void {
        foreach (['Game\\Service\\' => 'services', 'Game\\Core\\' => 'core'] as $prefix => $folder) {
            if (str_starts_with($class, $prefix)) {
                $leaf = substr($class, strlen($prefix));
                if (preg_match('/^[A-Za-z]+$/D', $leaf)) {
                    $file = $root.'/legacy/'.$folder.'/'.$leaf.'.php';
                    if (is_file($file)) {
                        require_once $file;
                    }
                }
            }
        }
    });
    Game\Config\Database::setConfig([
        'host' => '127.0.0.1', 'port' => (string) $input['port'], 'dbname' => $input['database'],
        'username' => $input['username'], 'password' => $input['password'], 'charset' => 'utf8mb4',
    ]);
    (new ReflectionProperty(Game\Config\Database::class, 'instance'))->setValue(null, $pdo);
}
date_default_timezone_set('UTC');
mt_srand(20261102);
$namespace = $input['path'] === 'legacy' ? 'Game\\Service\\' : 'App\\Services\\';
$service = static function (string $name) use ($namespace): object {
    $class = $namespace.$name;

    return new $class;
};
try {
    $result = match ($input['operation']) {
        'cultivate' => $service('CultivationService')->cultivate(1),
        'donate' => $service('SectService')->donate(1, 350),
        'battle' => $service('BattleService')->simulateBattle(1, 2, ['attacker_use_techniques' => false, 'defender_use_techniques' => false]),
        'challenge' => $service('ChallengeService')->acceptChallenge((int) $input['challenge_id'], 2, false),
        'boss' => $service('WorldBossService')->attack(1),
        'boss_rewards' => (static function () use ($service): array {
            $boss = $service('WorldBossService');
            $method = new ReflectionMethod($boss, 'getRewardForRank');

            return array_map(fn (int $rank) => $method->invoke($boss, $rank), [1, 2, 3, 4, 10, 11]);
        })(),
        'categories' => (static function () use ($service): array {
            $season = $service('SeasonService');
            $season->onPvpWin(1);
            $season->onWorldBossDamage(1, 123);
            $season->onCultivation(1, 29);
            $season->onSectContributionUnits(1, 3);

            return ['completed' => true];
        })(),
        default => throw new RuntimeException('Unknown scoring test operation.'),
    };
} catch (Throwable $error) {
    $result = ['caught_type' => $error::class, 'caught_code' => (string) $error->getCode()];
}
$diagnostics = SeasonScoringDiagnostics::fromFile(
    $input['directory'].'/diagnostics.log', $input['operation'], $input['fault'] ?? '',
    [$input['password'], $input['username'], (string) getenv('SEASON_TEST_INSTANCE_TOKEN'), (string) getenv('APP_KEY')]
);
echo json_encode([...$diagnostics, 'result' => $result, 'events' => $pdo->events, 'transaction_open' => $pdo->inTransaction(),
    'connection_id' => (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn()], JSON_THROW_ON_ERROR);
