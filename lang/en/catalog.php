<?php

// D-189: Procurement > Catalog and Catalog categories screens
// (CatalogItemResource, CatalogCategoryResource).
return [
    // D-192: menu name "Materials and Services" (formerly "Catalog").
    'nav' => 'Materials and Services',
    'label' => 'Material / service',
    'plural' => 'Materials and Services',

    'tabs' => [
        'all' => 'All',
    ],

    'sections' => [
        'main' => 'Item details',
        'purchase' => 'Purchasing',
        'general' => 'General',
        'orders' => 'Orders',
    ],

    'fields' => [
        'kind' => 'Type',
        'usage_area' => 'Place of use',
        'category' => 'Category',
        'name' => 'Name',
        'unit' => 'Unit',
        'currency' => 'Currency',
        'brand' => 'Brand',
        'requires_approval' => 'Order approval',
        'renewal_months' => 'Renewal',
        'specification' => 'Specification',
        'aliases' => 'Excel matching names',
        'cost_lines_count' => 'Proposal lines',
        'needs_count' => 'To be purchased',
        'archived_at' => 'Archived on',
    ],

    'help' => [
        'usage_area' => 'Where the item is mainly used; it also shows in the In-house and Site tabs.',
        'requires_approval' => 'When off, the order completes without approval',
        'renewal_months' => 'Recurring items such as servers, subscriptions, stationery',
        'aliases' => 'Names written differently in Excel are linked to this item and match automatically on the next upload.',
        'archive' => 'The item is archived and hidden from lists and selects. Proposal and order links are kept.',
        'restore' => 'The item shows again in lists and selects.',
    ],

    'values' => [
        'approval_required' => 'Approval required',
        'approval_not_required' => 'No approval',
        'renewal' => 'Every :n months',
        'months_suffix' => 'months',
        'no_aliases' => 'No matching names',
    ],

    'orders' => [
        'summary' => ':count items to purchase · best unit price :price',
        'summary_no_quote' => ':count items to purchase · no supplier quote yet',
        'none' => 'No item to purchase has been opened for this material / service yet.',
    ],

    'relations' => [
        'cost_lines' => [
            'title' => 'Proposals and projects',
            'empty' => 'No proposal cost line requests this item.',
            'proposal' => 'Proposal',
            'project' => 'Project',
            'path' => 'Place in Excel',
            'quantity' => 'Quantity',
            'unit_cost' => 'Unit cost',
            'total_cost' => 'Total cost',
            'price_state' => 'Price state',
            'purchase_status' => 'Purchasing',
            'not_sent' => 'Not sent',
        ],
        'needs' => [
            'title' => 'To be purchased',
            'empty' => 'No items to purchase for this material / service.',
            'need' => 'Item to purchase',
            'status' => 'Status',
            'proposal' => 'Proposal',
            'project' => 'Project',
            'quantity' => 'Quantity',
            'recommended' => 'Recommended quote',
            'needed_by' => 'Needed by',
        ],
        // D-194: the item's prices in every purchase quotation.
        'price_history' => [
            'title' => 'Price history',
            'empty' => 'No priced purchase quotation for this material / service yet.',
            'quote_date' => 'Quote date',
            'supplier' => 'Supplier',
            'quotation' => 'Quotation no',
            'quantity' => 'Quantity',
            'unit_price' => 'Unit price',
            'unit_price_try' => 'In TRY',
            'unit_price_try_help' => 'The discounted unit price in TRY (at the quotation\'s rate)',
            'discount' => 'Discount',
            'status' => 'Quotation status',
            'order' => 'Order no',
            'summary' => [
                'lowest' => 'Lowest: :price',
                'last' => 'Latest: :price',
                'average' => 'Average: :price',
                'count' => ':count prices',
                'no_rate' => 'Quotations without a TRY rate are left out of the summary',
            ],
            'badges' => [
                'option' => 'Option',
                'cancelled' => 'Cancelled',
            ],
            'filters' => [
                'supplier' => 'Supplier',
                'status' => 'Quotation status',
            ],
        ],
    ],

    'filters' => [
        'archive' => 'Archive',
        'archive_active' => 'Active',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
        'kind' => 'Type',
        'category' => 'Category',
    ],

    'actions' => [
        'create' => 'Add material / service',
        'archive' => 'Archive',
        'restore' => 'Restore from archive',
    ],

    'messages' => [
        'archived' => 'Item archived.',
        'restored' => 'Item restored from archive.',
    ],

    'empty' => 'The list has no materials / services.',

    // Categories (CatalogCategoryResource; formerly "Catalog categories", D-192)
    'category' => [
        'nav' => 'Categories',
        'label' => 'Material / service category',
        'plural' => 'Categories',
        'empty' => 'No categories of this type.',
        'tabs' => [
            'product' => 'Product / Service',
        ],
        'fields' => [
            'kind' => 'Type',
            'parent' => 'Parent category',
            'name' => 'Name',
            'path' => 'Location',
            'items_count' => 'Items',
        ],
        'help' => [
            'parent' => 'Leave empty for a main category.',
            'kind' => 'Products and services share the same categories.',
            'archive' => 'The category is archived and hidden from selects. Its items do not change.',
            'restore' => 'The category shows again in selects.',
        ],
        'actions' => [
            'create' => 'Add category',
            'edit' => 'Edit category',
        ],
        'messages' => [
            'created' => 'Category added.',
            'saved' => 'Category saved.',
            'archived' => 'Category archived.',
            'restored' => 'Category restored from archive.',
        ],
    ],
];
