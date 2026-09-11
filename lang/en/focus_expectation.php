<?php

return [
    'label' => 'Focus expectation',
    'plural' => 'Focus expectations',

    'sections' => [
        'main' => 'Expectation definition',
    ],

    'fields' => [
        'group' => 'Step (operation group)',
        'code' => 'Code',
        'name_tr' => 'Name (TR)',
        'name_en' => 'Name (EN)',
        'kind' => 'Counting rule',
        'min_count' => 'Minimum',
        'is_mandatory' => 'Mandatory',
        'help_tr' => 'Help text (TR)',
        'help_en' => 'Help text (EN)',
        'sort_order' => 'Order',
        'status' => 'Status',
        'created_at' => 'Created at',
        'reason' => 'Reason',
    ],

    'help' => [
        'kind' => 'Defines which project data is counted; the expectation is met when the count reaches the minimum.',
        'is_mandatory' => 'A project cannot move to the next step (without a reason) until mandatory expectations are met.',
    ],

    'relation' => [
        'title' => 'Expectations',
        'empty' => 'No expectations defined for this step.',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'set_status' => 'Set status to \":status\"',
    ],

    'messages' => [
        'done' => 'Done.',
        'status_changed' => 'Status updated.',
    ],

    'validation' => [
        'code_taken' => 'This code is already used in the same step.',
        'duplicate' => 'This record already exists.',
    ],
];
