<?php

// D-232: Operations - Purchasing > Suppliers and service areas.
return [
    'label' => 'Supplier',
    'plural' => 'Suppliers',
    'nav' => 'Suppliers',
    'empty' => 'No suppliers.',

    'sections' => [
        'supplier' => 'Purchasing',
        'side' => 'Supplier',
    ],

    'fields' => [
        'name' => 'Name',
        'service_areas' => 'Service areas',
        'note' => 'Note',
        'shared' => 'Also in Parties',
        'contacts_count' => 'Contacts',
        'archived_at' => 'Archived on',
        'created_at' => 'Added',
    ],

    'help' => [
        'service_areas' => 'Areas the company serves; several can be chosen. New areas are added on the "Service areas" page.',
        'note' => 'Short info such as product note, city, contact person.',
    ],

    'values' => [
        'shared' => 'Also in Parties',
        'purchase_only' => 'Supplier only',
    ],

    'filters' => [
        'archive' => 'Archive',
        'archive_active' => 'Active',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
        'areas' => 'Service area',
        'shared' => 'Also in Parties',
    ],

    'actions' => [
        'create' => 'Add supplier',
        'archive' => 'Archive',
        'restore' => 'Restore',
        'areas' => 'Service areas',
        'open_party' => 'Open the Parties record',
    ],

    'modals' => [
        'archive_description' => 'The supplier leaves the list and the selects; nothing is deleted. The Parties record is not affected.',
        'restore_description' => 'The supplier shows in the list and the selects again.',
    ],

    'messages' => [
        'created' => 'Supplier added.',
        'saved' => 'Supplier saved.',
        'archived' => 'Supplier archived.',
        'restored' => 'Supplier restored.',
    ],

    'area' => [
        'label' => 'Service area',
        'plural' => 'Service areas',
        'empty' => 'No service areas.',
        'fields' => [
            'name' => 'Name',
            'sort_order' => 'Order',
            'suppliers_count' => 'Suppliers',
        ],
        'actions' => [
            'create' => 'Add service area',
            'edit' => 'Edit service area',
        ],
        'messages' => [
            'created' => 'Service area added.',
            'saved' => 'Service area saved.',
            'archived' => 'Service area archived.',
            'restored' => 'Service area restored.',
        ],
        'modals' => [
            'archive_description' => 'The area is not offered for new choices; suppliers keep it.',
            'restore_description' => 'The area can be chosen again.',
        ],
    ],
];
