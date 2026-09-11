<?php

return [
    'label' => 'Business alert',
    'plural' => 'Business alerts',

    'fields' => [
        'alert_no' => 'Alert no',
        'severity' => 'Severity',
        'title' => 'Subject',
        'due_at' => 'Due date',
        'owner' => 'Owner',
        'state' => 'State',
        'opened_at' => 'Opened',
        'acknowledged_at' => 'Acknowledged',
    ],

    'actions' => [
        'open' => 'Open record',
        'acknowledge' => 'Acknowledge',
    ],

    'messages' => [
        'acknowledged' => 'The alert was marked as acknowledged.',
        'link_invalid' => 'The link has expired or is invalid.',
        'link_not_yours' => 'This link was sent to another person.',
    ],

    'notifications' => [
        'title' => [
            'warning' => 'Upcoming deadline',
            'high' => 'Deadline is very close',
            'critical' => 'URGENT: Deadline reached or passed',
        ],
        'body' => ':subject — due :date',
    ],

    'triggers' => [
        'deadline_project_issue' => 'Issue due date: :subject (:context)',
        'deadline_project_risk_review' => 'Risk review date: :subject (:context)',
        'deadline_stage_requirement' => 'Gate requirement due date: :subject (:context)',
        'deadline_stage_condition' => 'Conditional pass condition: :subject (:context)',
        'deadline_recovery_action' => 'Recovery action due date: :subject (:context)',
        'deadline_work_package' => 'Work package planned finish: :subject (:context)',
        'deadline_project_finish' => 'Project planned finish: :subject',
        'deadline_tender' => 'Tender deadline: :subject (:context)',
        'deadline_contract_milestone' => 'Contract milestone: :subject (:context)',
        'deadline_contract_obligation' => 'Contract obligation: :subject (:context)',
        'deadline_certification' => 'Certification expiry: :subject (:context)',
    ],

    'widget' => [
        'heading' => 'Upcoming dates and alerts',
        'description' => 'Open alerts you own; critical ones first.',
        'description_all' => 'All open alerts; critical ones first.',
        'empty' => 'No open alerts.',
    ],
];
