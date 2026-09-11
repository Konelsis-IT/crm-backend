<?php

return [
    'label' => 'Contract milestone',
    'plural' => 'Contract milestones',

    'sections' => [
        'main' => 'Milestone details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'description' => 'Description',
        'milestone_code' => 'Milestone code',
        'name' => 'Name',
        'payment_amount' => 'Payment amount',
        'payment_pct' => 'Payment (%)',
        'planned_on' => 'Planned on',
        'reason' => 'Reason',
    ],

    'relation' => [
        'title' => 'Milestones',
        'empty' => 'No milestones yet.',
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
