<?php

return [
    'label' => 'Progress snapshot',
    'plural' => 'Progress snapshots',

    'sections' => [
        'main' => 'Progress details',
    ],

    'fields' => [
        'cost_progress_pct' => 'Cost progress (%)',
        'created_at' => 'Created at',
        'physical_progress_pct' => 'Physical progress (%)',
        'planned_progress_pct' => 'Planned progress (%)',
        'reason' => 'Reason',
        'reporter' => 'Reported by',
        'snapshot_at' => 'Snapshot at',
        'source' => 'Source',
    ],

    'relation' => [
        'title' => 'Progress',
        'empty' => 'No progress snapshots yet.',
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
