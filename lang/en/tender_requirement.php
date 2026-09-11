<?php

return [
    'label' => 'Tender requirement',
    'plural' => 'Tender requirements',

    'sections' => [
        'main' => 'Requirement details',
    ],

    'fields' => [
        'compliance_state' => 'Compliance',
        'created_at' => 'Created at',
        'description' => 'Description',
        'evaluated_by' => 'Evaluated by',
        'is_mandatory' => 'Mandatory',
        'reason' => 'Reason',
        'requirement_code' => 'Requirement code',
        'requirement_type' => 'Requirement type',
        'sort_order' => 'Sort order',
    ],

    'relation' => [
        'title' => 'Requirements',
        'empty' => 'No requirements yet.',
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
