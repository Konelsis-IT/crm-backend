<?php

return [
    'label' => 'CBS node',
    'plural' => 'CBS',

    'sections' => [
        'main' => 'CBS details',
    ],

    'fields' => [
        'cost_category' => 'Cost category',
        'cost_code' => 'Cost code',
        'created_at' => 'Created at',
        'name' => 'Name',
        'parent' => 'Parent',
        'reason' => 'Reason',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'CBS',
        'empty' => 'No CBS nodes yet.',
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
