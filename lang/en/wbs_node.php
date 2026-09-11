<?php

return [
    'label' => 'WBS node',
    'plural' => 'WBS',

    'sections' => [
        'main' => 'WBS details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'level' => 'Level',
        'name' => 'Name',
        'parent' => 'Parent',
        'project' => 'Project',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
        'status' => 'Status',
        'wbs_code' => 'WBS code',
    ],

    'relation' => [
        'title' => 'WBS',
        'empty' => 'No WBS nodes yet.',
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
