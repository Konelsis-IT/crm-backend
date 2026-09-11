<?php

return [
    'label' => 'Brand item',
    'plural' => 'Brand list',

    'sections' => [
        'main' => 'Brand details',
    ],

    'fields' => [
        'alternative_brand' => 'Alternative brand',
        'approval_state' => 'Approval state',
        'created_at' => 'Created at',
        'item_code' => 'Item code',
        'item_description' => 'Item description',
        'origin_country' => 'Origin country',
        'proposed_brand' => 'Proposed brand',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
    ],

    'relation' => [
        'title' => 'Brand List',
        'empty' => 'No brand items yet.',
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
