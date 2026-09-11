<?php

return [
    'label' => 'Milestone',
    'plural' => 'Milestones',

    'sections' => [
        'main' => 'Milestone details',
    ],

    'fields' => [
        'actual_at' => 'Actual at',
        'baseline_at' => 'Baseline at',
        'contract_milestone' => 'Contract milestone',
        'created_at' => 'Created at',
        'forecast_at' => 'Forecast at',
        'milestone_code' => 'Milestone code',
        'milestone_kind' => 'Kind',
        'name' => 'Name',
        'planned_at' => 'Planned at',
        'reason' => 'Reason',
        'status' => 'Status',
        'wbs_node' => 'WBS node',
    ],

    'relation' => [
        'title' => 'Milestones',
        'empty' => 'No milestones yet.',
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
