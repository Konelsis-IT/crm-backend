<?php

return [
    'label' => 'Gate requirement',
    'plural' => 'Gate requirements',

    'sections' => [
        'main' => 'Requirement details',
    ],

    'fields' => [
        'applicability' => 'Applicability',
        'created_at' => 'Created at',
        'document_revision' => 'Document revision',
        'due_at' => 'Due at',
        'evidence_type_snapshot' => 'Evidence type',
        'is_mandatory_snapshot' => 'Mandatory',
        'name_snapshot_tr' => 'Requirement (TR)',
        'outcome_note' => 'Outcome note',
        'owner' => 'Owner',
        'reason' => 'Reason',
        'requirement_code_snapshot' => 'Requirement code',
        'status' => 'Status',
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
