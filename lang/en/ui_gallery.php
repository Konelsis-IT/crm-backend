<?php

return [
    'nav' => 'UI Preview',
    'title' => 'Card design preview',

    'pages' => [
        'cards' => 'Card design',
        'plugin' => 'Plugin: Filament Cards',
    ],

    'plugin' => [
        'title' => 'Plugin trial: Filament Cards',
        'subheading' => 'harvirsidhu/filament-cards (MIT). Its real purpose is a card "hub" for page/resource navigation; here the same cards are also filled with document records for comparison.',
        'groups' => [
            'documents' => 'Document records (one card per document)',
            'documents_help' => 'Card = title, subtitle, icon, status badge and link. No field list inside the card; clicking opens the document detail.',
            'hub' => 'Documents cluster (navigation cards — the plugin\'s real purpose)',
            'hub_help' => 'Resource classes are given; label, icon and link come from Filament navigation automatically.',
        ],
        'search' => 'Search cards…',
        'values' => [
            'no_revision' => 'No revision',
        ],
    ],
    'subheading' => 'A shared record card for projects, personnel, documents and, later, servers. The chosen design will be moved into those lists; this page is not part of any workflow.',

    'tabs' => [
        'schema' => 'A · Schema cards',
        'table' => 'B · Table cards (search, filters, pagination)',
        'list' => 'C · Schema cards + search and pagination',
    ],

    'list' => [
        'source' => 'Source',
        'search' => 'Search cards…',
    ],

    'variants' => [
        'cover' => 'Cover',
        'row' => 'Row',
    ],

    'sections' => [
        'projects' => 'Projects',
        'personnel' => 'Personnel',
        'documents' => 'Documents',
        'servers' => 'Servers',
    ],

    'help' => [
        'intro' => 'Switch the layout with the "Cover / Row" buttons above. Every card shares one anatomy: media · title and subtitle · status at the top right · badge row · icon entries · actions at the bottom.',
        'schema' => 'Cards built with Filament schema components. Free layout; same language as the project/document detail cards. No search or pagination on this tab (first 8 records).',
        'table' => 'The same anatomy with the Filament table card grid. Search, filters, sorting, pagination and row actions come from the table itself; this is how lists would switch between table and cards.',
        'list' => 'The cover card from tab A as lists will use it: search box, page size and pagination built with schema components (no table). This is the default view of the Personnel, Documents and Projects lists; the icon button in the header switches to the table. Search, page and page size travel in the address bar.',
        'servers_mock' => 'There is no server module yet; sample data shows the card anatomy.',
    ],

    'entries' => [
        'address' => 'Address',
        'channels' => 'Contact',
        'contact' => 'Contact person',
        'manager' => 'Project manager',
        'city' => 'City',
        'dates' => 'Plan',
        'value' => 'Contract value',
        'phone' => 'Phone',
        'email' => 'E-mail',
        'hired_on' => 'Hired on',
        'last_login' => 'Last login',
        'owner' => 'Owner',
        'project' => 'Project',
        'file' => 'File',
        'updated' => 'Updated',
        'disk' => 'Disk',
        'uptime' => 'Uptime',
        'last_check' => 'Last check',
        'environment' => 'Environment',
        'location' => 'Location',
    ],

    'values' => [
        'no_focus' => 'No focus',
        'no_channels' => 'No contact details',
        'steps_ready' => ':ready / :total steps ready',
        'no_title' => 'No job title',
        'no_cover' => 'No cover image',
        'empty' => 'No projects to show.',
        'server_status' => [
            'running' => 'Running',
            'warning' => 'Warning',
            'down' => 'Unreachable',
        ],
        'environment' => [
            'production' => 'Production',
            'test' => 'Test',
        ],
    ],

    'actions' => [
        'open' => 'Open',
        'edit' => 'Edit',
        'details' => 'Details',
    ],
];
