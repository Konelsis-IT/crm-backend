<?php

return [
    'label' => 'Stage dependency',
    'plural' => 'Stage dependencies',

    'sections' => [
        'main' => 'Dependency details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'is_hard' => 'Hard dependency',
        'name' => 'Name',
        'reason' => 'Reason',
        'successor' => 'Successor',
        'successor_node' => 'Successor node',
    ],

    'relation' => [
        'title' => 'Dependencies',
        'empty' => 'No dependencies yet.',
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
