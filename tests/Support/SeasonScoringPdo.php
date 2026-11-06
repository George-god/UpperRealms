<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOException;
use PDOStatement;

/** Test-only observation/injection around real SQL. Not a production scoring implementation. */
final class SeasonScoringPdo extends PDO
{
    public array $events = [];
    public array $eventTimes = [];

    private array $observed = [];

    public function __construct(private array $config)
    {
        SeasonScoringEnvironment::validate($config);
        parent::__construct(
            'mysql:host=127.0.0.1;port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',
            $config['username'], $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_PERSISTENT => false]
        );
        $this->exec('SET SESSION max_statement_time = 10');
        $this->exec('SET SESSION innodb_lock_wait_timeout = 5');
        $this->exec('SET SESSION lock_wait_timeout = 5');
        SeasonScoringEnvironment::verifyServer($this);
        $this->exec("SET time_zone = '+00:00'");
        if (isset($config['clock'])) {
            if (! preg_match('/^[0-9]{10}(\.[0-9]{1,6})?$/D', (string) $config['clock'])) {
                throw new \RuntimeException('Invalid test database clock.');
            }
            $this->exec('SET timestamp = '.$config['clock']);
        }
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SeasonScoringStatement::class, [$this]]);
        if (isset($config['directory'], $config['worker'])) {
            if (file_put_contents($config['directory'].'/'.$config['worker'].'.connection', (string) $this->query('SELECT CONNECTION_ID()')->fetchColumn()) === false) {
                throw new \RuntimeException('Cannot publish test connection identity.');
            }
        }
    }

    public function event(string $event): void
    {
        $this->events[] = $event;
        $this->eventTimes[$event][] = hrtime(true);
        if (isset($this->observed[$event])) {
            return;
        }
        $this->observed[$event] = true;
        if (($this->config['advance_at'] ?? '') === $event) {
            $clock = (string) ($this->config['advance_clock'] ?? '');
            if (!preg_match('/^[0-9]{10}$/D', $clock)) { throw new \RuntimeException('Invalid advancing test clock.'); }
            $this->exec('SET timestamp = '.$clock);
        }
        if (($this->config['barrier'] ?? '') === $event) {
            SeasonScoringBarrier::point($this->config['directory'], $this->config['worker'], $event);
        }
        if (($this->config['fault'] ?? '') === $event) {
            throw new PDOException('Synthetic scoring fault: '.$event, 45000);
        }
    }

    public function beginTransaction(): bool
    {
        $this->event('before_begin');
        $result = parent::beginTransaction();
        $this->event('after_begin');

        return $result;
    }

    public function commit(): bool
    {
        $this->event('before_commit');
        $result = parent::commit();
        // The real server commit above must succeed before acknowledgement loss is injected.
        $this->event('after_commit');

        return $result;
    }

    public function rollBack(): bool
    {
        $this->event('before_rollback');
        if (($this->config['rollback'] ?? '') === 'false') {
            return false;
        }
        if (($this->config['rollback'] ?? '') === 'throw') {
            throw new PDOException('Synthetic rollback failure.', 45000);
        }
        $result = parent::rollBack();
        $this->event('after_rollback');

        return $result;
    }
}

final class SeasonScoringStatement extends PDOStatement
{
    protected function __construct(private SeasonScoringPdo $owner) {}

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);
        $sql = preg_replace('/\s+/', ' ', trim($this->queryString));
        if (str_starts_with($sql, 'SELECT id, name, slug, starts_at')) {
            $this->owner->event('after_season_lookup');
        }

        return $row;
    }

    public function execute(?array $params = null): bool
    {
        $sql = preg_replace('/\s+/', ' ', trim($this->queryString));
        $event = match (true) {
            str_contains($sql, 'GET_LOCK(') => 'acquire',
            str_contains($sql, 'RELEASE_LOCK(') => 'release',
            str_starts_with($sql, 'INSERT INTO season_gameplay_operations') => 'ledger',
            str_starts_with($sql, 'UPDATE users SET chi') => 'gameplay',
            str_starts_with($sql, 'UPDATE users SET strength') => 'attribute_growth',
            str_starts_with($sql, 'INSERT INTO notifications') => 'notification',
            str_starts_with($sql, 'INSERT IGNORE INTO player_caves') => 'cave',
            str_starts_with($sql, 'SELECT id, weight_pvp') => 'admission',
            str_starts_with($sql, 'INSERT INTO season_rankings') => 'score_row',
            str_starts_with($sql, 'UPDATE season_rankings SET total_score') => 'total',
            str_starts_with($sql, 'UPDATE season_rankings SET score_') => 'category',
            str_starts_with($sql, 'INSERT INTO seasons') => 'replacement',
            default => null,
        };
        if ($event !== null) {
            $this->owner->event('before_'.$event);
        }
        $result = parent::execute($params);
        if ($event !== null) {
            $this->owner->event('after_'.$event);
        }

        return $result;
    }
}
