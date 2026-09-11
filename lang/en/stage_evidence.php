<?php

return [
    'label' => 'Stage evidence',
    'plural' => 'Stage evidence',

    'sections' => [
        'main' => 'Evidence details',
    ],

    'fields' => [
        'accepted_at' => 'Accepted at',
        'acceptor' => 'Accepted by',
        'created_at' => 'Created at',
        'document_revision' => 'Document revision',
        'evidence_hash' => 'Evidence hash',
        'reason' => 'Reason',
        'requirement' => 'Requirement',
        'submitted_at' => 'Submitted at',
        'submitter' => 'Submitted by',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Evidence',
        'empty' => 'No evidence yet.',
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
