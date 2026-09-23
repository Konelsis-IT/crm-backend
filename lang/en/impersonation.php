<?php

declare(strict_types=1);

/*
 * Switching personnel (D-120): visible only on the hidden system account.
 */
return [
    'actions' => [
        'switch' => 'Switch personnel',
        'submit' => 'Switch to this person',
        'stop' => 'Back to my account',
    ],

    'modal' => [
        'heading' => 'Switch personnel',
        'description' => 'You see the panel as the selected person; records you write are stored under your own account.',
    ],

    'fields' => [
        'personnel' => 'Personnel',
    ],

    'messages' => [
        'failed' => 'Could not switch to this person.',
        'started' => 'You are now seeing the panel as :name.',
        'stopped' => 'You are back on your own account.',
    ],
];
