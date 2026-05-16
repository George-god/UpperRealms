<?php
declare(strict_types=1);

/**
 * Phase 1 bootstrap: config and database only.
 * Include before any page logic. Does not start session.
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/database.php';

use Game\Config\Database;

$applyLaravelMysqlConfig = static function (): bool {
    if (! function_exists('app') || ! function_exists('config')) {
        return false;
    }
    try {
        if (! app()->bound('config')) {
            return false;
        }
    } catch (\Throwable) {
        return false;
    }
    $connName = config('database.default');
    $c = config("database.connections.{$connName}");
    if (! is_array($c)) {
        return false;
    }
    $driver = $c['driver'] ?? '';
    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
        return false;
    }
    Database::setConfig([
        'host' => (string) ($c['host'] ?? '127.0.0.1'),
        'port' => (string) ($c['port'] ?? '3306'),
        'dbname' => (string) ($c['database'] ?? 'cultivation_rpg'),
        'username' => (string) ($c['username'] ?? 'root'),
        'password' => (string) ($c['password'] ?? ''),
        'charset' => (string) ($c['charset'] ?? 'utf8mb4'),
    ]);

    return true;
};

if (! $applyLaravelMysqlConfig()) {
    $port = getenv('DB_PORT');
    Database::setConfig([
        'host' => getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: '127.0.0.1'),
        'port' => $port !== false && $port !== '' ? (string) $port : null,
        'dbname' => getenv('DB_DATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'cultivation_rpg'),
        'username' => getenv('DB_USERNAME') ?: (getenv('MYSQL_USER') ?: 'root'),
        'password' => getenv('DB_PASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: ''),
        'charset' => 'utf8mb4',
    ]);
}

/**
 * Safe when Laravel has already started the legacy session in LegacyPhpController.
 */
if (! function_exists('legacy_session_start')) {
    function legacy_session_start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

$gameStatInvalidate = dirname(__DIR__, 2).'/app/Support/Gameplay/game_stat_invalidate.php';
if (is_file($gameStatInvalidate)) {
    require_once $gameStatInvalidate;
}
