<?php

return [
    'label' => 'Announcement',
    'plural' => 'Announcements',

    'actions' => [
        'send' => 'Send notification',
        'submit' => 'Send',
        'open' => 'Open',
        'read' => 'Read',
        'close' => 'Close',
    ],

    'fields' => [
        'audience_kind' => 'To',
        'department' => 'Department',
        'role' => 'Role',
        'personnel' => 'Personnel',
        'priority' => 'Priority',
        'title' => 'Title',
        'body' => 'Message',
        'action_url' => 'Link (optional)',
        'sent_at' => 'Sent',
        'sender' => 'Sender',
        'audience' => 'Audience',
        'recipient_count' => 'Recipients',
    ],

    'help' => [
        'send' => 'Everyone in the selected audience receives it in their notification bell. Only audiences you are allowed to notify are listed.',
        'action_url' => 'You may add an in-app page link; it appears as an "Open" button in the notification.',
    ],

    'audiences' => [
        'team_of' => "Team of :name",
        'department_of' => ':name department',
        'role_of' => ':name role',
        'personnel_count' => ':count people',
        'all' => 'Everyone',
    ],

    'notifications' => [
        'body' => ':sender: :body',
    ],

    'messages' => [
        'sent' => 'The notification was sent to :count people.',
    ],

    'widget' => [
        'heading' => 'Announcements',
        'description' => 'Sent notifications and announcements; newest first.',
        'empty' => 'No announcements yet.',
    ],
];
