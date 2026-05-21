<?php

declare(strict_types=1);

return [
    'starting' => [
        'strength' => 5,
        'agility' => 5,
        'vitality' => 5,
        'spirit' => 5,
        'soul' => 5,
        'willpower' => 5,
    ],

    'stat_cap' => 999,

    'minor_level' => [
        'growth_rolls' => 2,
        'min_per_stat' => 1,
        'max_per_stat' => 2,
    ],

    'breakthrough' => [
        'base_points' => 5,
        'per_realm_bonus' => 2,
    ],

    'respec' => [
        'spirit_stone_cost' => 500,
        'free_respecs' => 1,
    ],

    /**
     * Flat combat bonuses from primaries (added after full pipeline on final stats).
     */
    'derived' => [
        'attack' => ['strength' => 2.2, 'spirit' => 0.6],
        'defense' => ['vitality' => 1.6, 'willpower' => 0.4],
        'max_chi' => ['spirit' => 10, 'vitality' => 6],
        'crit_rate' => ['base' => 0.03, 'agility' => 0.0025, 'soul' => 0.001, 'cap' => 0.45],
        'crit_damage' => ['base' => 1.5, 'soul' => 0.008, 'cap' => 2.75],
        'dodge' => ['base' => 0.02, 'agility' => 0.002, 'cap' => 0.40],
        'attack_speed' => ['base' => 1.0, 'agility' => 0.004, 'cap' => 1.65],
        'cultivation_speed' => ['base' => 0.0, 'spirit' => 0.003, 'cap' => 0.35],
        'technique_power' => ['base' => 0.0, 'spirit' => 0.004, 'soul' => 0.002, 'cap' => 0.40],
        'breakthrough_stability' => ['base' => 0.0, 'soul' => 0.003, 'willpower' => 0.002, 'cap' => 0.30],
        'mental_resistance' => ['base' => 0.0, 'soul' => 0.002, 'willpower' => 0.003, 'cap' => 0.35],
        'tribulation_resistance' => ['base' => 0.0, 'willpower' => 0.004, 'spirit' => 0.001, 'cap' => 0.35],
        'debuff_resistance' => ['base' => 0.0, 'willpower' => 0.003, 'vitality' => 0.001, 'cap' => 0.30],
    ],

    'stats' => [
        'strength' => [
            'label' => 'Strength',
            'icon' => '⚔',
            'color' => 'rose',
            'description' => 'Channels physical force into devastating strikes.',
        ],
        'agility' => [
            'label' => 'Agility',
            'icon' => '💨',
            'color' => 'emerald',
            'description' => 'Swift movement, evasion, and precise critical strikes.',
        ],
        'vitality' => [
            'label' => 'Vitality',
            'icon' => '🛡',
            'color' => 'amber',
            'description' => 'Fortifies the flesh — health and defense.',
        ],
        'spirit' => [
            'label' => 'Spirit',
            'icon' => '✦',
            'color' => 'violet',
            'description' => 'Qi cultivation, technique power, and mana capacity.',
        ],
        'soul' => [
            'label' => 'Soul',
            'icon' => '☯',
            'color' => 'cyan',
            'description' => 'Deepens crit damage, breakthrough stability, and mental resistance.',
        ],
        'willpower' => [
            'label' => 'Willpower',
            'icon' => '🔥',
            'color' => 'orange',
            'description' => 'Endures tribulation lightning and resists debuffs.',
        ],
    ],

    'specializations' => [
        'martial' => [
            'label' => 'Martial Path',
            'description' => 'Physical dominance — amplified Strength and Vitality effects.',
            'bonuses' => ['strength' => 0.12, 'vitality' => 0.12],
        ],
        'phantom' => [
            'label' => 'Phantom Path',
            'description' => 'Elusive assassin — amplified Agility and Soul effects.',
            'bonuses' => ['agility' => 0.12, 'soul' => 0.12],
        ],
        'mystic' => [
            'label' => 'Mystic Path',
            'description' => 'Qi sage — amplified Spirit and Willpower effects.',
            'bonuses' => ['spirit' => 0.12, 'willpower' => 0.12],
        ],
        'harmonious' => [
            'label' => 'Harmonious Path',
            'description' => 'Balanced cultivation — small bonus to all attributes.',
            'bonuses' => ['all' => 0.06],
        ],
    ],

    'body_types' => [
        'heavenly_flame' => [
            'label' => 'Heavenly Flame Body',
            'description' => 'Burning meridians — Spirit and Soul affinities.',
            'affinities' => ['spirit' => 3, 'soul' => 2],
            'bonuses' => ['technique_power' => 0.08, 'crit_damage' => 0.05],
        ],
        'iron_mountain' => [
            'label' => 'Iron Mountain Body',
            'description' => 'Unyielding frame — Strength and Vitality affinities.',
            'affinities' => ['strength' => 3, 'vitality' => 2],
            'bonuses' => ['defense' => 0.10, 'debuff_resistance' => 0.06],
        ],
        'void_soul' => [
            'label' => 'Void Soul Body',
            'description' => 'Hollow spirit core — Soul and Willpower affinities.',
            'affinities' => ['soul' => 3, 'willpower' => 2],
            'bonuses' => ['mental_resistance' => 0.08, 'tribulation_resistance' => 0.06],
        ],
    ],

    'synergies' => [
        [
            'id' => 'iron_body',
            'label' => 'Iron Body Synergy',
            'requires' => ['strength' => 18, 'vitality' => 18],
            'bonuses' => ['defense' => 0.05],
        ],
        [
            'id' => 'wind_dancer',
            'label' => 'Wind Dancer Synergy',
            'requires' => ['agility' => 18, 'soul' => 16],
            'bonuses' => ['crit_rate' => 0.04, 'dodge' => 0.03],
        ],
        [
            'id' => 'sage_mind',
            'label' => 'Sage Mind Synergy',
            'requires' => ['spirit' => 20, 'willpower' => 18],
            'bonuses' => ['cultivation_speed' => 0.06, 'technique_power' => 0.05],
        ],
        [
            'id' => 'perfect_harmony',
            'label' => 'Perfect Harmony',
            'requires' => ['min_all' => 14],
            'bonuses' => ['all_derived' => 0.04],
        ],
    ],
];
