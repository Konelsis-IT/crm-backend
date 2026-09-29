<?php

return [
    'label' => 'Meeting note',
    'plural' => 'Meeting notes',

    'sections' => [
        'main' => 'Meeting details',
    ],

    'fields' => [
        'business_case' => 'Potential job',
        'proposals' => 'Proposals',
        'channel' => 'Channel',
        'contact' => 'Contact person',
        'created_at' => 'Created at',
        'next_action' => 'Next step',
        'next_action_on' => 'Next step date',
        'note' => 'Note',
        'noted_on' => 'Meeting date',
        'personnel' => 'Konelsis staff',
        'reason' => 'Reason',
        'subject' => 'Subject',
    ],

    'help' => [
        'business_case' => 'Choose if the meeting is about a potential job; the note also shows on that job.',
        'proposals' => 'Proposals discussed in the meeting (proposals of the selected potential job); the note also shows on the proposal.',
        'next_action_reminder' => 'If a next step date is given, the step appears on the Meeting plan calendar as a planned meeting; the personnel who held the meeting gets a bell notification 1 day before and on the morning of that day.',
    ],

    'relation' => [
        'title' => 'Meeting notes',
        'empty' => 'No meeting notes yet.',
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
