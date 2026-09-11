<?php

return [
    'label' => 'Address',
    'plural' => 'Addresses',

    'sections' => [
        'main' => 'Address details',
    ],

    'fields' => [
        'address_type' => 'Address type',
        'city' => 'City',
        'country' => 'Country',
        'created_at' => 'Created at',
        'abroad' => 'Outside Türkiye',
        'district' => 'District',
        'is_primary' => 'Primary',
        'line1' => 'Address line 1',
        'line2' => 'Address line 2',
        'postal_code' => 'Postal code',
        'reason' => 'Reason',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Addresses',
        'empty' => 'No addresses yet.',
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
