<?php

return [
    'label' => 'Party',
    'plural' => 'Parties',

    'sections' => [
        'main' => 'Party details',
        'organization' => 'Organization details',
        'person' => 'Person details',
    ],

    'fields' => [
        'consent_status' => 'Consent status',
        'country' => 'Country',
        'created_at' => 'Created at',
        'default_locale' => 'Default language',
        'display_name' => 'Display name',
        'family_name' => 'Family name',
        'founded_year' => 'Founded year',
        'given_name' => 'Given name',
        'is_public_company' => 'Public company',
        'job_title' => 'Job title',
        'legal_name' => 'Legal name',
        'party_kind' => 'Kind',
        'party_no' => 'Party no',
        'person_title' => 'Title',
        'reason' => 'Reason',
        'registration_no' => 'Registration no',
        'roles' => 'Roles',
        'sector_code' => 'Sector',
        'status' => 'Status',
        'tax_number' => 'Tax number',
        'tax_office' => 'Tax office',
        'trade_name' => 'Trade name',
        'website_url' => 'Website',
    ],

    'relation' => [
        'title' => 'Parties',
        'empty' => 'No parties yet.',
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
