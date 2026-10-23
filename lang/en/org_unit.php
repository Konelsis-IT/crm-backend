<?php

return [
    'label' => 'Org Unit',
    'plural' => 'Org Units',

    'sections' => [
        'main' => 'Unit details',
        'projects' => 'In contracts',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Unit name',
        'unit_type' => 'Type',
        'parent' => 'Parent unit',
        'manager' => 'Manager',
        'cost_center_code' => 'Cost center code',
        'personnel_count' => 'Personnel count',
        'position_count' => 'Position count',
        'status' => 'Status',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'in_projects' => 'Works in contracts',
        'project_sort' => 'Order in contracts',
        'in_projects_short' => 'In contracts',
        'in_projects_value' => 'Yes · position :sort',
        'in_projects_no' => 'No',
    ],

    'help' => [
        'parent' => 'Changing this closes the old parent as history and opens the new one from today.',
        'in_projects' => 'When on, this department is listed on the Departments tab of every new contract with its owner, progress and dates.',
        'project_sort' => 'Position on the Departments tab of a contract (lower first).',
    ],

    'relation' => [
        'personnel' => 'Personnel',
        'personnel_empty' => 'No personnel in this unit.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'self_parent' => 'A unit cannot be its own parent.',
    ],
];
