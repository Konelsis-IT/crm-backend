<?php

return [
    'label' => 'Pricing scenario',
    'plural' => 'Pricing scenarios',

    'sections' => [
        'main' => 'Scenario details',
    ],

    'fields' => [
        'adjustment_pct' => 'Adjustment (%)',
        'created_at' => 'Created at',
        'is_selected' => 'Selected',
        'name' => 'Name',
        'reason' => 'Reason',
        'scenario_code' => 'Scenario code',
        'target_margin_pct' => 'Target margin (%)',
        'total_price' => 'Total price',
    ],

    'relation' => [
        'title' => 'Pricing Scenarios',
        'empty' => 'No scenarios yet.',
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
