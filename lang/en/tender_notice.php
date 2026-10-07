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
        'header' => 'Tender card',
        'details' => 'Tender details',
    ],

    'fields' => [
        'business_case' => 'Business case',
        'captured_at' => 'Identified on',
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
        'continue_to_case' => 'Create a potential job from this tender after saving',
    ],

    'help' => [
        'no_case' => 'No potential job',
        'case_after_save' => 'The tender is saved without a potential job. The potential job is opened from this tender with the tender preselected.',
        'no_case_yet' => 'No potential job has been opened from this tender yet.',
        'proposal_after_case' => 'The proposal is prepared after a potential job is opened from the tender.',
        'project_after_case' => 'The project opens when the proposal is won.',
    ],

    'steps' => [
        'no_case' => 'No potential job yet',
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
        'open' => 'Open tender',
        'create_case' => 'Create a potential job from this tender',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
        'created' => 'Tender saved.',
        'draft_saved' => 'Tender saved as a draft.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
