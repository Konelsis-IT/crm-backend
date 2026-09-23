<?php

return [
    'label' => 'Position',
    'plural' => 'Positions',

    'sections' => [
        'main' => 'Position details',
    ],

    'fields' => [
        'code' => 'Code',
        'title' => 'Position title',
        'org_unit' => 'Org unit',
        'grade' => 'Grade',
        'managerial_level' => 'Managerial level',
        'headcount' => 'Headcount',
        'assignee_count' => 'Assigned personnel',
        'status' => 'Status',
        'is_primary' => 'Primary position',
        'allocation_pct' => 'Allocation (%)',
        'valid_from' => 'From',
        'valid_until' => 'Until',
    ],

    'help' => [
        'managerial_level' => '0 = not managerial; maximum 5.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate_primary' => 'This personnel already has a primary position.',
    ],

    'relation' => [
        'title' => 'Positions',
        'empty' => 'No position assigned.',
        'personnel' => 'Assigned personnel',
        'personnel_empty' => 'Nobody is assigned to this position.',
    ],
];
