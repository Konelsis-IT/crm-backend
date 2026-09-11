<?php

return [
    'label' => 'Training',
    'plural' => 'Trainings',

    'sections' => [
        'main' => 'Training details',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Training name',
        'training_kind' => 'Kind',
        'provider' => 'Provider',
        'planned_on' => 'Planned date',
        'duration_hours' => 'Duration (hours)',
        'attendee_count' => 'Attendee count',
        'status' => 'Status',
        'attended_on' => 'Attended on',
        'outcome' => 'Outcome',
        'score' => 'Score',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This personnel is already registered for this training.',
    ],

    'relation' => [
        'title' => 'Trainings',
        'empty' => 'No training attendance recorded.',
    ],
];
