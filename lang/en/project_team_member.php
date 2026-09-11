<?php

return [
    'label' => 'Team member',
    'plural' => 'Project team',

    'sections' => [
        'main' => 'Assignment',
    ],

    'fields' => [
        'project' => 'Project',
        'personnel' => 'Personnel',
        'workstream' => 'Step (department)',
        'team_role' => 'Role',
        'allocation_pct' => 'Allocation (%)',
        'assigned_from' => 'From',
        'assigned_until' => 'Until',
        'is_lead' => 'Lead',
        'status' => 'Status',
        'note' => 'Note',
        'created_at' => 'Created at',
        'reason' => 'Reason',
    ],

    'help' => [
        'allocation_pct' => 'Share of the person\'s time dedicated to this project.',
    ],

    'relation' => [
        'title' => 'Project team',
        'empty' => 'No team members yet.',
    ],

    'actions' => [
        'end' => 'End assignment',
        'change_status' => 'Change status',
        'set_status' => 'Set status to \":status\"',
    ],

    'messages' => [
        'done' => 'Done.',
        'ended' => 'Assignment ended.',
        'status_changed' => 'Status updated.',
    ],

    'validation' => [
        'duplicate' => 'This person already holds that role on the team.',
    ],
];
