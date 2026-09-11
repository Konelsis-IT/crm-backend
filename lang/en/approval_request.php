<?php

return [
    'label' => 'Approval request',
    'plural' => 'Approval Requests',
    'nav' => 'Approvals',

    'sections' => [
        'summary' => 'Request',
    ],

    'tabs' => [
        'inbox' => 'Waiting for me',
        'mine' => 'My requests',
        'open' => 'Open requests',
        'all' => 'All',
    ],

    'fields' => [
        'subject_type' => 'Subject type',
        'subject_work_request' => 'Request',
        'subject_document_revision' => 'Document revision',
        'note' => 'Note',
        'subject' => 'Subject',
        'policy' => 'Policy',
        'requester' => 'Requester',
        'status' => 'Status',
        'waiting_on' => 'Waiting on',
        'requested_at' => 'Requested at',
        'decided_at' => 'Decided at',
        'invalidation_reason' => 'Invalidation reason',
        'note' => 'Request note',
        'reason' => 'Reason',
        'comment' => 'Comment / reason',
        'approver' => 'Approver',
        'unresolved_reason' => 'Unresolved reason',
        'step_status' => 'Step status',
        'activated_at' => 'Activated',
        'due_at' => 'Due',
        'decider' => 'Decided by',
        'on_behalf_of' => 'On behalf of',
        'decision' => 'Decision',
    ],

    'values' => [
        'unresolved' => 'No approver found',
    ],

    'callouts' => [
        'open' => 'Waiting for a decision — currently with: :who',
        'approved' => 'The request was approved; the subject was updated.',
        'rejected' => 'The request was rejected; see the decisions for the reason.',
        'closed' => 'The request is closed: :status',
    ],

    'actions' => [
        'create' => 'Open approval request',
        'open' => 'Open',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'return' => 'Return',
        'cancel' => 'Cancel request',
    ],

    'help' => [
        'create' => 'An approval request is opened for the selected record under the published policy; approvers are resolved from the policy.',
        'list' => 'Requests waiting for your decision are under "Waiting for me"; your own under "My requests".',
        'comment_required' => 'A reason is required for reject and return.',
    ],

    'messages' => [
        'created' => 'The approval request was opened and the approvers notified.',
        'link_invalid' => 'The link has expired or is invalid.',
        'link_not_yours' => 'This link was sent to another person.',
        'decided' => 'Your decision was recorded.',
        'cancelled' => 'The request was cancelled.',
        'empty' => 'No approval requests yet.',
    ],

    'relation' => [
        'steps' => [
            'title' => 'Steps and approvers',
        ],
        'decisions' => [
            'title' => 'Decisions',
            'empty' => 'No decision yet.',
        ],
    ],

    'notifications' => [
        'step_activated' => [
            'title' => 'Your approval is needed',
            'body' => ':subject — step: :step. Due: :due',
        ],
        'approved' => [
            'title' => 'Your request was approved',
            'body' => ':subject was approved.',
        ],
        'rejected' => [
            'title' => 'Your request was rejected',
            'body' => ':subject was rejected; see the decisions for the reason.',
        ],
        'expired' => [
            'title' => 'Approval time expired',
            'body' => 'The decision time for :subject passed; the request is closed.',
        ],
        'cancelled' => [
            'title' => 'Approval request cancelled',
            'body' => 'The request for :subject was cancelled.',
        ],
        'invalidated' => [
            'title' => 'Approval request invalidated',
            'body' => ':subject changed while approval was running; a new request is needed.',
        ],
    ],
];
