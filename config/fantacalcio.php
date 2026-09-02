<?php

return [
    'status_thresholds' => [
        'purchase_delta' => [
            'opportunistic_max' => -0.05,
            'in_piano_max'      =>  0.12,
            'aggressivo_max'    =>  0.28,
            'compresso_max'     =>  0.48,
        ],
        'future_compression' => [
            'opportunistic_max' =>  0.00,
            'in_piano_max'      =>  0.10,
            'aggressivo_max'    =>  0.22,
            'compresso_max'     =>  0.38,
        ],
        'worst_key_slot' => [
            'no_override_min' => 0.75,
            'compresso_min'   => 0.60,
        ],
        'goalkeeper' => [
            'opportunistic_max' => 0.80,
            'in_piano_max'      => 1.20,
        ],
    ],

    'status_weights' => [
        'A' => 3,
        'C' => 2,
        'D' => 1,
    ],

    'key_slots' => [
        'D' => ['D1', 'D2'],
        'C' => ['C1', 'C2', 'C3'],
        'A' => ['A1', 'A2', 'A3'],
    ],

    'role_percentages' => [
        'D' => 0.09,
        'C' => 0.30,
        'A' => 0.56,
    ],

    'goalkeeper_economy' => [
        'target_percentage' => 0.05,
        'target_blocks' => 2,
        'min_block_cost' => 1,
        'cover_cost' => 0,
    ],

    /*
     * Technical goalkeeper slots. Train assignment is intentionally empty:
     * it will be set later when a team's goalkeeper block is selected.
     */
    'rosa_goalkeeper_slots' => [
        [
            'index' => 0,
            'slot_code' => 'P1',
            'train_id' => null,
            'train_role' => null,
            'strategic_weight' => 0.047,
            'min_cost' => 1,
        ],
        [
            'index' => 1,
            'slot_code' => 'P2',
            'train_id' => null,
            'train_role' => null,
            'strategic_weight' => 0.001,
            'min_cost' => 1,
        ],
        [
            'index' => 2,
            'slot_code' => 'P3',
            'train_id' => null,
            'train_role' => null,
            'strategic_weight' => 0.001,
            'min_cost' => 1,
        ],
        [
            'index' => 3,
            'slot_code' => 'P4',
            'train_id' => null,
            'train_role' => null,
            'strategic_weight' => 0.001,
            'min_cost' => 1,
        ],
        [
            'index' => 4,
            'slot_code' => 'P5',
            'train_id' => null,
            'train_role' => null,
            'strategic_weight' => 0.001,
            'min_cost' => 1,
        ],
        [
            'index' => 5,
            'slot_code' => 'P6',
            'train_id' => null,
            'train_role' => null,
            'strategic_weight' => 0.001,
            'min_cost' => 1,
        ],
    ],

    'rosa_dca_slots' => [
        'D' => [
            ['index' => 6, 'slot_code' => 'D1', 'label' => 'Difensore principale', 'role' => 'D', 'strategic_weight' => 4, 'max_extra_percentage' => 0.30, 'min_cost' => 1, 'level' => 'Top', 'hint' => 'Difensore principale.'],
            ['index' => 7, 'slot_code' => 'D2', 'label' => 'Difensore medio-alto', 'role' => 'D', 'strategic_weight' => 3, 'max_extra_percentage' => 0.25, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Difensore di fascia medio-alta.'],
            ['index' => 8, 'slot_code' => 'D3', 'label' => 'Difensore medio-alto', 'role' => 'D', 'strategic_weight' => 1, 'max_extra_percentage' => 0.20, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Difensore di fascia medio-alta.'],
            ['index' => 9, 'slot_code' => 'D4', 'label' => 'Difensore medio-alto', 'role' => 'D', 'strategic_weight' => 1, 'max_extra_percentage' => 0.20, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Difensore di fascia medio-alta.'],
            ['index' => 10, 'slot_code' => 'D5', 'label' => 'Difensore di rotazione', 'role' => 'D', 'strategic_weight' => 1, 'max_extra_percentage' => 0.15, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Difensore di rotazione.'],
            ['index' => 11, 'slot_code' => 'D6', 'label' => 'Difensore di rotazione', 'role' => 'D', 'strategic_weight' => 1, 'max_extra_percentage' => 0.15, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Difensore di rotazione.'],
            ['index' => 12, 'slot_code' => 'D7', 'label' => 'Difensore low cost', 'role' => 'D', 'strategic_weight' => 1, 'max_extra_percentage' => 0.05, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Difensore low cost.'],
            ['index' => 13, 'slot_code' => 'D8', 'label' => 'Difensore low cost', 'role' => 'D', 'strategic_weight' => 1, 'max_extra_percentage' => 0.05, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Difensore low cost.'],
        ],
        'C' => [
            ['index' => 14, 'slot_code' => 'C1', 'label' => 'Centrocampista top', 'role' => 'C', 'strategic_weight' => 37, 'max_extra_percentage' => 0.20, 'min_cost' => 1, 'level' => 'Top', 'hint' => 'Centrocampista top.'],
            ['index' => 15, 'slot_code' => 'C2', 'label' => 'Centrocampista semitop', 'role' => 'C', 'strategic_weight' => 24, 'max_extra_percentage' => 0.25, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Centrocampista semitop.'],
            ['index' => 16, 'slot_code' => 'C3', 'label' => 'Centrocampista semitop', 'role' => 'C', 'strategic_weight' => 14, 'max_extra_percentage' => 0.20, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Centrocampista semitop.'],
            ['index' => 17, 'slot_code' => 'C4', 'label' => 'Centrocampista titolare', 'role' => 'C', 'strategic_weight' => 9, 'max_extra_percentage' => 0.15, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Centrocampista titolare.'],
            ['index' => 18, 'slot_code' => 'C5', 'label' => 'Centrocampista titolare', 'role' => 'C', 'strategic_weight' => 6, 'max_extra_percentage' => 0.10, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Centrocampista titolare.'],
            ['index' => 19, 'slot_code' => 'C6', 'label' => 'Centrocampista di rotazione', 'role' => 'C', 'strategic_weight' => 4, 'max_extra_percentage' => 0.05, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Centrocampista di rotazione.'],
            ['index' => 20, 'slot_code' => 'C7', 'label' => 'Centrocampista di rotazione', 'role' => 'C', 'strategic_weight' => 3, 'max_extra_percentage' => 0.05, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Centrocampista di rotazione.'],
            ['index' => 21, 'slot_code' => 'C8', 'label' => 'Centrocampista di rotazione', 'role' => 'C', 'strategic_weight' => 2, 'max_extra_percentage' => 0.05, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Centrocampista di rotazione.'],
        ],
        'A' => [
            ['index' => 22, 'slot_code' => 'A1', 'label' => 'Prima punta principale', 'role' => 'A', 'strategic_weight' => 12, 'max_extra_percentage' => 0.25, 'min_cost' => 1, 'level' => 'Top', 'hint' => 'Prima punta principale.'],
            ['index' => 23, 'slot_code' => 'A2', 'label' => 'Secondo attaccante', 'role' => 'A', 'strategic_weight' => 9, 'max_extra_percentage' => 0.20, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Secondo attaccante.'],
            ['index' => 24, 'slot_code' => 'A3', 'label' => 'Terzo attaccante', 'role' => 'A', 'strategic_weight' => 4, 'max_extra_percentage' => 0.15, 'min_cost' => 1, 'level' => 'Medio', 'hint' => 'Terzo attaccante.'],
            ['index' => 25, 'slot_code' => 'A4', 'label' => 'Attaccante di rotazione', 'role' => 'A', 'strategic_weight' => 2, 'max_extra_percentage' => 0.10, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Attaccante di rotazione.'],
            ['index' => 26, 'slot_code' => 'A5', 'label' => 'Attaccante low cost', 'role' => 'A', 'strategic_weight' => 1, 'max_extra_percentage' => 0.05, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Attaccante low cost o scommessa.'],
            ['index' => 27, 'slot_code' => 'A6', 'label' => 'Attaccante low cost', 'role' => 'A', 'strategic_weight' => 1, 'max_extra_percentage' => 0.05, 'min_cost' => 1, 'level' => 'Low', 'hint' => 'Attaccante low cost o scommessa.'],
        ],
    ],
];
