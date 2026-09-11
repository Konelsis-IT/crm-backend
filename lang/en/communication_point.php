<?php

return [
    'label' => 'Communication point',
    'plural' => 'Communication points',

    'sections' => [
        'main' => 'Contact details',
    ],

    'fields' => [
        'channel_type' => 'Channel',
        'created_at' => 'Created at',
        'is_primary' => 'Primary',
        'purpose' => 'Purpose',
        'reason' => 'Reason',
        'status' => 'Status',
        'value' => 'Value',
    ],

    'relation' => [
        'title' => 'Communication Points',
        'empty' => 'No communication points yet.',
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
