<?php

return [
    'label' => 'Proposal document',
    'plural' => 'Proposal documents',

    'sections' => [
        'main' => 'Document details',
    ],

    'fields' => [
        'document' => 'Document',
        'file' => 'File',
        'revision' => 'Revision',
        'uploaded_at' => 'Uploaded',
        'role' => 'Document type',
        'created_at' => 'Created at',
        'document_revision' => 'Document revision',
        'document_role' => 'Document role',
        'reason' => 'Reason',
        'sort_order' => 'Sort order',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Documents',
        'empty' => 'No documents yet.',
    ],

    // D-184: rows of the Documents table that are not copied into the proposal.
    'virtual' => [
        'reference_list' => 'Reference list',
        'reference_count' => ':type · :count references',
    ],

    'actions' => [
        'upload' => 'Upload document',
        // D-186: no "Upload new version"; the bin removes the document from the proposal.
        'detach' => 'Remove from proposal',
        'detach_heading' => 'Remove the document from the proposal?',
        'download' => 'Download',
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

    'help' => [
        'upload' => 'You can select several files. Each file is added to the current version as a new document of this type; the proposal version does not change.',
        'detach' => 'The document is removed from this proposal version; it stays in Documents and is not deleted. The proposal version does not change.',
        'current_only' => 'Documents of the current version. Earlier versions are shown from the Versions button at the top of the page.',
    ],

    'messages' => [
        'uploaded_in_place' => 'Document uploaded.',
        'detached' => 'Document removed from the proposal; it stays in Documents.',
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
