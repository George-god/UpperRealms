<?php

declare(strict_types=1);

return [
    'chapter' => 'spirit_veil_prologue',
    'chapter_title' => 'Spirit Veil Forest',

    'locations' => [
        'spirit_veil_forest' => [
            'name' => 'Spirit Veil Forest',
            'tagline' => 'Where mist remembers what mortals forget.',
            'theme' => 'forest',
        ],
        'riverstone_village' => [
            'name' => 'Riverstone Village',
            'tagline' => 'Lanterns on the mountain road.',
            'theme' => 'village',
        ],
    ],

    'npcs' => [
        'lin_mei' => [
            'name' => 'Lin Mei',
            'title' => 'Herbalist Disciple',
            'portrait' => '🌿',
        ],
        'village_elder' => [
            'name' => 'Elder Shen',
            'title' => 'Riverstone Elder',
            'portrait' => '📿',
        ],
        'sect_messenger' => [
            'name' => 'Messenger of the Azure Gate',
            'title' => 'Outer Sect Emissary',
            'portrait' => '⚔️',
        ],
    ],

    'tutorial_combat' => [
        'npc_name' => 'Corrupted Spirit Wolf',
        'npc_key' => 'tutorial_corrupted_spirit_wolf',
    ],

    'step_order' => [
        'awakening',
        'explore_forest',
        'inspect_objects',
        'meet_lin_mei',
        'fight_wolf',
        'qi_condensation',
        'breakthrough_cinematic',
        'village_arrival',
        'quest_herbs',
        'quest_defend',
        'quest_elder',
        'dream_heavens',
        'sect_messenger',
    ],

    'steps' => [
        'awakening' => [
            'location' => 'spirit_veil_forest',
            'title' => 'Awakening',
            'atmosphere' => 'glowing mist · floating particles · unnatural silence',
            'narration' => [
                'Cold ground. Wet leaves. Your lungs pull in air that tastes of copper and rain.',
                'You do not know your name. You do not know how you came to be here.',
                'Above the canopy, spirit-light drifts like embers in reverse—slow, silent, wrong.',
            ],
            'flashbacks' => [
                ['image' => '⚡', 'text' => 'Thunder that does not end.'],
                ['image' => '🔥', 'text' => 'Fire climbing the sky itself.'],
                ['image' => '🌑', 'text' => 'Heaven cracking like thin ice.'],
            ],
            'actions' => [
                ['key' => 'continue', 'label' => 'Push through the mist', 'next' => 'explore_forest'],
            ],
        ],

        'explore_forest' => [
            'location' => 'spirit_veil_forest',
            'title' => 'Spirit Veil Forest',
            'narration' => [
                'This place has a name you do not remember hearing: the Spirit Veil Forest.',
                'Mist clings to ancient roots. Somewhere distant, water falls without sound.',
                'Your skin prickles—as if the forest is watching to see whether you belong.',
            ],
            'actions' => [
                ['key' => 'explore', 'label' => 'Follow the pale light deeper', 'next' => 'inspect_objects'],
            ],
        ],

        'inspect_objects' => [
            'location' => 'spirit_veil_forest',
            'title' => 'Signs in the Mist',
            'narration' => [
                'Two shapes emerge from the fog—one broken, one reverent.',
            ],
            'inspectables' => [
                [
                    'key' => 'relic',
                    'label' => 'Shattered jade plaque',
                    'text' => 'Characters half-erased. The last line reads: “…beyond the veil, none return.”',
                ],
                [
                    'key' => 'shrine',
                    'label' => 'Moss-covered spirit stone',
                    'text' => 'Offerings long rotted away. Fresh claw marks score the stone—recent, hungry.',
                ],
            ],
            'actions' => [
                ['key' => 'continue', 'label' => 'A voice calls from the trees', 'next' => 'meet_lin_mei', 'requires_inspected' => ['relic', 'shrine']],
            ],
        ],

        'meet_lin_mei' => [
            'location' => 'spirit_veil_forest',
            'title' => 'A Living Soul',
            'dialogue' => [
                ['speaker' => 'narrator', 'text' => 'A girl in disciple robes freezes, herb basket raised like a shield.'],
                ['speaker' => 'Lin Mei', 'text' => 'You—how are you breathing? Ordinary mortals die within minutes in the Veil.'],
                ['speaker' => 'Lin Mei', 'text' => 'I am Lin Mei. I came for spirit herbs, not… whatever you are.'],
                ['speaker' => 'Lin Mei', 'text' => 'Cultivators avoid the deep forest. The qi here is sick. Something twisted a spirit beast.'],
                ['speaker' => 'Lin Mei', 'text' => 'If you can stand, help me. If you cannot, I should not be here either.'],
            ],
            'actions' => [
                ['key' => 'continue', 'label' => 'Ready your stance', 'next' => 'fight_wolf'],
            ],
        ],

        'fight_wolf' => [
            'location' => 'spirit_veil_forest',
            'title' => 'Corrupted Spirit Wolf',
            'type' => 'combat',
            'narration' => [
                'A wolf made of mist and malice steps into the clearing. Its eyes burn violet. Lin Mei gasps behind you.',
            ],
            'dialogue' => [
                ['speaker' => 'Lin Mei', 'text' => 'That is the corrupted spirit wolf! Do not let it touch your meridians!'],
            ],
            'combat' => [
                'win_next' => 'qi_condensation',
                'defeat_retry' => true,
            ],
        ],

        'qi_condensation' => [
            'location' => 'spirit_veil_forest',
            'title' => 'First Breath of Qi',
            'narration' => [
                'The wolf dissolves into ash and whining wind. Silence returns—heavier than before.',
                'Pain, then warmth. A thread of energy coils in your lower abdomen, shy as dawn light.',
                'You do not understand the words, but your body knows them: Qi Condensation. The first realm of the path.',
            ],
            'dialogue' => [
                ['speaker' => 'Lin Mei', 'text' => 'You… condensed qi without a manual. That should be impossible for a mortal.'],
            ],
            'actions' => [
                ['key' => 'continue', 'label' => 'Feel the qi settle', 'next' => 'breakthrough_cinematic'],
            ],
        ],

        'breakthrough_cinematic' => [
            'location' => 'spirit_veil_forest',
            'title' => 'Meridians Open',
            'type' => 'cinematic',
            'cinematic' => [
                'lines' => [
                    'Your breath finds rhythm.',
                    'Qi traces a circuit through bone and memory.',
                    'For one heartbeat, the forest bows.',
                ],
                'subtitle' => 'Qi Condensation — First Realm',
            ],
            'actions' => [
                ['key' => 'continue', 'label' => 'Descend toward distant lights', 'next' => 'village_arrival'],
            ],
        ],

        'village_arrival' => [
            'location' => 'riverstone_village',
            'title' => 'Riverstone Village',
            'atmosphere' => 'paper lanterns · mountain wind · wary eyes',
            'narration' => [
                'Lanterns sway along a stone path. Villagers whisper behind shuttered windows.',
                'An old man grips his walking staff. A child hides behind her mother’s skirt.',
                'They look at you as one looks at a storm that learned to walk.',
            ],
            'dialogue' => [
                ['speaker' => 'Villager', 'text' => 'You came through the Veil? No one comes through the Veil.'],
                ['speaker' => 'Lin Mei', 'text' => 'He fought the corrupted wolf. Let him rest—then let Elder Shen judge.'],
            ],
            'actions' => [
                ['key' => 'continue', 'label' => 'Accept shelter for the night', 'next' => 'quest_herbs'],
            ],
        ],

        'quest_herbs' => [
            'location' => 'riverstone_village',
            'title' => 'Quest: Spirit Herbs',
            'quest' => [
                'name' => 'Gather herbs with Lin Mei',
                'objective' => 'Help sort and bind spirit herbs for the village ward.',
            ],
            'dialogue' => [
                ['speaker' => 'Lin Mei', 'text' => 'The ward needs clean herbs. Watch the leaves—if they blacken, discard them.'],
                ['speaker' => 'narrator', 'text' => 'Your hands learn faster than your mind. The village’s fear softens a fraction.'],
            ],
            'actions' => [
                ['key' => 'complete_quest', 'label' => 'Finish the herb bundles', 'next' => 'quest_defend'],
            ],
        ],

        'quest_defend' => [
            'location' => 'riverstone_village',
            'title' => 'Quest: Village Watch',
            'quest' => [
                'name' => 'Defend the village',
                'objective' => 'Drive off weak beasts drawn by corrupted qi.',
            ],
            'narration' => [
                'Night insects fall silent. Three scrawny beasts test the ward—hungry, mindless.',
            ],
            'actions' => [
                ['key' => 'complete_quest', 'label' => 'Hold the line until dawn', 'next' => 'quest_elder'],
            ],
        ],

        'quest_elder' => [
            'location' => 'riverstone_village',
            'title' => 'Elder’s Lesson',
            'dialogue' => [
                ['speaker' => 'Elder Shen', 'text' => 'Child, you carry qi like a wound that chose to heal wrong.'],
                ['speaker' => 'Elder Shen', 'text' => 'Cultivation is not power. It is agreement—between breath, heaven, and self.'],
                ['speaker' => 'Elder Shen', 'text' => 'Meditate when the heart is still. Advance when the foundation is true.'],
                ['speaker' => 'Elder Shen', 'text' => 'The sects will notice you. Prepare before they do.'],
            ],
            'actions' => [
                ['key' => 'continue', 'label' => 'Rest until night deepens', 'next' => 'dream_heavens'],
            ],
        ],

        'dream_heavens' => [
            'location' => 'riverstone_village',
            'title' => 'The Dying Dao',
            'type' => 'dream',
            'dream' => [
                'lines' => [
                    'The sky is a mirror cracked from within.',
                    'Golden chains drag something vast and unseen across the stars.',
                    'A voice without a mouth presses into your bones:',
                ],
                'voice' => 'The Dao is dying.',
            ],
            'actions' => [
                ['key' => 'wake', 'label' => 'Wake gasping', 'next' => 'sect_messenger'],
            ],
        ],

        'sect_messenger' => [
            'location' => 'riverstone_village',
            'title' => 'A Messenger Arrives',
            'atmosphere' => 'morning mist · sect banners · uneasy qi',
            'narration' => [
                'Morning light. A sect messenger in azure trim dismounts at the village gate.',
                'Their gaze lingers on you a heartbeat too long.',
            ],
            'dialogue' => [
                ['speaker' => 'Messenger of the Azure Gate', 'text' => 'Riverstone. I seek a survivor from the Spirit Veil.'],
                ['speaker' => 'Messenger of the Azure Gate', 'text' => '…You. Your meridians are wrong. As if heaven wrote you twice.'],
                ['speaker' => 'Messenger of the Azure Gate', 'text' => 'The outer sect will hear of this. Walk carefully, initiate.'],
                ['speaker' => 'narrator', 'text' => 'The main story begins where the forest ends. The jianghu awaits.'],
            ],
            'actions' => [
                ['key' => 'finish', 'label' => 'Enter the wider realm', 'next' => null, 'complete' => true],
            ],
        ],
    ],
];
