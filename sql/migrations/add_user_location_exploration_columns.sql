-- World map exploration: burst of 10 explores, then 30-minute long rest (see ExplorationService).
-- Safe to run once on existing databases that predate these columns.

ALTER TABLE user_location
    ADD COLUMN explore_burst_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_explore_at,
    ADD COLUMN explore_blocked_until DATETIME NULL DEFAULT NULL AFTER explore_burst_count;
