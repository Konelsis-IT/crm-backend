<?php

// Urgent warning, department call and department warning from the dashboard (D-208, 14 October 2026).
return [
    'item' => [
        'title' => 'Urgent: :subject',
        'body_fixed' => ':sender marked this as urgent.',
        'body_custom' => ':sender: :message',
    ],
    'call' => [
        'title' => ':sender is calling you',
        'body' => 'The :department department was called by :sender.',
    ],
    'department' => [
        'title' => ':department: warning about pending work',
        'body' => ':topic: :count records are waiting. Sent by :sender.',
        'body_oldest' => ':topic: :count records are waiting, the oldest for :days days. Sent by :sender.',
    ],
    'note' => 'Note: :note',
    'actions' => [
        'open_record' => 'Open record',
        'open_list' => 'Open list',
    ],
];
