<?php

return [
    'label' => 'Tender source',
    'plural' => 'Tender sources',

    'sections' => [
        'main' => 'Source details',
    ],

    'fields' => [
        'access_mode' => 'Access mode',
        'base_url' => 'Base URL',
        'code' => 'Code',
        'created_at' => 'Created at',
        'name_en' => 'Name (EN)',
        'name_tr' => 'Name (TR)',
        'reason' => 'Reason',
        'scraping_allowed' => 'Scraping allowed',
        'source_type' => 'Source type',
        'status' => 'Status',
        'terms_reference' => 'Terms reference',
    ],

    'relation' => [
        'title' => 'Tender Sources',
        'empty' => 'No sources yet.',
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
