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
        'empty' => 'No change recorded yet.',
    ],

    'reporting' => [
        'title' => 'Reporting history',
        'help' => 'Direct manager changes; newest first.',
        'empty' => 'No change recorded yet.',
        'manager' => 'Direct manager',
    ],
];
