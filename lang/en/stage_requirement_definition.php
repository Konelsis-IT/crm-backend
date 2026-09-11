<?php

return [
    'label' => 'Stage requirement definition',
    'plural' => 'Requirement definitions',

    'sections' => [
        'main' => 'Requirement details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'description' => 'Description',
        'evidence_type' => 'Evidence type',
        'is_mandatory' => 'Mandatory',
        'min_document_type' => 'Minimum document type',
        'name_en' => 'Name (EN)',
        'name_tr' => 'Name (TR)',
        'reason' => 'Reason',
        'requirement_code' => 'Requirement code',
        'sort_order' => 'Sort order',
    ],

    'relation' => [
        'title' => 'Requirements',
        'empty' => 'No requirements yet.',
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
