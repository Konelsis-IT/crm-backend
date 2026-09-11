<?php

return [
    'label' => 'Estimate line',
    'plural' => 'Estimate lines',

    'sections' => [
        'main' => 'Line details',
    ],

    'fields' => [
        'cost_type' => 'Cost type',
        'created_at' => 'Created at',
        'description' => 'Description',
        'line_code' => 'Line code',
        'line_total_cost' => 'Line cost',
        'parent_line' => 'Parent line',
        'quantity' => 'Quantity',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
        'unit_cost' => 'Unit cost',
        'unit_price' => 'Unit price',
        'uom' => 'Unit',
        'wbs_hint' => 'WBS hint',
    ],

    'relation' => [
        'title' => 'Lines',
        'empty' => 'No lines yet.',
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
