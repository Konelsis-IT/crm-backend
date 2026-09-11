<?php

return [
    'label' => 'Commercial clarification',
    'plural' => 'Commercial clarifications',

    'sections' => [
        'main' => 'Clarification details',
    ],

    'fields' => [
        'clarification_no' => 'Clarification no',
        'clarification_type' => 'Type',
        'created_at' => 'Created at',
        'customer_contact_party' => 'Customer contact party',
        'description' => 'Description',
        'linked_change' => 'Linked change',
        'reason' => 'Reason',
        'responded_at' => 'Responded at',
        'response' => 'Response',
        'status' => 'Status',
        'title' => 'Title',
    ],

    'relation' => [
        'title' => 'Commercial Clarifications',
        'empty' => 'No clarifications yet.',
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
