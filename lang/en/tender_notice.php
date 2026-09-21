<?php

return [
    'label' => 'Tender notice',
    'plural' => 'Tender notices',
    'label_title' => 'Tender Notice',
    'plural_title' => 'Tender Notices',

    'sections' => [
        'identity' => 'Notice identity',
        'publication' => 'Publication and status',
        'summary' => 'Summary',
        'main' => 'Notice details',
    ],

    'fields' => [
        'business_case' => 'Business case',
        'captured_at' => 'Captured at',
        'created_at' => 'Created at',
        'current_version' => 'Current version',
        'external_notice' => 'External notice ID',
        'issuer_party' => 'Issuer',
        'notice_url' => 'Notice URL',
        'published_on' => 'Published on',
        'reason' => 'Reason',
        'source_document_revision' => 'Source document',
        'status' => 'Status',
        'summary' => 'Summary',
        'tender_source' => 'Tender source',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Tender Notices',
        'empty' => 'No notices yet.',
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
