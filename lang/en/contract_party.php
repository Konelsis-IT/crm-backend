<?php

return [
    'label' => 'Contract party',
    'plural' => 'Contract parties',

    'sections' => [
        'main' => 'Party details',
    ],

    'fields' => [
        'contract_role' => 'Contract role',
        'created_at' => 'Created at',
        'party' => 'Party',
        'reason' => 'Reason',
        'signatory_name' => 'Signatory',
    ],

    'relation' => [
        'title' => 'Parties',
        'empty' => 'No parties yet.',
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
