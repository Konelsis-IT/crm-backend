<?php

return [
    'label' => 'Compliance item',
    'plural' => 'Compliance items',

    'sections' => [
        'main' => 'Item details',
    ],

    'fields' => [
        'compliance_state' => 'Compliance',
        'created_at' => 'Created at',
        'description' => 'Description',
        'note' => 'Note',
        'reason' => 'Reason',
        'requirement_code' => 'Requirement code',
        'sort_order' => 'Sort order',
        'tender_requirement' => 'Tender requirement',
    ],

    'relation' => [
        'title' => 'Spec Compliance',
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
