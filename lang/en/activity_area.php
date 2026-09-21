<?php

return [
    'label' => 'Activity area',
    'plural' => 'Activity areas',

    'sections' => [
        'main' => 'Activity area details',
    ],

    'fields' => [
        'parent' => 'Main activity area',
        'code' => 'Code',
        'name_tr' => 'Name (TR)',
        'name_en' => 'Name (EN)',
        'sort_order' => 'Order',
        'status' => 'Status',
    ],

    'help' => [
        'parent' => 'Leave empty for a main activity area; choose one to make this its sub-activity area.',
    ],

    'values' => [
        'root' => 'Main activity area',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
    ],
];
