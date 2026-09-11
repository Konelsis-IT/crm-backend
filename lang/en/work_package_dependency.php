<?php

return [
    'label' => 'Work package dependency',
    'plural' => 'Work package dependencies',

    'sections' => [
        'main' => 'Dependency details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'dependency_type' => 'Dependency type',
        'is_hard' => 'Hard dependency',
        'name' => 'Name',
        'predecessor' => 'Predecessor',
        'predecessor_package' => 'Predecessor package',
        'reason' => 'Reason',
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
