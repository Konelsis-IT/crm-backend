<?php

return [
    'label' => 'Proposal document',
    'plural' => 'Proposal documents',

    'sections' => [
        'main' => 'Document details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'document_revision' => 'Document revision',
        'document_role' => 'Document role',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Documents',
        'empty' => 'No documents yet.',
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
