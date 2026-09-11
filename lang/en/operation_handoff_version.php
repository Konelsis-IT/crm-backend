<?php

return [
    'label' => 'Handoff version',
    'plural' => 'Handoff versions',

    'sections' => [
        'main' => 'Version details',
    ],

    'fields' => [
        'business_case' => 'Business case',
        'comment' => 'Comment',
        'contract_version' => 'Contract version',
        'created_at' => 'Created at',
        'decision' => 'Decision',
        'decision_reason' => 'Decision reason',
        'manifest_document_revision' => 'Manifest document revision',
        'planned_finish_on' => 'Planned finish',
        'planned_start_on' => 'Planned start',
        'project_manager' => 'Project manager',
        'project_name' => 'Project name',
        'proposal_version' => 'Proposal version',
        'reason' => 'Reason',
        'site_location' => 'Site location',
        'status' => 'Status',
        'submitted_at' => 'Submitted at',
        'submitter' => 'Submitted by',
        'version_no' => 'Version no',
    ],

    'relation' => [
        'title' => 'Versions',
        'empty' => 'No versions yet.',
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
        'reviewed' => 'Review decision recorded.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],

    'help' => [
        'proposal_version' => 'Leave blank to use the selected proposal\'s approved version.',
        'contract_version' => 'Leave blank to use the executed contract version (D-10).',
    ],
];
