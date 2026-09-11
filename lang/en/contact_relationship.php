<?php

return [
    'label' => 'Contact relationship',
    'plural' => 'Contact relationships',

    'sections' => [
        'main' => 'Relationship details',
    ],

    'fields' => [
        'contact' => 'Contact',
        'contact_party' => 'Contact party',
        'created_at' => 'Created at',
        'department_note' => 'Department note',
        'is_primary' => 'Primary',
        'reason' => 'Reason',
        'relationship_role' => 'Relationship role',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
    ],

    'relation' => [
        'title' => 'Contacts',
        'empty' => 'No contacts yet.',
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
