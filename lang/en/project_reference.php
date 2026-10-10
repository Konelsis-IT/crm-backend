<?php

// References (B50, D-177).
return [
    'label' => 'Reference',
    'plural' => 'References',

    'fields' => [
        'title' => 'Reference',
        'scope_types' => 'Project type',
        'sort_order' => 'Order',
        'archived_at' => 'Archived at',
    ],

    'help' => [
        'title' => 'The text of the completed work as it appears in the list (for example "METGÜN ENERJİ ELBİSTAN SOLAR POWER PLANT COMPLETE EPC SYSTEMS (50MW)").',
        'scope_types' => 'Project types the reference belongs to; several can be selected.',
        'archive' => 'The reference leaves the lists and the Excel file; choose Archived in the "Archive" filter to see and restore it.',
        'restore' => 'The reference appears in the lists and the Excel file again.',
    ],

    'filters' => [
        'scope_type' => 'Project type',
        'archive' => 'Archive',
        'archive_active' => 'Active',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
    ],

    'actions' => [
        'create' => 'Add reference',
        'edit' => 'Edit',
        'archive' => 'Archive',
        'restore' => 'Restore from archive',
        'excel' => 'Excel',
        'download' => 'Download',
        'open_list' => 'Open reference list',
        'close' => 'Close',
        'references' => 'References',
        'download_type' => 'Download references',
    ],

    'tabs' => [
        'all' => 'All',
    ],

    'messages' => [
        'created' => 'Reference added.',
        'saved' => 'Reference saved.',
        'archived' => 'Reference archived.',
        'restored' => 'Reference restored from archive.',
    ],

    'list' => [
        'modal_heading' => 'Reference list',
        'modal_heading_type' => ':type references',
        'modal_description' => 'References of this project type. You can search, filter, download to Excel and add a new reference.',
        'table_heading' => 'References',
    ],

    'empty' => 'No references found.',
];
