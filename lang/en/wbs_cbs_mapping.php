<?php

return [
    'label' => 'WBS-CBS mapping',
    'plural' => 'WBS-CBS mappings',

    'sections' => [
        'main' => 'Mapping details',
    ],

    'fields' => [
        'allocation_pct' => 'Allocation (%)',
        'cbs_node' => 'CBS node',
        'cost_category' => 'Cost category',
        'created_at' => 'Created at',
        'name' => 'Name',
        'reason' => 'Reason',
    ],

    'relation' => [
        'title' => 'CBS Allocation',
        'empty' => 'No mappings yet.',
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
