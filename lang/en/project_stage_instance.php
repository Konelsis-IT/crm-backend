<?php

return [
    'label' => 'Project gate',
    'plural' => 'Project gates',

    'sections' => [
        'main' => 'Gate details',
    ],

    'fields' => [
        'comment' => 'Comment',
        'condition_due_on' => 'Condition due',
        'conditions' => 'Conditions',
        'created_at' => 'Created at',
        'decision' => 'Decision',
        'entered_at' => 'Entered at',
        'name' => 'Name',
        'owner' => 'Owner',
        'passed_at' => 'Passed at',
        'project' => 'Project',
        'reason' => 'Reason',
        'remediation_due_on' => 'Remediation due',
        'requirement' => 'Requirement',
        'risk_owner' => 'Risk owner',
        'stage_code' => 'Gate code',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Stage-Gate',
        'empty' => 'No gates yet.',
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
