<?php

return [
    'label' => 'Meeting plan',
    'plural' => 'Meeting plan',
    'label_title' => 'Meeting Plan',
    'plural_title' => 'Meeting Plan',

    'sections' => [
        'main' => 'Meeting',
        'people' => 'Personnel',
        'result' => 'Meeting result',
        'follow_up' => 'Follow-up of',
    ],

    'fields' => [
        'party' => 'Company / institution',
        'contact' => 'Contact person',
        'planned_on' => 'Meeting date',
        'channel' => 'Channel',
        'personnel' => 'Responsible',
        'participants' => 'Attending personnel',
        'subject' => 'Subject / purpose',
        'note' => 'Note',
        'status' => 'Status',
        'source' => 'Source',
        'completed_at' => 'Result entered at',
        'cancel_reason' => 'Why did it not happen?',
        'follow_up_of' => 'Meeting note date',
    ],

    'help' => [
        'participants' => 'Personnel attending the meeting. They also receive the reminder.',
        'note' => 'Preparation note or why the meeting did not happen.',
        'complete' => 'The result is written to the company\'s meeting notes. If you give a next step date, it appears on the calendar as a new planned meeting and a reminder is sent.',
        'reschedule' => 'The reminder (1 day before and on the morning) is sent again for the new date.',
        'cancel' => 'The meeting is marked as "Did not happen"; your reason is added to the plan note.',
        'result' => 'This comes from the company\'s meeting notes; correct it there.',
    ],

    'values' => [
        'overdue' => 'Overdue',
        'no_personnel' => 'Not assigned',
        'follow_up_subject' => 'Next step',
    ],

    'tabs' => [
        'upcoming' => 'Upcoming',
        'today' => 'Today',
        'overdue' => 'Overdue',
        'done' => 'Held',
        'cancelled' => 'Not held',
        'all' => 'All',
    ],

    'filters' => [
        'personnel' => 'Personnel (responsible or attending)',
        'dates' => 'Date range',
        'from' => 'From',
        'until' => 'Until',
    ],

    'actions' => [
        'open' => 'Open',
        'create' => 'Plan a meeting',
        'calendar' => 'Calendar view',
        'list' => 'List view',
        'complete' => 'Enter result',
        'reschedule' => 'Change date',
        'cancel' => 'Did not happen',
    ],

    'messages' => [
        'completed' => 'The result was saved and written to the company\'s meeting notes.',
        'rescheduled' => 'The meeting date was changed.',
        'cancelled' => 'The meeting was marked as not held.',
    ],

    'notifications' => [
        'meeting' => [
            'day_before' => 'You have a meeting tomorrow: :party',
            'same_day' => 'You have a meeting today: :party',
        ],
        'follow_up' => [
            'day_before' => 'Next step tomorrow: :party',
            'same_day' => 'Next step today: :party',
        ],
    ],

    'ui' => [
        'meeting_summary' => ':count meetings this month',
        'meeting_day_label' => ':date, :count meetings',
        'meeting_day_count' => ':count meetings',
        'meeting_day_empty' => 'No meetings on this day.',
        'meeting_add_to_day' => 'Plan a meeting on this day',
        'meeting_follow_up' => 'Next step',
        'meeting_no_personnel' => 'Not assigned',
        'meeting_all_personnel' => 'All personnel',
        'meeting_personnel_filter' => 'Filter by personnel',
        'meeting_status_planned' => 'Planned',
        'meeting_status_overdue' => 'Overdue',
        'meeting_status_done' => 'Held',
        'meeting_status_cancelled' => 'Not held',
    ],
];
