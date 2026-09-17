<?php

return [
    'label' => 'Report',
    'plural' => 'Reports',

    'sections' => [
        'report' => 'Report',
        'answers' => 'Report content',
        'board' => 'Work board',
        'metrics' => 'Numeric summary',
        'review' => 'Review',
        'history' => 'History',
    ],

    'fields' => [
        'report_no' => 'Report no',
        'template' => 'Report template',
        'template_help' => 'About the template',
        'title' => 'Title',
        'kind' => 'Kind',
        'status' => 'Status',
        'author' => 'Author',
        'author_org_unit' => 'Department',
        'subject' => 'Related record',
        'subject_personnel' => 'Related personnel',
        'subject_project' => 'Related project',
        'subject_component' => 'Related product / component',
        'subject_proposal' => 'Related proposal',
        'subject_business_case' => 'Related business case',
        'period' => 'Period',
        'period_none' => 'Period',
        'period_day' => 'Date',
        'period_week' => 'Week',
        'period_month' => 'Month',
        'period_range' => 'Start',
        'period_end' => 'End',
        'submitted_at' => 'Submitted',
        'reviewer' => 'Reviewer',
        'reviewed_at' => 'Reviewed at',
        'review_comment' => 'Review note',
        'revision_count' => 'Revisions',
        'confidential' => 'Confidentiality',
        'created_at' => 'Created',
        'items' => 'Work items',
        'metric_key' => 'Measurement',
        'metric_value' => 'Value',
    ],

    'items' => [
        'title' => 'Work item',
        'status' => 'Status',
        'project' => 'Project',
        'work_hours' => 'Hours',
        'description' => 'Note',
    ],

    'help' => [
        'list' => 'My reports: reports I wrote. Review inbox: reports sent to me. My team: reports of my subordinates and department.',
        'report' => 'Choose the report template first; it determines the fields and the layout.',
        'title' => 'Leave empty to generate it from the template name, related record and period.',
        'answers' => 'These fields belong to the selected template.',
        'board' => 'Work done in the period; each row is a work item shown as a board column by status. Unfinished items of the previous report are carried over.',
        'period_week' => 'Pick any day of the week; the period is stored as Monday–Sunday.',
        'period_month' => 'Pick any day of the month; the period is stored as the whole month.',
        'metrics' => 'Numeric indicators of the template; calculated on submission.',
        'submit' => 'A submitted report can be withdrawn until the review starts; the reviewer is notified when the template requires review.',
        'review_comment' => 'A note is required for revision and rejection; the author sees it.',
        'delete' => 'The draft is deleted together with its items. This cannot be undone.',
    ],

    'tabs' => [
        'mine' => 'My reports',
        'review' => 'Review inbox',
        'team' => 'My team',
        'all' => 'All',
    ],

    'actions' => [
        'create' => 'Write report',
        'today' => "Today's report",
        'open' => 'Open',
        'edit' => 'Edit',
        'submit' => 'Submit',
        'withdraw' => 'Withdraw',
        'approve' => 'Approve',
        'request_revision' => 'Request revision',
        'reject' => 'Reject',
        'delete' => 'Delete draft',
        'add_item' => 'Add work item',
        'add_measurement' => 'Add measurement',
    ],

    'values' => [
        'no_reviewer' => 'No review needed',
        'no_history' => 'No activity yet.',
        'no_items' => 'No items in this column.',
        'item_count' => ':count items',
        'hours' => 'h',
        'carried_over' => 'Carried over',
        'confidential' => 'Confidential report',
        'template_missing' => 'The template of this report is no longer defined; content cannot be shown.',
        'week_of' => 'week of :date',
    ],

    'relation' => [
        'help' => 'Reports related to this record; only the ones you may view are listed.',
        'empty' => 'No reports yet.',
    ],

    'messages' => [
        'created' => 'Report saved as draft.',
        'updated' => 'Report updated.',
        'submitted' => 'Report submitted.',
        'withdrawn' => 'Report withdrawn; you can edit it again.',
        'approved' => 'Report approved.',
        'revision_required' => 'Report returned to the author for revision.',
        'rejected' => 'Report rejected.',
        'deleted' => 'Draft deleted.',
    ],

    'notifications' => [
        'submitted' => ['title' => 'Report awaiting your review: :no', 'body' => ':title — :author'],
        'approved' => ['title' => 'Your report was approved: :no', 'body' => ':title — :reviewer'],
        'revision_required' => ['title' => 'Revision requested on your report: :no', 'body' => ':title — see the note on the report.'],
        'rejected' => ['title' => 'Your report was rejected: :no', 'body' => ':title — see the note on the report.'],
    ],

    'rating' => [
        '1' => '1 — Insufficient',
        '2' => '2 — Needs improvement',
        '3' => '3 — Meets expectations',
        '4' => '4 — Good',
        '5' => '5 — Excellent',
    ],

    'metrics' => [
        'total_hours' => 'Total work (hours)',
        'done_count' => 'Completed items',
        'open_count' => 'Open items',
        'progress_deviation' => 'Deviation from plan',
        'defect_rate' => 'Defect rate',
        'overall_score' => 'Overall score',
    ],

    'templates' => [
        'daily_work' => [
            'name' => 'Daily work report',
            'description' => 'The day\'s work as a board (planned / in progress / done / blocked), a short summary and tomorrow\'s plan.',
            'fields' => [
                'summary' => 'Summary of the day',
                'blockers' => 'Blockers and needs',
                'tomorrow_plan' => 'Plan for tomorrow',
            ],
        ],
        'weekly_work' => [
            'name' => 'Weekly work report',
            'description' => 'The week\'s work board, highlights, blockers and next week\'s plan; reviewed by the line manager.',
            'fields' => [
                'summary' => 'Summary of the week',
                'achievements' => 'Highlights',
                'blockers' => 'Blockers and needs',
                'next_week_plan' => 'Plan for next week',
            ],
        ],
        'monthly_work' => [
            'name' => 'Monthly work report',
            'description' => 'The month\'s work board, achievements, next month\'s targets and self-assessment; reviewed by the department manager.',
            'fields' => [
                'summary' => 'Summary of the month',
                'achievements' => 'Achievements',
                'blockers' => 'Blockers and needs',
                'next_month_targets' => 'Targets for next month',
                'self_score' => 'Self-assessment',
            ],
        ],
        'project_status' => [
            'name' => 'Project status report',
            'description' => 'Progress percentages, overall health, risks, next steps and pending decisions for the selected project.',
            'fields' => [
                'progress_pct' => 'Physical progress (%)',
                'planned_pct' => 'Planned progress (%)',
                'health' => 'Overall health',
                'open_issue_count' => 'Open issues',
                'summary' => 'Status summary',
                'risks' => 'Risks and issues',
                'next_steps' => 'Next steps',
                'decisions_needed' => 'Pending decisions',
            ],
            'options' => [
                'health' => [
                    'on_track' => 'On track',
                    'at_risk' => 'At risk',
                    'delayed' => 'Delayed',
                ],
            ],
        ],
        'component_performance' => [
            'name' => 'Product / component report',
            'description' => 'Delivered and defective quantities, unit cost, quality and supplier assessment for the selected product or component.',
            'fields' => [
                'delivered_qty' => 'Delivered quantity',
                'defect_count' => 'Defects / failures',
                'unit_cost' => 'Unit cost',
                'quality_note' => 'Quality assessment',
                'supplier_note' => 'Supplier note',
                'improvement' => 'Improvement proposal',
            ],
        ],
        'proposal_assessment' => [
            'name' => 'Proposal assessment report',
            'description' => 'Win probability, competitor and price position, strengths / weaknesses and recommendation for the selected proposal; reviewed by the department manager.',
            'fields' => [
                'win_probability_pct' => 'Win probability (%)',
                'competitor_count' => 'Competitors',
                'price_position' => 'Price position',
                'recommendation' => 'Recommendation',
                'summary' => 'Assessment',
                'strengths' => 'Strengths',
                'weaknesses' => 'Weaknesses',
            ],
            'options' => [
                'price_position' => [
                    'low' => 'Low',
                    'competitive' => 'Competitive',
                    'high' => 'High',
                ],
                'recommendation' => [
                    'submit' => 'Submit the proposal',
                    'revise' => 'Revise',
                    'withdraw' => 'Withdraw',
                ],
            ],
        ],
        'business_case_review' => [
            'name' => 'Business case review report',
            'description' => 'Commercial and technical risk, customer relationship, recommendation and actions for the selected business case; reviewed by the department manager.',
            'fields' => [
                'commercial_risk' => 'Commercial risk',
                'technical_risk' => 'Technical risk',
                'recommendation' => 'Recommendation',
                'summary' => 'Overall assessment',
                'customer_relationship' => 'Customer relationship',
                'actions' => 'Actions',
            ],
            'options' => [
                'commercial_risk' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
                'technical_risk' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
                'recommendation' => [
                    'pursue' => 'Pursue',
                    'hold' => 'Hold',
                    'drop' => 'Drop',
                ],
            ],
        ],
        'personnel_manager_evaluation' => [
            'name' => 'Manager evaluation',
            'description' => 'The line manager or department manager rates a team member for a period and writes an opinion. Confidential; the person does not see it.',
            'fields' => [
                'performance' => 'Results',
                'quality' => 'Quality of work',
                'collaboration' => 'Teamwork',
                'discipline' => 'Discipline and attendance',
                'initiative' => 'Initiative',
                'recommendation' => 'Recommendation',
                'overall' => 'Overall opinion',
                'strengths' => 'Strengths',
                'development_areas' => 'Development areas',
            ],
            'options' => [
                'recommendation' => [
                    'retain' => 'Continue in current role',
                    'promote' => 'Consider promotion',
                    'develop' => 'Development plan',
                    'warn' => 'Warning',
                ],
            ],
        ],
        'personnel_hr_evaluation' => [
            'name' => 'HR opinion',
            'description' => 'Personnel with HR authority write attendance, compliance, training and disciplinary notes about a person. Confidential.',
            'fields' => [
                'attendance' => 'Attendance and time management',
                'compliance' => 'Compliance with rules',
                'recommendation' => 'Recommendation',
                'overall' => 'HR opinion',
                'training_status' => 'Training and development status',
                'disciplinary_note' => 'Disciplinary note',
            ],
            'options' => [
                'recommendation' => [
                    'none' => 'No action needed',
                    'training' => 'Plan training',
                    'warning' => 'Warning',
                    'promotion_review' => 'Promotion review',
                ],
            ],
        ],
        'system_data' => [
            'name' => 'System data report',
            'description' => 'Measurements taken from a system (name and value), observations and anomalies. Numeric measurements are stored as indicators.',
            'fields' => [
                'data_source' => 'Data source',
                'measurements' => 'Measurements',
                'observations' => 'Observations',
                'anomalies' => 'Anomalies',
            ],
            'help' => [
                'measurements' => 'One measurement per row: the name on the left (e.g. "Production kWh"), the value on the right.',
            ],
        ],
    ],
];
