<?php

declare(strict_types=1);

return [
    'singular' => 'Notification',
    'plural' => 'All notifications',
    'help' => 'Every notification sent to you, older ones included. Clicking a row opens the related page and marks the notification as read.',
    'help_all' => 'Notifications sent to all personnel, older ones included. The "Recipient" column shows who received each one; pick a person in the filter. Clicking a row opens the related page; someone else\'s notification is not marked as read.',
    'open_all' => 'See all notifications',
    'empty' => 'You have no notifications yet',
    'empty_all' => 'No notifications yet',
    'fields' => [
        'status' => 'Status',
        'recipient' => 'Recipient',
        'title' => 'Title',
        'body' => 'Content',
        'created_at' => 'Date',
    ],
    'status' => [
        'read' => 'Read',
        'unread' => 'Unread',
    ],
    'filters' => [
        'read' => 'Read status',
        'recipient' => 'Recipient',
    ],
    'tabs' => [
        'all' => 'All',
        'unread' => 'Unread',
    ],
    'actions' => [
        'open' => 'Open',
        'mark_read' => 'Mark as read',
        'mark_unread' => 'Mark as unread',
        'mark_all_read' => 'Mark all as read',
    ],
];
