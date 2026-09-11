<?php

return [
    'label' => 'Change request',
    'plural' => 'Change requests',

    'sections' => [
        'main' => 'Change details',
    ],

    'fields' => [
        'affects_baseline' => 'Affects baseline',
        'change_no' => 'Change no',
        'change_type' => 'Change type',
        'created_at' => 'Created at',
        'currency' => 'Currency',
        'description' => 'Description',
        'impact_cost' => 'Cost impact',
        'impact_days' => 'Schedule impact (days)',
        'reason' => 'Reason',
        'status' => 'Status',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Changes',
        'empty' => 'No changes yet.',
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
