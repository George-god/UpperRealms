<?php

declare(strict_types=1);

// Test worker only. No HTTP bootstrap, Artisan command, or normal DB_* connection.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeasonBarrierPdo;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require_once __DIR__.'/SeasonBarrierPdo.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (getenv('SEASON_TEST_DISPOSABLE') !== '1' || getenv('APP_ENV') !== 'testing'
    || ! preg_match('/^upperrealms_season_test_[a-f0-9]{16}$/D', $input['database'] ?? '')
    || (int) ($input['port'] ?? 0) === 3306 || ! is_dir($input['directory'])
    || ! preg_match('/^[a-z]+$/D', $input['name'] ?? '')
    || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Unsafe test worker configuration.');
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Worker must bootstrap with in-memory SQLite defaults.');
}
config(['database.default' => 'season_worker', 'database.connections.season_worker' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $input['port'],
    'database' => $input['database'], 'username' => $input['username'], 'password' => $input['password'],
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
]]);
$pdo = new SeasonBarrierPdo($input);
DB::connection()->setPdo($pdo);
DB::connection()->setReadPdo($pdo);

if ($input['path'] === 'legacy') {
    require_once dirname(__DIR__, 2).'/legacy/core/bootstrap.php';
    require_once dirname(__DIR__, 2).'/legacy/services/SeasonService.php';
    // Install only the test connection; never open the legacy singleton's fallback connection.
    $property = new ReflectionProperty(Game\Config\Database::class, 'instance');
    $property->setValue(null, $pdo);
    $service = new Game\Service\SeasonService;
} else {
    $service = new App\Services\SeasonService;
}

$result = match ($input['operation']) {
    'process' => $service->processEndedSeasons(),
    'create' => (function () use ($service) { $service->ensureActiveSeasonExists(); return ['created_call_completed' => true]; })(),
    'page' => $service->getPageData(1, 'overall'),
    'score' => (function () use ($service) { $service->onPvpWin(1); return ['score_call_completed' => true]; })(),
    default => throw new RuntimeException('Unknown test operation.'),
};
echo json_encode($result, JSON_THROW_ON_ERROR);
