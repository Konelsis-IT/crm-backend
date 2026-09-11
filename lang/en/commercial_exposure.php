<?php

return [
    'label' => 'Commercial exposure',
    'plural' => 'Commercial exposures',

    'sections' => [
        'main' => 'Exposure details',
    ],

    'fields' => [
        'cbs_node' => 'CBS node',
        'created_at' => 'Created at',
        'currency' => 'Currency',
        'description' => 'Description',
        'exposure_amount' => 'Amount',
        'exposure_kind' => 'Kind',
        'exposure_no' => 'Exposure no',
        'owner' => 'Owner',
        'probability' => 'Probability (0–1)',
        'reason' => 'Reason',
        'source_change' => 'Source change',
        'source_delay_event' => 'Source delay event',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Commercial Exposure',
        'empty' => 'No exposures yet.',
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
