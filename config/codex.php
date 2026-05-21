<?php

declare(strict_types=1);

return [
    'categories' => [
        'realms' => [
            'label' => 'Realms',
            'icon' => '☁️',
            'description' => 'Cultivation realms and the path between them.',
        ],
        'bloodlines' => [
            'label' => 'Bloodlines',
            'icon' => '🩸',
            'description' => 'Ancestral legacies awakened through trial.',
        ],
        'monsters' => [
            'label' => 'Monsters',
            'icon' => '👹',
            'description' => 'Beasts, shades, and foes met on the road.',
        ],
        'artifacts' => [
            'label' => 'Artifacts',
            'icon' => '✦',
            'description' => 'Relics of power bound to cultivators.',
        ],
        'regions' => [
            'label' => 'Regions',
            'icon' => '🗺️',
            'description' => 'Lands charted by your wandering spirit.',
        ],
        'manuals' => [
            'label' => 'Manuals',
            'icon' => '📜',
            'description' => 'Scriptures and cultivation treatises.',
        ],
        'sects' => [
            'label' => 'Sects',
            'icon' => '🏛️',
            'description' => 'Factions that shape the jianghu.',
        ],
        'history' => [
            'label' => 'History & Lore',
            'icon' => '📖',
            'description' => 'Chronicles of heavens, eras, and fate.',
        ],
    ],

    'history_milestones' => [
        'first_exploration' => [
            'event_types' => ['encounter', 'exploration'],
            'min_count' => 1,
        ],
        'first_tribulation' => [
            'event_types' => ['tribulation', 'tribulation_success', 'breakthrough'],
            'min_count' => 1,
        ],
        'first_manual' => [
            'event_types' => ['manual_acquisition'],
            'min_count' => 1,
        ],
        'first_dungeon' => [
            'event_types' => ['dungeon_run', 'dungeon_clear', 'dungeon_complete'],
            'min_count' => 1,
        ],
        'world_boss_witness' => [
            'event_types' => ['world_boss', 'world_boss_damage', 'world_boss_kill'],
            'min_count' => 1,
        ],
    ],

    'ui' => [
        'particles' => true,
        'ambient_sound_key' => 'codex_ambient',
    ],
];
