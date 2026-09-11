<?php

return [
    'label' => 'Project component',
    'plural' => 'Project components',

    'sections' => [
        'main' => 'Component details',
    ],

    'fields' => [
        'capacity_uom' => 'Capacity unit',
        'capacity_value' => 'Capacity',
        'code' => 'Code',
        'component_definition' => 'Component definition',
        'created_at' => 'Created at',
        'definition' => 'Component',
        'note' => 'Note',
        'reason' => 'Reason',
        'scope_state' => 'Scope state',
    ],

    'relation' => [
        'title' => 'Components',
        'empty' => 'No components yet.',
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
        'duplicate' => 'This component is already defined for the project; it cannot be added twice.',
    ],
];
