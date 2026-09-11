<?php

return [
    'label' => 'Deviation',
    'plural' => 'Deviations',

    'sections' => [
        'main' => 'Deviation details',
    ],

    'fields' => [
        'compliance_item' => 'Compliance item',
        'created_at' => 'Created at',
        'description' => 'Description',
        'deviation_type' => 'Deviation type',
        'justification' => 'Justification',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Deviations',
        'empty' => 'No deviations yet.',
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
