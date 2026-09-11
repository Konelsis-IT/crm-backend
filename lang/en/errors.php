<?php

return [
    'kinds' => [
        'error' => 'Error',
        'not_found' => 'Not found',
        'forbidden' => 'Access denied',
        'auth' => 'Sign-in required',
        'expired' => 'Session expired',
        'throttle' => 'Too many requests',
        'server' => 'Server error',
        'maintenance' => 'Under maintenance',
        'client' => 'Request error',
    ],

    '401' => [
        'title' => 'You need to sign in',
        'message' => 'Sign in with your Konelsis account to view this page.',
    ],
    '403' => [
        'title' => 'You are not allowed to view this page',
        'message' => 'Your role does not cover this record or action. Ask your manager for access if you believe you need it.',
    ],
    '404' => [
        'title' => 'The page could not be found',
        'message' => 'The link may be outdated or the record may have moved. Use the menu to reach the section again.',
    ],
    '419' => [
        'title' => 'The page timed out',
        'message' => 'Your session ended after a long period of inactivity. Sign in again and repeat the action.',
    ],
    '429' => [
        'title' => 'Let us slow down a little',
        'message' => 'Too many requests were sent in a short time. Wait a few seconds and try again.',
    ],
    '500' => [
        'title' => 'An unexpected error occurred',
        'message' => 'Nothing was saved. The problem has been logged; tell the system administrator if it persists.',
    ],
    '503' => [
        'title' => 'Konelsis is briefly under maintenance',
        'message' => 'A planned update is in progress. Try again in a few minutes; your data is safe.',
    ],
    '4xx' => [
        'title' => 'The request could not be processed',
        'message' => 'The request was not understood or is invalid. Refresh the page and try again.',
    ],
    '5xx' => [
        'title' => 'The server cannot respond right now',
        'message' => 'A temporary problem occurred. Try again shortly.',
    ],

    'actions' => [
        'back' => 'Go back',
        'home' => 'Go to the panel',
        'login' => 'Sign in',
        'retry' => 'Try again',
    ],

    'footer' => [
        'help' => 'If the problem persists, send this page code to your system administrator.',
        'signed_in_as' => 'Signed in as :name',
    ],
];
