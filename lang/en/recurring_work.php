<?php

declare(strict_types=1);

/*
 * Duties and responsibilities (recurring work, D-219, B57).
 */
return [
    'label' => 'Duty',
    'plural' => 'Duties and responsibilities',
    'nav' => 'Duties and responsibilities',
    'empty' => 'No duties defined',
    'empty_help' => 'Routine work (reports, mailboxes, checks) is defined here; every period a work item lands on the board.',
    'empty_person' => 'No duties defined for this person',

    'sections' => [
        'main' => 'Duty',
        'where' => 'Scope and period',
    ],

    'fields' => [
        'title' => 'Duty',
        'description' => 'What to check',
        'personnel' => 'Owner',
        'kind' => 'Kind',
        'frequency' => 'Frequency',
        'weekday' => 'Day',
        'month_day' => 'Day of month',
        'priority' => 'Priority',
        'org_unit' => 'Department',
        'project' => 'Project',
        'category' => 'Category',
        'starts_on' => 'Starts',
        'ends_on' => 'Ends',
        'is_active' => 'Active',
        'compliance' => 'Recent periods',
    ],

    'help' => [
        'title' => 'E.g. "Daily site report check", "Purchasing mailbox".',
        'weekday' => 'Day the weekly work is expected; Monday when empty.',
        'month_day' => 'Day the monthly work is expected; first workday when empty.',
        'ends_on' => 'Open-ended when empty.',
        'is_active' => 'When off no period work opens; the definition and history stay.',
        'description' => 'Written into the note of the board item.',
        'compliance' => 'Last eight periods, oldest first: ✓ on time, ◔ late, ✗ not done, ○ running.',
    ],

    'values' => [
        'active' => 'Active',
        'passive' => 'Inactive',
        'weekday_default' => 'Monday',
        'every_workday' => 'Every weekday',
        'every_weekday' => 'Every :day',
        'every_month_day' => 'Day :day of every month',
        'every_month_first' => 'First workday of the month',
        'no_period' => 'No period yet',
    ],

    'weekdays' => [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ],

    'period' => [
        'week' => ':year / week :week',
    ],

    'states' => [
        'on_time' => 'On time',
        'late' => 'Late',
        'missed' => 'Not done',
        'open' => 'Running',
    ],

    'filters' => [
        'archive' => 'Archive',
        'archive_active' => 'Active',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
    ],

    'actions' => [
        'create' => 'New duty',
        'archive' => 'Archive',
        'unarchive' => 'Unarchive',
        'open_list' => 'Duties and responsibilities',
    ],

    'occurrences' => [
        'title' => 'Period work',
        'help' => 'The work opened on the owner\'s board every period and whether it was done.',
        'period' => 'Period',
        'done_on' => 'Completed',
        'empty' => 'No period work yet',
    ],
];
