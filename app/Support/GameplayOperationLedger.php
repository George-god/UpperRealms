<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/** Durable rows are never expired or backfilled. Must use the coordinator's root transaction. */
final class GameplayOperationLedger
{
    public static function ddl(): string
    {
        return "CREATE TABLE season_gameplay_operations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            operation_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            fingerprint_version SMALLINT UNSIGNED NOT NULL,
            admitted_at DATETIME(6) NOT NULL,
            season_id BIGINT UNSIGNED NULL,
            outcome LONGTEXT NOT NULL,
            scoring LONGTEXT NOT NULL,
            UNIQUE KEY gameplay_operation_identity (actor_id, action, operation_key)
        ) ENGINE=InnoDB";
    }

    public static function find(PDO $db, int $actor, string $key): ?array
    {
        $stmt = $db->prepare("SELECT * FROM season_gameplay_operations WHERE actor_id = ? AND action = 'cultivation' AND operation_key = ?");
        $stmt->execute([$actor, $key]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function record(PDO $db, int $actor, string $key, string $fingerprint, string $admitted,
        ?int $season, array $outcome, array $scoring): void
    {
        $db->prepare("INSERT INTO season_gameplay_operations
            (actor_id, action, operation_key, fingerprint, fingerprint_version, admitted_at, season_id, outcome, scoring)
            VALUES (?, 'cultivation', ?, ?, ?, ?, ?, ?, ?)")->execute([
                $actor, $key, $fingerprint, GameplayFingerprint::VERSION, $admitted, $season,
                json_encode($outcome, JSON_THROW_ON_ERROR), json_encode($scoring, JSON_THROW_ON_ERROR),
            ]);
    }
}
