<?php

return [
    'label' => 'Share link',
    'plural' => 'Shares',

    'sections' => [
        'main' => 'Share details',
    ],

    'fields' => [
        'label' => 'Label',
        'url' => 'Link',
        'status' => 'Status',
        'allow_download' => 'Allow download',
        'expires_at' => 'Expires at',
        'access_count' => 'Opens',
        'last_accessed_at' => 'Last opened',
        'created_by' => 'Created by',
        'created_at' => 'Created',
    ],

    'help' => [
        'label' => 'Who/why it was sent to (e.g. "Sent to customer").',
        'expires_at' => 'Leave blank to keep the link open until it is revoked.',
        'relation' => 'Anyone with the link can view the document; revoke links that are no longer needed.',
    ],

    'values' => [
        'no_expiry' => 'No expiry',
    ],

    'actions' => [
        'revoke' => 'Revoke link',
    ],

    'messages' => [
        'copied' => 'Link copied.',
        'revoked' => 'Share link revoked.',
        'empty' => 'No share links yet.',
    ],
];
