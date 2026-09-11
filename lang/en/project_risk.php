<?php

return [
    'label' => 'Risk',
    'plural' => 'Risks',

    'sections' => [
        'main' => 'Risk details',
    ],

    'fields' => [
        'category' => 'Category',
        'created_at' => 'Created at',
        'description' => 'Description',
        'impact' => 'Impact (1–5)',
        'mitigation_plan' => 'Mitigation plan',
        'owner' => 'Owner',
        'probability' => 'Probability (0–1)',
        'reason' => 'Reason',
        'response_strategy' => 'Response strategy',
        'review_due_on' => 'Review due',
        'risk_no' => 'Risk no',
        'score' => 'Score',
        'status' => 'Status',
        'title' => 'Title',
        'workstream' => 'Workstream',
    ],

    'relation' => [
        'title' => 'Risks',
        'empty' => 'No risks yet.',
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
