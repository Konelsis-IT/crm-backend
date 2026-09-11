<?php

return [
    'label' => 'Approval step',
    'plural' => 'Approval Steps',

    'relation' => [
        'title' => 'Steps',
        'empty' => 'No steps yet.',
    ],

    'sections' => [
        'identity' => 'Step',
        'resolver' => 'Approver',
    ],

    'fields' => [
        'step_code' => 'Step code',
        'sequence_no' => 'Order',
        'decision_rule' => 'Decision rule',
        'name_tr' => 'Name (TR)',
        'name_en' => 'Name (EN)',
        'sla_minutes' => 'Decision time (min)',
        'resolver_type' => 'How the approver is found',
        'target_personnel' => 'Personnel',
        'target_position' => 'Position',
        'role_code' => 'Role',
        'is_optional' => 'Optional step',
        'allows_delegation' => 'Accepts delegation',
    ],

    'help' => [
        'step_code' => 'Upper case; unique within the version (e.g. CHECK, APPROVE).',
        'sequence_no' => 'Appended to the end when blank. Sequential mode follows this order.',
        'decision_rule' => 'When several approvers are found: any one / all / majority.',
        'sla_minutes' => 'Falls back to the version time when blank.',
        'resolver' => 'The approver is resolved when the request opens: a person/position, the requester\'s line manager, the unit manager, a project role or an RBAC role.',
        'is_optional' => 'The step is skipped when no approver is found; a mandatory step blocks the request.',
        'allows_delegation' => 'A valid delegate of the approver may decide on their behalf in this step.',
        'relation' => 'Steps can only be edited on a draft version.',
        'empty' => 'Add at least one step before publishing.',
    ],
];
