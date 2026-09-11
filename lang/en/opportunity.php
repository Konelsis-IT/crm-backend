<?php

return [
    'label' => 'Opportunity',
    'plural' => 'Opportunities',

    'sections' => [
        'main' => 'Opportunity details',
    ],

    'fields' => [
        'bid_decider' => 'Bid decided by',
        'bid_decision' => 'Bid decision',
        'competitor_note' => 'Competitor note',
        'created_at' => 'Created at',
        'expected_decision_on' => 'Expected decision',
        'expected_value' => 'Expected value',
        'market_code' => 'Market',
        'probability_pct' => 'Probability (%)',
        'reason' => 'Reason',
        'stage' => 'Stage',
    ],

    'relation' => [
        'title' => 'Opportunity',
        'empty' => 'No opportunity record.',
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
