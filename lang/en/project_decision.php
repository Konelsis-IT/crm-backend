<?php

return [
    'label' => 'Project decision',
    'plural' => 'Project decisions',

    'sections' => [
        'main' => 'Decision details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'decided_at' => 'Decided at',
        'decider' => 'Decided by',
        'decision_no' => 'Decision no',
        'decision_scope' => 'Scope',
        'description' => 'Description',
        'document_revision' => 'Document revision',
        'reason' => 'Reason',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Decisions',
        'empty' => 'No decisions yet.',
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
