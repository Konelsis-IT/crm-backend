<?php

return [
    'label' => 'Proposal version',
    'plural' => 'Proposal versions',

    'sections' => [
        'main' => 'Version details',
    ],

    'fields' => [
        'approved_at' => 'Approved at',
        'created_at' => 'Created at',
        'currency' => 'Currency',
        'is_critical_route' => 'Critical route',
        'locale' => 'Language',
        'margin_pct' => 'Margin (%)',
        'preparer' => 'Prepared by',
        'project_group_opinion_document_revision' => 'Project group opinion',
        'proposal' => 'Proposal',
        'reason' => 'Reason',
        'status' => 'Status',
        'submitted_at' => 'Submitted at',
        'summary' => 'Summary',
        'total_price' => 'Total price',
        'validity_until' => 'Valid until',
        'version_no' => 'Version no',
    ],

    'relation' => [
        'title' => 'Versions',
        'empty' => 'No versions yet.',
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
