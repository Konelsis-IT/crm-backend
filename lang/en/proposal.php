<?php

return [
    'label' => 'Proposal',
    'plural' => 'Proposals',

    'sections' => [
        'main' => 'Proposal details',
    ],

    'fields' => [
        'business_case' => 'Business case',
        'created_at' => 'Created at',
        'current_version' => 'Current version',
        'is_selected' => 'Selected',
        'offer_status' => 'Offer status',
        'owner' => 'Owner',
        'proposal_no' => 'Proposal no',
        'reason' => 'Reason',
        'status' => 'Status',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Proposals',
        'empty' => 'No proposals yet.',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'set_status' => 'Set status to ":status"',
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
