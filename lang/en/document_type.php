<?php

return [
    'label' => 'Document Type',
    'plural' => 'Document Types',

    'sections' => [
        'main' => 'Type details',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Type name',
        'discipline' => 'Discipline',
        'numbering_prefix' => 'Numbering prefix',
        'is_controlled' => 'Controlled document',
        'allowed_extensions' => 'Allowed extensions',
        'max_byte_size' => 'Max file size (bytes)',
        'default_classification' => 'Default classification',
        'default_retention_policy' => 'Default retention policy',
        'document_count' => 'Document count',
        'status' => 'Status',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
    ],
];
