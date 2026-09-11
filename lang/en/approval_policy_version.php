<?php

return [
    'label' => 'Policy version',
    'plural' => 'Policy Versions',

    'relation' => [
        'title' => 'Versions',
        'empty' => 'No versions yet. Open a new version, add its steps and publish it.',
    ],

    'sections' => [
        'flow' => 'Flow',
        'threshold' => 'Amount threshold (optional)',
        'note' => 'Note',
    ],

    'fields' => [
        'version_no' => 'Version',
        'status' => 'Status',
        'mode' => 'Flow mode',
        'quorum_count' => 'Required approvals',
        'requires_maker_checker' => 'Requester cannot approve',
        'reapproval_on_change' => 'Re-approve on change',
        'sla_minutes' => 'Decision time (min)',
        'risk_level' => 'Risk level',
        'applies_min_amount' => 'Minimum amount',
        'applies_max_amount' => 'Maximum amount',
        'currency_code' => 'Currency',
        'change_summary' => 'Change summary',
        'publisher' => 'Published by',
        'published_at' => 'Published at',
        'steps_count' => 'Steps',
    ],

    'help' => [
        'relation' => 'A new version opens as a draft; publish it after adding steps. A published version cannot change — open a new one.',
        'flow' => 'How the steps are processed and segregation of duties.',
        'mode' => 'Sequential: one step after another. Parallel: all at once, all must complete. Quorum: all at once, a set number of steps is enough.',
        'quorum_count' => 'Number of steps that must complete for the request to be approved in quorum mode.',
        'requires_maker_checker' => 'When on, the requester can never approve their own request in any step (segregation of duties).',
        'reapproval_on_change' => 'If the subject changes while approval is running, the request is invalidated and a new one is needed.',
        'sla_minutes' => 'Used when a step has no time of its own; when exceeded the request expires and management is notified.',
        'threshold' => 'For subjects with an amount (proposal, order): the range this policy applies to. Blank means any amount.',
        'publish' => 'Publishing supersedes the previous published version and activates the policy. A published version cannot change.',
    ],

    'actions' => [
        'create' => 'New version',
        'open_steps' => 'Open steps',
        'publish' => 'Publish',
    ],

    'messages' => [
        'published' => 'Version published; policy is active.',
    ],
];
