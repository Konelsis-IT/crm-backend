<?php

return [
    'label' => 'Focus history',
    'plural' => 'Focus history',

    'sections' => [
        'main' => 'Focus details',
    ],

    'fields' => [
        'changer' => 'Changed by',
        'created_at' => 'Created at',
        'direction' => 'Direction',
        'ended_at' => 'Ended at',
        'reason' => 'Reason',
        'started_at' => 'Started at',
        'workstream' => 'Workstream',
    ],

    'relation' => [
        'title' => 'Focus History',
        'empty' => 'No focus history yet.',
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
