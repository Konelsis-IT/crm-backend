<?php

return [
    'label' => 'Deadline',
    'plural' => 'Deadlines',

    'sections' => [
        'main' => 'Deadline details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'deadline_type' => 'Deadline type',
        'due_at_utc' => 'Due at (UTC)',
        'local_due_date' => 'Local date',
        'local_due_time' => 'Local time',
        'reason' => 'Reason',
        'timezone' => 'Time zone',
    ],

    'relation' => [
        'title' => 'Deadlines',
        'empty' => 'No deadlines yet.',
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
