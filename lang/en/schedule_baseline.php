<?php

return [
    'label' => 'Schedule baseline',
    'plural' => 'Schedule baselines',

    'sections' => [
        'main' => 'Baseline details',
    ],

    'fields' => [
        'approved_at' => 'Approved at',
        'baseline_document_revision' => 'Baseline document revision',
        'created_at' => 'Created at',
        'name' => 'Name',
        'planned_finish_on' => 'Planned finish',
        'planned_start_on' => 'Planned start',
        'reason' => 'Reason',
        'source' => 'Source',
        'status' => 'Status',
        'version_no' => 'Version no',
    ],

    'relation' => [
        'title' => 'Schedule Baselines',
        'empty' => 'No baselines yet.',
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
