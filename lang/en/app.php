<?php

return [
    'name' => 'Konelsis Admin Panel',
    'language' => 'Language selection',

    'actions' => [
        'open' => 'Open',
        'save' => 'Save',
    ],

    'dashboard' => [
        'title' => 'Overview',
    ],

    'nav' => [
        'reports' => 'Reports',
        'analytics' => 'Analytics',
        'acquisition' => 'Business Acquisition',
        'operations' => 'Operations',
        'project_group' => 'Project Group',
        'procurement' => 'Procurement',
        'tenders' => 'Tenders',
        'administrative' => 'Administrative',
        'documents' => 'Documents',
        'settings' => 'Settings',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'errors' => [
        'title' => 'The operation could not be completed',
        'stale_record' => 'The record was changed by someone else after you opened it. Reload the page and try again.',
        'invalid_transition' => 'This status change is not allowed.',
        'self_parent' => 'A department cannot be its own parent.',
        'generic' => 'An unexpected error occurred. Nothing was saved.',
    ],
];
