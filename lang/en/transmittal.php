<?php

return [
    'label' => 'Transmittal',
    'plural' => 'Transmittals',

    'sections' => [
        'main' => 'Transmittal details',
    ],

    'fields' => [
        'project' => 'Project',
        'recipient_party' => 'Recipient party',
        'transmittal_no' => 'Transmittal no',
        'recipient_description' => 'Recipient',
        'purpose' => 'Purpose',
        'status' => 'Status',
        'issuer' => 'Issued by',
        'issued_at' => 'Issued at',
        'cover_revision' => 'Cover document',
        'external_reference' => 'External reference',
    ],

    'help' => [
        'transmittal_no' => 'Generated automatically.',
    ],

    'relation' => [
        'items' => [
            'title' => 'Items',
            'empty' => 'No items added yet.',
        ],
    ],
];
