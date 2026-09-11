<?php

return [
    'label' => 'Contract',
    'plural' => 'Contracts',

    'sections' => [
        'main' => 'Contract details',
    ],

    'fields' => [
        'business_case' => 'Business case',
        'contract_no' => 'Contract no',
        'contract_type' => 'Contract type',
        'created_at' => 'Created at',
        'current_version' => 'Current version',
        'customer_party' => 'Customer',
        'effective_from' => 'Effective from',
        'reason' => 'Reason',
        'signed_on' => 'Signed on',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Contracts',
        'empty' => 'No contracts yet.',
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
