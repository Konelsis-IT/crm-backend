<?php

declare(strict_types=1);

return [
    'actions' => [
        'enable' => 'Turn on desktop notifications',
    ],
    'messages' => [
        'enabled' => 'Desktop notifications are on. New notifications and messages will show a Windows notification and play a sound.',
        'denied' => 'The browser denied notification permission. Allow notifications for this site from the lock icon in the address bar.',
        'unsupported' => 'This browser does not support desktop notifications or the connection is not secure (HTTPS is required). The notification sound still plays.',
    ],
];
