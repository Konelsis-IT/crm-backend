<?php

return [
    'label' => 'Announcement',
    'plural' => 'Announcements',

    'actions' => [
        'send' => 'Send announcement',
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
        'send' => 'The announcement reaches the notification bell of everyone in the selected audience and appears under Announcements on the overview. Only audiences you are allowed to notify are listed.',
        'action_url' => 'You may add an in-app page link; it appears as "Open" in the bell notification and as "Open link" under Announcements.',
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
        'sent' => 'The announcement was sent to :count people.',
    ],

    'widget' => [
        'heading' => 'Announcements',
        'description' => 'Sent announcements; newest first.',
        'empty' => 'No announcements yet.',
        'open_link' => 'Open link',
        'show_all' => 'See all',
    ],
];
