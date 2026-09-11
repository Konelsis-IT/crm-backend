<?php

return [
    'label' => 'Revision',
    'plural' => 'Revisions',

    'sections' => [
        'main' => 'Revision details',
        'content' => 'Content',
    ],

    'fields' => [
        'revision_no' => 'Revision no',
        'revision_code' => 'Revision code',
        'language' => 'Language',
        'title' => 'Title',
        'purpose' => 'Purpose',
        'status' => 'Status',
        'content_kind' => 'Content source',
        'body' => 'Document text',
        'change_summary' => 'Change summary',
        'file' => 'File',
        'file_size' => 'Size',
        'mime_type' => 'Type',
        'thumbnail' => 'Image',
        'preparer' => 'Prepared by',
        'prepared_at' => 'Prepared at',
        'checker' => 'Checked by',
        'approver' => 'Approved by',
        'approved_at' => 'Approved at',
        'issued_at' => 'Issued at',
    ],

    'help' => [
        'revision_no' => 'Generated automatically.',
        'file' => 'The original file for this revision. If identical content was uploaded before, it is linked to that file automatically.',
        'content_kind' => 'Upload the original as a file or write the document here.',
        'body' => 'Document text; headings, lists and tables are available. A new content hash is generated when it changes.',
    ],

    'values' => [
        'authored' => 'Written in system',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'download' => 'Download',
        'preview' => 'Preview',
        'set_status' => 'Set status to ":status"',
    ],

    'messages' => [
        'status_changed' => 'Revision status updated.',
    ],
];
