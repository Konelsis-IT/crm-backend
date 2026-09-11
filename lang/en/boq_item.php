<?php

return [
    'label' => 'BOQ item',
    'plural' => 'BOQ items',

    'sections' => [
        'main' => 'Item details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'description' => 'Description',
        'item_code' => 'Item code',
        'quantity' => 'Quantity',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
        'unit_price' => 'Unit price',
        'uom' => 'Unit',
    ],

    'relation' => [
        'title' => 'BOQ',
        'empty' => 'No items yet.',
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
