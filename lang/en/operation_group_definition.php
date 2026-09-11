<?php

return [
    'label' => 'Operation group',
    'plural' => 'Operation groups',

    'sections' => [
        'main' => 'Group details',
    ],

    'fields' => [
        'code' => 'Code',
        'created_at' => 'Created at',
        'default_sort_order' => 'Default order',
        'name_en' => 'Name (EN)',
        'name_tr' => 'Name (TR)',
        'reason' => 'Reason',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Groups',
        'empty' => 'No groups yet.',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'set_status' => 'Set status to \":status\"',
        'select' => 'Mark as selected',
        'submit' => 'Submit for review',
        'review' => 'Record review decision',
        'publish' => 'Publish',
        'approve' => 'Approve',
        'change_focus' => 'Change focus',
        'waive' => 'Grant waiver',
        'add_evidence' => 'Add evidence',
        'accept' => 'Accept',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
