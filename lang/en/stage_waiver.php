<?php

return [
    'label' => 'Stage waiver',
    'plural' => 'Stage waivers',

    'sections' => [
        'main' => 'Waiver details',
    ],

    'fields' => [
        'approver' => 'Approved by',
        'created_at' => 'Created at',
        'granted_at' => 'Granted at',
        'reason' => 'Reason',
        'remediation_due_on' => 'Remediation due',
        'requirement' => 'Requirement',
        'risk_owner' => 'Risk owner',
    ],

    'relation' => [
        'title' => 'Waivers',
        'empty' => 'No waivers yet.',
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
