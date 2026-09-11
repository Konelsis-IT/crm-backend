<?php

return [
    'label' => 'Stage node',
    'plural' => 'Stage nodes',

    'sections' => [
        'main' => 'Gate details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'description' => 'Description',
        'is_hard_gate' => 'Hard gate',
        'name_en' => 'Name (EN)',
        'name_tr' => 'Name (TR)',
        'owner_group' => 'Owner group',
        'owner_group_definition' => 'Owner group definition',
        'reason' => 'Reason',
        'sequence_no' => 'Sequence no',
        'stage_code' => 'Gate code',
        'template' => 'Template',
        'template_version' => 'Template version',
    ],

    'relation' => [
        'title' => 'Gates',
        'empty' => 'No gates yet.',
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
