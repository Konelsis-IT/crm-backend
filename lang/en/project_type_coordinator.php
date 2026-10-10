<?php

declare(strict_types=1);

// Project type coordinators (B49, D-175).
return [
    'label' => 'Project type coordinator',
    'plural' => 'Project type coordinators',
    'nav' => 'Project type coordinators',
    'title' => 'Project type coordinators',
    'subheading' => 'A coordinator looks after every project of the project type, independently of the project managers. Each type has one coordinator.',

    'columns' => [
        'type' => 'Project type',
        'coordinator' => 'Coordinator',
        'since' => 'Assigned on',
        'project_count' => 'Projects',
    ],

    'fields' => [
        'personnel' => 'Coordinator',
        'project_coordinators' => 'Project type coordinators',
        'project_coordinator' => 'Project type coordinator',
        'personnel_roles' => 'Project type coordinator of',
    ],

    'help' => [
        'personnel' => 'Only active people can be selected. The previous coordinator\'s assignment stays in the history.',
    ],

    'values' => [
        'none' => 'Not assigned',
        'badge' => ':type coordinator',
        'project_item' => ':type: :name',
        'projects' => ':count projects',
    ],

    'actions' => [
        'assign' => 'Assign coordinator',
        'change' => 'Change coordinator',
        'remove' => 'Remove coordinator',
    ],

    'modals' => [
        'assign_heading' => ':type coordinator',
        'remove_heading' => 'Remove the :type coordinator',
        'remove_description' => ':name is removed as coordinator of this type; the assignment stays in the history.',
    ],

    'messages' => [
        'assigned' => 'Coordinator assigned.',
        'removed' => 'Coordinator removed.',
    ],

    'empty' => 'No project types found.',
];
