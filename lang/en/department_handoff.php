<?php

return [
    'label' => 'Department handoff',
    'plural' => 'Department handoffs',

    'sections' => [
        'main' => 'Handoff details',
    ],

    'fields' => [
        'accepted_at' => 'Accepted at',
        'created_at' => 'Created at',
        'project' => 'Project',
        'reason' => 'Reason',
        'sla_due_at' => 'SLA due',
        'source_workstream' => 'Source workstream',
        'status' => 'Status',
        'target_workstream' => 'Target workstream',
        'trigger_stage_instance' => 'Trigger gate',
    ],

    'relation' => [
        'title' => 'Department Handoffs',
        'empty' => 'No handoffs yet.',
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
