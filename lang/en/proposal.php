<?php

return [
    'label' => 'Proposal',
    'plural' => 'Proposals',

    'sections' => [
        'main' => 'Proposal details',
        'business_case' => 'Business case',
        'header' => 'Proposal card',
        'current_version' => 'Current version',
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

    'help' => [
        'business_case' => 'The proposal is opened for this business case. Its summary appears below once selected.',
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
        'open_business_case' => 'Open business case',
    ],

    'steps' => [
        'version' => 'Version :no · :status',
        'no_version' => 'No version yet',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
        'created' => 'Proposal created: :no',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
