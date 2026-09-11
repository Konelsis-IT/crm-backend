<?php

return [
    'label' => 'Work package',
    'plural' => 'Work packages',

    'sections' => [
        'main' => 'Work package details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'description' => 'Description',
        'name' => 'Name',
        'owner' => 'Owner',
        'package_code' => 'Package code',
        'planned_finish_on' => 'Planned finish',
        'planned_start_on' => 'Planned start',
        'project' => 'Project',
        'project_workstream' => 'Project workstream',
        'reason' => 'Reason',
        'status' => 'Status',
        'wbs_node' => 'WBS node',
        'workstream' => 'Workstream',
    ],

    'relation' => [
        'title' => 'Work Packages',
        'empty' => 'No work packages yet.',
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
