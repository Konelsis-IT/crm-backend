<?php

return [
    'label' => 'Stage template',
    'plural' => 'Stage templates',

    'sections' => [
        'main' => 'Template details',
    ],

    'fields' => [
        'code' => 'Code',
        'created_at' => 'Created at',
        'current_version' => 'Current version',
        'name_en' => 'Name (EN)',
        'name_tr' => 'Name (TR)',
        'project_type' => 'Project type',
        'reason' => 'Reason',
        'status' => 'Status',
    ],

    'relation' => [
        'title' => 'Templates',
        'empty' => 'No templates yet.',
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
