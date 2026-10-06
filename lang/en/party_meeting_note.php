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
        'archived_at' => 'Archived at',
    ],

    'help' => [
        'business_case' => 'Choose if the meeting is about a potential job; the note also shows on that job.',
        'proposals' => 'Proposals discussed in the meeting (proposals of the selected potential job); the note also shows on the proposal.',
        'next_action_reminder' => 'If a next step date is given, the step appears on the Meeting plan calendar as a planned meeting; the personnel who held the meeting gets a bell notification 1 day before and on the morning of that day.',
        'archive' => 'The note is not deleted but archived: it no longer shows in lists, and its Meeting plan entry is archived too. Find it with the Archive filter and restore it.',
        'restore' => 'The note and its Meeting plan entry show again.',
    ],

    'filters' => [
        'archive' => 'Archive',
        'archive_active' => 'Active',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
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
        'edit' => 'Edit note',
        'archive' => 'Archive',
        'restore' => 'Restore from archive',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
        'updated' => 'The meeting note was updated.',
        'archived' => 'The meeting note was archived.',
        'restored' => 'The meeting note was restored from the archive.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
