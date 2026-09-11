<?php

return [
    'label' => 'Delay event',
    'plural' => 'Delay events',

    'sections' => [
        'main' => 'Delay details',
    ],

    'fields' => [
        'cause_category' => 'Cause',
        'created_at' => 'Created at',
        'delay_days' => 'Delay (days)',
        'description' => 'Description',
        'detected_at' => 'Detected at',
        'evidence_document_revision' => 'Evidence document revision',
        'is_excusable' => 'Excusable',
        'project' => 'Project',
        'reason' => 'Reason',
        'status' => 'Status',
        'workstream' => 'Workstream',
    ],

    'relation' => [
        'title' => 'Delays',
        'empty' => 'No delays yet.',
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
