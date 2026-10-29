<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;
use RuntimeException;

/** Pauses real database operations at deterministic interleaving boundaries. */
final class SeasonBarrierPdo extends PDO
{
    public function __construct(array $config)
    {
        parent::__construct(
            "mysql:host=127.0.0.1;port={$config['port']};dbname={$config['database']};charset=utf8mb4",
            $config['username'], $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]
        );
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SeasonBarrierStatement::class, [$config]]);
        $this->exec('SET SESSION innodb_lock_wait_timeout = 5');
        $this->exec('SET SESSION max_statement_time = 10');
        $this->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        if (isset($config['clock'])) {
            $this->exec('SET timestamp = '.(int) $config['clock']);
        }
        file_put_contents($config['directory'].'/'.$config['name'].'.connection', (string) $this->query('SELECT CONNECTION_ID()')->fetchColumn());
    }
}

final class SeasonBarrierStatement extends PDOStatement
{
    private bool $paused = false;

    protected function __construct(private array $config) {}

    public function execute(?array $params = null): bool
    {
        if (str_contains($this->queryString, 'FOR UPDATE')) {
            file_put_contents($this->config['directory'].'/'.$this->config['name'].'.attempt', '1');
        }

        return parent::execute($params);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = parent::fetchAll($mode, ...$args);
        if ($this->config['barrier'] === 'locked' && str_contains($this->queryString, 'FOR UPDATE') && $rows !== []) {
            $this->pause();
        }

        return $rows;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);
        $sql = preg_replace('/\s+/', ' ', trim($this->queryString));
        if ($this->config['barrier'] === 'create' && $row === false && str_starts_with($sql, 'SELECT id FROM seasons WHERE status =')) {
            $this->pause();
        }
        if ($this->config['barrier'] === 'score' && $row !== false && str_starts_with($sql, 'SELECT id, name, slug, starts_at')) {
            $this->pause();
        }

        return $row;
    }

    private function pause(): void
    {
        if ($this->paused) {
            return;
        }
        $this->paused = true;
        $prefix = $this->config['directory'].'/'.$this->config['name'];
        file_put_contents($prefix.'.ready', '1');
        $deadline = microtime(true) + 12;
        while (! is_file($prefix.'.release')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Test barrier timed out.');
            }
            usleep(10000);
            clearstatcache();
        }
    }
}
