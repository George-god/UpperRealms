<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameDaoRecord;
use PDO;
use Throwable;

/**
 * Centralized Heavenly Dao record logging (Eloquent + read helpers).
 */
final class DaoRecordLogger
{
    public static function log(
        string $eventType,
        int $userId,
        ?int $targetId,
        string $description,
        array $contextData = [],
        ?PDO $db = null,
    ): bool {
        if ($eventType === '' || $userId <= 0 || trim($description) === '') {
            return false;
        }

        if ($db !== null) {
            return self::logViaPdo($db, $eventType, $userId, $targetId, $description, $contextData);
        }

        try {
            GameDaoRecord::query()->create([
                'event_type' => $eventType,
                'user_id' => $userId,
                'target_id' => $targetId,
                'description' => trim($description),
                'context_data' => $contextData !== [] ? $contextData : null,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('DaoRecordLogger::log '.$e->getMessage());

            return false;
        }
    }

    private static function logViaPdo(
        PDO $db,
        string $eventType,
        int $userId,
        ?int $targetId,
        string $description,
        array $contextData,
    ): bool {
        try {
            $stmt = $db->prepare('
                INSERT INTO dao_records (event_type, user_id, target_id, description, context_data)
                VALUES (?, ?, ?, ?, ?)
            ');
            $json = $contextData !== [] ? json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            if ($json === false) {
                $json = null;
            }
            $stmt->execute([
                $eventType,
                $userId,
                $targetId,
                trim($description),
                $json,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('DaoRecordLogger::logViaPdo '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getRecords(array $filters = [], int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $q = GameDaoRecord::query()
            ->join('users as u', 'u.id', '=', 'dao_records.user_id')
            ->select('dao_records.*', 'u.username');

        if (! empty($filters['event_type'])) {
            $q->where('dao_records.event_type', (string) $filters['event_type']);
        }
        if (! empty($filters['user_id'])) {
            $q->where('dao_records.user_id', (int) $filters['user_id']);
        }

        /** @var list<array<string, mixed>> */
        return $q->orderByDesc('dao_records.created_at')
            ->orderByDesc('dao_records.id')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getRecordsForUser(int $userId, int $limit = 100): array
    {
        return self::getRecords(['user_id' => $userId], $limit);
    }

    /**
     * @return list<string>
     */
    public static function getEventTypes(): array
    {
        try {
            return GameDaoRecord::query()
                ->select('event_type')
                ->distinct()
                ->orderBy('event_type')
                ->pluck('event_type')
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();
        } catch (Throwable $e) {
            error_log('DaoRecordLogger::getEventTypes '.$e->getMessage());

            return [];
        }
    }
}
