<?php

return [
    'label' => 'Site photo',
    'plural' => 'Site photos',

    'sections' => [
        'main' => 'Photo details',
    ],

    'fields' => [
        'file' => 'Photo file',
        'files' => 'Photo files',
        'caption' => 'Caption',
        'taken_on' => 'Taken on',
        'sort_order' => 'Order',
        'is_cover' => 'Cover image',
        'workstream' => 'Step (department)',
        'uploaded_at' => 'Uploaded at',
        'uploader' => 'Uploaded by',
        'created_at' => 'Created at',
        'reason' => 'Reason',
    ],

    'help' => [
        'files' => 'You may select several photos; the first one becomes the cover image automatically.',
        'is_cover' => 'When checked this photo is shown on the project card and at the top of the workspace.',
    ],

    'relation' => [
        'title' => 'Site photos',
        'empty' => 'No photos yet.',
    ],

    'actions' => [
        'upload' => 'Add photos',
        'set_cover' => 'Make cover',
        'change_status' => 'Change status',
        'download' => 'Download',
        'preview' => 'Preview',
        'set_status' => 'Set status to \":status\"',
    ],

    'messages' => [
        'done' => 'Done.',
        'uploaded' => ':count photo(s) added.',
        'status_changed' => 'Status updated.',
    ],

    'validation' => [
        'duplicate' => 'This photo is already attached.',
    ],
];
