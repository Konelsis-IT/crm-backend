<?php

return [
    'label' => 'Party role',
    'plural' => 'Party roles',

    'sections' => [
        'main' => 'Role details',
    ],

    'fields' => [
        'approved_by' => 'Approved by',
        'approver' => 'Approved by',
        'created_at' => 'Created at',
        'reason' => 'Reason',
        'role_code' => 'Role',
        'status' => 'Status',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
    ],

    'relation' => [
        'title' => 'Roles',
        'empty' => 'No roles yet.',
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
