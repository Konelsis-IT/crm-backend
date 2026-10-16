<?php

// D-189: Procurement > Company purchases screen.
return [
    'label' => 'Company purchase',
    'plural' => 'Company purchases',
    'navigation' => 'Company purchases',

    'outlook' => 'Renewal cost in the next 12 months: :amount · :count renewals coming up',

    'tabs' => [
        'all' => 'All',
        'upcoming' => 'Renewal coming up',
        'overdue' => 'Renewal overdue',
        'one_time' => 'One-time',
    ],

    'sections' => [
        'purchase' => 'Purchase',
        'amount' => 'Amount and renewal',
        'lines' => 'Lines',
    ],

    'fields' => [
        'request' => 'Purchase request',
        'catalog_item' => 'Catalog item',
        'description' => 'Description',
        'org_unit' => 'Department',
        'personnel' => 'Used by',
        'supplier' => 'Supplier',
        'supplier_party' => 'Supplier (registered company)',
        'supplier_name' => 'Supplier name',
        'purchased_on' => 'Purchase date',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit price',
        'currency' => 'Currency',
        'total' => 'Total',
        'renewal' => 'Renewal',
        'renewal_months' => 'Renewal period',
        'next_renewal_on' => 'Next renewal',
        'ends_on' => 'End date',
        'note' => 'Note',
        'need' => 'Order item',
        'request_line' => 'Request line',
        'request_lines' => 'Request lines',
        'purchase_request' => 'Request',
        'purchase_order' => 'Order',
    ],

    'help' => [
        'request' => 'The purchase is linked to a purchase request. Choosing the request fills the item, description, department, person, quantity, supplier and price.',
        'catalog_item' => 'In-house items (computers, printers, software, stationery) are listed first.',
        'supplier_name' => 'Type the name if the company is not registered.',
        'renewal_months' => 'Leave empty to use the catalog item\'s period.',
        'next_renewal_on' => 'Leave empty for purchase date + renewal period.',
        'renew' => 'Renewal done: the renewal of :current closes and the next renewal becomes :next.',
        'archive' => 'The record leaves the list; it is shown with the "Archive" filter and can be restored.',
        'restore' => 'The record appears in the list again.',
        'request_lines' => 'Company-internal purchase request. Selecting it fills department, person and the request lines; each line becomes a separate purchase record.',
        'bulk_add' => 'Adds the lines of the selected request that are not in the list yet.',
    ],

    'values' => [
        'months' => 'months',
        'every_months' => 'Every :months months',
        'one_time' => 'One-time',
    ],

    'actions' => [
        'create' => 'Add purchase',
        'renew' => 'Renewed',
        'archive' => 'Archive',
        'restore' => 'Restore',
    ],

    'messages' => [
        'renewed' => 'Renewal recorded; the next renewal date moved forward.',
        'archived' => 'Company purchase archived.',
        'restored' => 'Company purchase restored.',
        'created_lines' => ':count company purchases saved.',
    ],

    'filters' => [
        'archive' => 'Archive',
        'archive_active' => 'Active',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
    ],

    'empty' => 'No company purchases.',
];
