<?php

return [
    'label' => 'Competency',
    'plural' => 'Competencies',

    'sections' => [
        'main' => 'Competency details',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Competency name',
        'category' => 'Category',
        'description' => 'Description',
        'personnel_count' => 'Personnel count',
        'status' => 'Status',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
    ],
];
