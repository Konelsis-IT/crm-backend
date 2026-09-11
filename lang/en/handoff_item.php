<?php

return [
    'label' => 'Handoff item',
    'plural' => 'Handoff items',

    'sections' => [
        'main' => 'Item details',
    ],

    'fields' => [
        'completion_state' => 'Completion',
        'created_at' => 'Created at',
        'description' => 'Description',
        'document_revision' => 'Document revision',
        'item_code' => 'Item code',
        'item_type' => 'Item type',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
        'waiver' => 'Waived by',
    ],

    'relation' => [
        'title' => 'Checklist',
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
