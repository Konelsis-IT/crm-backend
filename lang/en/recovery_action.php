<?php

return [
    'label' => 'Recovery action',
    'plural' => 'Recovery actions',

    'sections' => [
        'main' => 'Action details',
    ],

    'fields' => [
        'completed_at' => 'Completed at',
        'created_at' => 'Created at',
        'description' => 'Description',
        'due_at' => 'Due at',
        'expected_recovery_days' => 'Expected recovery (days)',
        'owner' => 'Owner',
        'reason' => 'Reason',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Recovery Actions',
        'empty' => 'No actions yet.',
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
