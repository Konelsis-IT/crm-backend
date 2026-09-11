<?php

return [
    'label' => 'Business development activity',
    'plural' => 'Activities',

    'sections' => [
        'main' => 'Activity details',
    ],

    'fields' => [
        'activity_type' => 'Activity type',
        'created_at' => 'Created at',
        'location' => 'Location',
        'next_action' => 'Next action',
        'next_action_due_at' => 'Next action due',
        'occurred_at' => 'Occurred at',
        'organizer' => 'Organizer',
        'outcome_summary' => 'Outcome summary',
        'party' => 'Party',
        'reason' => 'Reason',
        'subject' => 'Subject',
    ],

    'relation' => [
        'title' => 'Activities',
        'empty' => 'No activities yet.',
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
