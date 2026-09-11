<?php

return [
    'label' => 'Template',
    'plural' => 'Templates',

    'sections' => [
        'main' => 'Template details',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Template name',
        'output_kind' => 'Output kind',
        'status' => 'Status',
        'version_no' => 'Version no',
        'locale' => 'Language',
        'view_key' => 'View key',
        'version_status' => 'Version status',
        'published_at' => 'Published at',
        'publisher' => 'Published by',
        'layout_config' => 'Layout settings',
        'required_field_keys' => 'Required field keys',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
    ],

    'relation' => [
        'versions' => [
            'title' => 'Versions',
            'empty' => 'No versions yet.',
        ],
    ],
];
