<?php

return [
    'label' => 'Legal Hold',
    'plural' => 'Legal Holds',

    'sections' => [
        'main' => 'Hold details',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'reason' => 'Reason',
        'requester' => 'Requested by',
        'approver' => 'Approved by',
        'status' => 'Status',
        'starts_at' => 'Starts at',
        'released_at' => 'Released at',
        'release_reason' => 'Release reason',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
    ],

    'relation' => [
        'documents' => [
            'title' => 'Documents in Scope',
            'empty' => 'No documents added yet.',
        ],
    ],
];
