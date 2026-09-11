<?php

return [
    'label' => 'Estimate',
    'plural' => 'Estimates',

    'sections' => [
        'main' => 'Estimate details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'currency' => 'Currency',
        'exchange_rate_snapshot' => 'Exchange rate snapshot',
        'notes' => 'Notes',
        'preparer' => 'Prepared by',
        'proposal' => 'Proposal',
        'proposal_version' => 'Proposal version',
        'reason' => 'Reason',
        'status' => 'Status',
        'target_margin_pct' => 'Target margin (%)',
        'total_cost' => 'Total cost',
        'total_price' => 'Total price',
        'version_no' => 'Version no',
    ],

    'relation' => [
        'title' => 'Estimates',
        'empty' => 'No estimates yet.',
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
