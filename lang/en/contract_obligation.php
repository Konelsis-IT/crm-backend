<?php

return [
    'label' => 'Obligation',
    'plural' => 'Obligations',

    'sections' => [
        'main' => 'Obligation details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'description' => 'Description',
        'due_on' => 'Due on',
        'obligation_code' => 'Obligation code',
        'obligation_type' => 'Obligation type',
        'reason' => 'Reason',
        'responsible_party' => 'Responsible party',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Obligations',
        'empty' => 'No obligations yet.',
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
