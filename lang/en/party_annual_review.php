<?php

return [
    'label' => 'Annual review',
    'plural' => 'Annual reviews',

    'sections' => [
        'main' => 'Review details',
    ],

    'fields' => [
        'created_at' => 'Created at',
        'next_review_on' => 'Next review',
        'outcome' => 'Outcome',
        'reason' => 'Reason',
        'review_year' => 'Review year',
        'reviewed_at' => 'Reviewed at',
        'reviewer' => 'Reviewer',
        'score' => 'Score',
        'summary' => 'Summary',
    ],

    'relation' => [
        'title' => 'Annual Reviews',
        'empty' => 'No reviews yet.',
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
