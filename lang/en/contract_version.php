<?php

return [
    'label' => 'Contract version',
    'plural' => 'Contract versions',

    'sections' => [
        'main' => 'Version details',
    ],

    'fields' => [
        'approved_at' => 'Approved at',
        'contract' => 'Contract',
        'contract_value' => 'Contract value',
        'created_at' => 'Created at',
        'currency' => 'Currency',
        'effective_from' => 'Effective from',
        'effective_until' => 'Effective until',
        'executed_at' => 'Executed at',
        'locale' => 'Language',
        'reason' => 'Reason',
        'status' => 'Status',
        'summary' => 'Summary',
        'version_no' => 'Version no',
    ],

    'relation' => [
        'title' => 'Versions',
        'empty' => 'No versions yet.',
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
