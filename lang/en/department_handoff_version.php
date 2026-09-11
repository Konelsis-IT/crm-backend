<?php

return [
    'label' => 'Handoff version',
    'plural' => 'Handoff versions',

    'sections' => [
        'main' => 'Version details',
    ],

    'fields' => [
        'comment' => 'Comment',
        'created_at' => 'Created at',
        'decision' => 'Decision',
        'project' => 'Project',
        'reason' => 'Reason',
        'snapshot_hash' => 'Snapshot hash',
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
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
