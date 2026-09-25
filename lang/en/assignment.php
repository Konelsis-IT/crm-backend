<?php

return [
    'fields' => [
        'department' => 'Department',
        'job_title' => 'Job title',
        'direct_manager' => 'Direct manager',
        'effective_from' => 'From',
        'effective_to' => 'To',
    ],

    'messages' => [
        'ongoing' => 'Ongoing',
    ],

    'relation' => [
        'title' => 'Assignment history',
        'help' => 'Org unit and job title changes; newest first.',
        'empty' => 'No manager recorded yet.',
    ],

    'reporting' => [
        'kind' => 'Manager type',
        'add' => 'Add another manager',
        'close' => 'Close',
        'kind_help' => 'There is one line manager; further managers are added as functional or project managers.',
        'title' => 'Manager history',
        'help' => 'There is one line manager; a person may report to several managers, added as functional or project managers.',
        'empty' => 'No manager recorded yet.',
        'manager' => 'Manager',
    ],
];
