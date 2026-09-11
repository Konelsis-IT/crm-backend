<?php

return [
    'label' => 'Workstream',
    'plural' => 'Workstreams',

    'sections' => [
        'main' => 'Workstream details',
    ],

    'fields' => [
        'actual_finish_on' => 'Actual finish',
        'actual_start_on' => 'Actual start',
        'block_reason' => 'Block reason',
        'created_at' => 'Created at',
        'group' => 'Group',
        'owner' => 'Owner',
        'planned_finish_on' => 'Planned finish',
        'planned_start_on' => 'Planned start',
        'progress_pct' => 'Progress (%)',
        'project' => 'Project',
        'reason' => 'Reason',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Workstreams',
        'empty' => 'No workstreams yet.',
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
