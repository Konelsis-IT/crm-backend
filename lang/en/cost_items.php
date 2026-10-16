<?php

// D-189: Cost items tab on the proposal page (CostItemsRelationManager).
return [
    'relation' => [
        'title' => 'Cost items',
        'empty' => 'No cost items have been imported into the current proposal version.',
        'empty_description' => 'Use "Import from Excel" to split the cost list uploaded to the proposal scope into items.',
    ],

    'tabs' => [
        'product_service' => 'Product/Service',
        'admin_staff' => 'Administrative staff',
        'general_expense' => 'General expenses',
    ],

    'summary' => ':count items · :matched in catalog · :new new · :similar similar (awaiting choice) · Total cost :total · Price not settled :open',

    'fields' => [
        'line_no' => 'No',
        'description' => 'Item',
        'match' => 'Catalog',
        'quantity' => 'Quantity',
        'unit_cost' => 'Unit cost',
        'total_cost' => 'Total cost',
        'total_sale' => 'Total sale',
        'price_state' => 'Price state',
        'purchase_status' => 'Purchasing',
    ],

    'match' => [
        'similar' => 'Similar: :name?',
        'new' => 'New: :name',
    ],

    'filters' => [
        'with_cost' => 'With amount',
        'price_state' => 'Price state',
        'match' => 'Catalog match',
        'not_sent' => 'Not sent to purchasing',
    ],

    'actions' => [
        'import' => 'Import from Excel',
        'match' => 'Match',
        'send' => 'Send to purchasing',
    ],

    'import' => [
        'document' => 'Cost list',
        'description' => 'The cost list uploaded to the proposal scope is split into items. Each item is matched to the existing catalog item; a missing one is added once, a very similar one waits for your choice.',
    ],

    'match_form' => [
        'description' => 'Link the Excel item to a catalog item. This spelling becomes a matching name and matches automatically on later uploads.',
        'line' => 'Item in Excel',
        'catalog_item' => 'Catalog item',
        'as_new' => 'Add as a new catalog item',
    ],

    'send_form' => [
        'description' => 'The selected items become order items and reach purchasing after project approval. Items without an amount, without a catalog match or already sent are skipped.',
        'needed_by' => 'Needed by',
        'is_urgent' => 'Urgent',
        'note' => 'Note',
    ],

    'messages' => [
        'imported' => ':count items imported: :matched matched in the catalog, :new new, :similar similar',
        'matched' => 'Item linked to the catalog.',
        'sent' => ':created items sent to purchasing (awaiting project approval); unmatched :unmatched, skipped :skipped',
    ],
];
