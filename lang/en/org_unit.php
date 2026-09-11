<?php

return [
    'label' => 'Org Unit',
    'plural' => 'Org Units',

    'sections' => [
        'main' => 'Unit details',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Unit name',
        'unit_type' => 'Type',
        'parent' => 'Parent unit',
        'manager' => 'Manager',
        'cost_center_code' => 'Cost center code',
        'personnel_count' => 'Personnel count',
        'status' => 'Status',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
    ],

    'help' => [
        'parent' => 'Changing this closes the old parent as history and opens the new one from today.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'self_parent' => 'A unit cannot be its own parent.',
    ],
];
