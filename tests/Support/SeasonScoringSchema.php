<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/** Extract allowlisted table definitions only; NEVER import the bundled SQL script. */
final class SeasonScoringSchema
{
    public const TABLES = [
        'realms', 'users', 'dao_records', 'dao_paths', 'dao_techniques',
        'cultivation_manuals', 'user_cultivation_manuals',
        'battles', 'battle_logs', 'battle_challenges', 'notifications',
        'sects', 'sect_members', 'item_templates', 'inventory', 'equipment_slots',
        'world_regions', 'world_boss_templates', 'world_bosses', 'boss_damage_log', 'boss_rewards',
        'global_announcements', 'world_state', 'sect_bases', 'sect_buildings', 'sect_npcs',
        'daily_tasks', 'weekly_tasks', 'titles', 'user_titles', 'seasons', 'season_rankings',
        'bloodlines', 'bloodline_mutation_templates', 'user_bloodlines', 'bloodline_evolution',
        'bloodline_abilities', 'bloodline_interactions', 'tribulations', 'dungeons', 'dungeon_runs',
        'cave_environments', 'cave_formations', 'player_caves', 'player_cave_formations',
        'artifacts', 'user_artifacts',
    ];

    public static function statements(): array
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/legacy/database_full.sql');
        if ($source === false) {
            throw new RuntimeException('Bundled fixture schema cannot be read.');
        }
        $statements = [];
        foreach (self::TABLES as $table) {
            $pattern = '/^CREATE TABLE IF NOT EXISTS '.preg_quote($table, '/').' \(\R.*?^\) ENGINE=InnoDB[^;]*;/ms';
            if (preg_match_all($pattern, $source, $matches) !== 1) {
                throw new RuntimeException('Missing or ambiguous fixture definition: '.$table);
            }
            $statements[] = $matches[0][0];
        }
        // These bundled extensions supply the columns used by the real services. No seed INSERTs,
        // CREATE DATABASE, USE, routines, migrations, or arbitrary ALTER statements are executed.
        preg_match_all('/^ALTER TABLE (?:users|realms)\R.*?;/ms', $source, $extensions);
        if (count($extensions[0]) !== 6) {
            throw new RuntimeException('Review changed user/realm fixture extensions before running.');
        }

        return [...$statements, ...$extensions[0]];
    }
}
