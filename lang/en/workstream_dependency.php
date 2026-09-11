<?php

return [
    'label' => 'Workstream dependency',
    'plural' => 'Workstream dependencies',

    'sections' => [
        'main' => 'Dependency details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'dependency_type' => 'Dependency type',
        'is_hard' => 'Hard dependency',
        'lag_days' => 'Lag (days)',
        'predecessor' => 'Predecessor',
        'predecessor_workstream' => 'Predecessor workstream',
        'reason' => 'Reason',
        'status' => 'Status',
        'waiver' => 'Waived by',
        'waiver_reason' => 'Waiver reason',
    ],

    'relation' => [
        'title' => 'Dependencies (predecessors)',
        'empty' => 'No dependencies yet.',
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
