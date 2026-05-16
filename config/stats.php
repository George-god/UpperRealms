<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Player stat cache
    |--------------------------------------------------------------------------
    */
    'cache_enabled' => (bool) env('STAT_CACHE_ENABLED', true),
    'cache_ttl_seconds' => (int) env('STAT_CACHE_TTL', 300),
    'cache_prefix' => 'player_stats',

    /*
    |--------------------------------------------------------------------------
    | Debug / observability
    |--------------------------------------------------------------------------
    */
    'debug_stats' => (bool) env('GAME_DEBUG_STATS', false),
    'debug_combat' => (bool) env('GAME_DEBUG_COMBAT', false),
    'log_dao_records_debug' => (bool) env('GAME_LOG_DAO_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Bonus scaling — prefer additive % pools over chained multipliers
    |--------------------------------------------------------------------------
    */
    'use_additive_percent_pool' => (bool) env('STAT_ADDITIVE_POOL', true),

    /** Max total additive % per core stat from pooled sources (dao, bloodline, artifact, title, manual, scroll). */
    'max_pooled_attack_pct' => (float) env('STAT_MAX_POOLED_ATTACK_PCT', 2.5),
    'max_pooled_defense_pct' => (float) env('STAT_MAX_POOLED_DEFENSE_PCT', 2.5),
    'max_pooled_max_chi_pct' => (float) env('STAT_MAX_POOLED_MAX_CHI_PCT', 2.5),

    /*
    |--------------------------------------------------------------------------
    | Queues — heavy work off the request thread
    |--------------------------------------------------------------------------
    */
    'queue' => env('GAME_QUEUE', 'default'),

    /** When true, stat invalidation dispatches background codex unlock sync. */
    'queue_codex_on_invalidate' => (bool) env('STAT_QUEUE_CODEX_SYNC', false),
];
