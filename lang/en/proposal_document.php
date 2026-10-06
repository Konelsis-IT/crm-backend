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

    'actions' => [
        'upload' => 'Upload document',
        'upload_revision' => 'Upload new version',
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
        'upload' => 'If a document of this type exists, the file becomes its new revision and the proposal gets a new version. Unchanged documents are not copied.',
        'upload_revision' => 'The file becomes the new revision of this document and the proposal gets a new version.',
        'current_only' => 'Documents of the current version. Earlier versions are shown from the Versions button at the top of the page.',
    ],

    'messages' => [
        'uploaded' => 'Document uploaded; new proposal version: Version :no',
        'uploaded_in_place' => 'Document uploaded.',
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
