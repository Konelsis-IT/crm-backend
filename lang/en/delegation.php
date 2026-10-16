<?php

return [
    'label' => 'Delegation',
    'plural' => 'Delegations',

    'sections' => [
        'parties' => 'Parties and scope',
        'validity' => 'Validity',
    ],

    'fields' => [
        'grantor' => 'Grantor',
        'delegate' => 'Delegate',
        'capability_code' => 'Delegated capability',
        'scope_type' => 'Scope',
        'scope_policy' => 'Approval policy',
        'reason' => 'Reason',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'period' => 'Period',
        'status' => 'Status',
        'approver' => 'Approved by',
        'revoked_at' => 'Revoked at',
        'revoker' => 'Revoked by',
        'revoke_reason' => 'Revoke reason',
    ],

    'capabilities' => [
        'approval_decide' => 'Make approval decisions',
    ],

    'help' => [
        'parties' => 'The grantor hands their approval authority to the delegate for a period. The delegate\'s decision is recorded together with whom it was made for.',
        'scope_type' => '"All": every approval step of the grantor. "Approval policy": only the steps of the selected policy.',
        'valid_until' => 'An end date is required; the delegation ends automatically when it passes.',
        'list' => 'Delegations you granted or received; administrators see all of them.',
        'open_list' => 'Current and upcoming delegations. Use All delegations for past ones and editing.',
    ],

    'actions' => [
        'revoke' => 'Revoke delegation',
        'all' => 'All delegations',
        'new' => 'Grant delegation',
        'close' => 'Close',
    ],

    'empty_open' => 'No current or upcoming delegation.',

    'messages' => [
        'revoked' => 'Delegation revoked.',
    ],
];
