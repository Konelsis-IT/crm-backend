<?php

return [
    'label' => 'Certificate',
    'plural' => 'Certificates',

    'sections' => [
        'main' => 'Certificate details',
    ],

    'fields' => [
        'certificate_no' => 'Certificate no',
        'certificate_type' => 'Certificate type',
        'created_at' => 'Created at',
        'document_revision' => 'Document revision',
        'issued_on' => 'Issued on',
        'issuer' => 'Issuer',
        'reason' => 'Reason',
        'status' => 'Status',
        'valid_until' => 'Valid until',
    ],

    'relation' => [
        'title' => 'Certificates',
        'empty' => 'No certificates yet.',
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
