<?php

return [
    'label' => 'Issue',
    'plural' => 'Issues',

    'sections' => [
        'main' => 'Issue details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'description' => 'Description',
        'due_at' => 'Due at',
        'issue_no' => 'Issue no',
        'owner' => 'Owner',
        'raised_at' => 'Raised at',
        'reason' => 'Reason',
        'resolution' => 'Resolution',
        'severity' => 'Severity',
        'status' => 'Status',
        'title' => 'Title',
        'workstream' => 'Workstream',
    ],

    'relation' => [
        'title' => 'Issues',
        'empty' => 'No issues yet.',
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
