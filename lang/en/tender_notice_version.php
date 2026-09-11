<?php

return [
    'label' => 'Notice version',
    'plural' => 'Notice versions',

    'sections' => [
        'main' => 'Version details',
    ],

    'fields' => [
        'capturer' => 'Captured by',
        'created_at' => 'Created at',
        'notice' => 'Notice',
        'published_on' => 'Published on',
        'reason' => 'Reason',
        'source_document_revision' => 'Source document',
        'status' => 'Status',
        'summary' => 'Summary',
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
