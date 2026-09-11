<?php

return [
    'label' => 'Operation handoff',
    'plural' => 'Operation handoffs',

    'sections' => [
        'main' => 'Handoff details',
    ],

    'fields' => [
        'accepted_at' => 'Accepted at',
        'accepted_version' => 'Accepted version',
        'acceptor' => 'Accepted by',
        'business_case' => 'Business case',
        'created_at' => 'Created at',
        'prepared_by' => 'Prepared by',
        'preparer' => 'Prepared by',
        'reason' => 'Reason',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Operation Handoff',
        'empty' => 'No handoff yet.',
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
        'duplicate' => 'This business case already has a handoff.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
