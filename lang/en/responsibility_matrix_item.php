<?php

return [
    'label' => 'Responsibility item',
    'plural' => 'Responsibility matrix',

    'sections' => [
        'main' => 'Responsibility details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'note' => 'Note',
        'reason' => 'Reason',
        'responsible_party_role' => 'Responsible party',
        'scope_code' => 'Scope code',
        'scope_description' => 'Scope',
        'sort_order' => 'Sort order',
    ],

    'relation' => [
        'title' => 'Responsibility Matrix',
        'empty' => 'No items yet.',
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
