<?php

// D-192 (B53): Operations - Purchasing > Purchase requests (itemized request).
// Shared item list texts (Add item, Bulk add items, Collapse / expand all,
// badges) live under procurement.php `lines`.
return [
    'label' => 'Purchase request',
    'plural' => 'Purchase requests',
    'navigation' => 'Purchase requests',

    'tabs' => [
        'all' => 'All',
        'mine' => 'My requests',
        'open' => 'Open',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'sections' => [
        'request' => 'Request',
        'lines' => 'Items',
        'reasons' => 'Justification and notes',
    ],

    'fields' => [
        'request_no' => 'Code',
        'title' => 'Request',
        'requested_by' => 'Requested by',
        'org_unit' => 'Department',
        'usage_area' => 'Place of use',
        'project' => 'Project',
        'needed_by' => 'Due date',
        'is_urgent' => 'Urgent',
        'justification' => 'Justification',
        'existing_check' => 'Why can the existing one not be used?',
        'note' => 'Note',
        'status' => 'Status',
        'lines_count' => 'Items',
        'created_at' => 'Opened',
        'archived_at' => 'Archived',
        'reason' => 'Reason',
        'catalog_item' => 'Product / service',
        'not_in_list' => 'Not in the list',
        'requested_item_name' => 'Product / service name',
        'brand_model' => 'Brand / model',
        'specification' => 'Requested specification',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'line_note' => 'Item note',
    ],

    'help' => [
        'usage_area' => 'Completed in-house requests are written to In-house purchases.',
        // D-230
        'project' => 'Required for field requests; for in-house requests choose it if the request belongs to a project.',
        'org_unit' => 'The requester\'s department; cannot be changed.',
        'justification' => 'Why is it needed? Why does the site or project want it?',
        'existing_check' => 'Why can the product / service the company already has not be used?',
        'catalog_item' => 'Pick it from the Materials and Services; if it is not there, switch on "Not in the list" and type its name.',
        'specification' => 'E.g. processor, memory, disk, screen for a computer; version and users for software.',
        'bulk_add' => 'Pick several materials / services from the list or paste one item per line: "name; quantity; unit". An item with the same name in the list is matched automatically.',
        'bulk_paste' => 'One item per line: name; quantity; unit (e.g. "A4 copy paper; 10; box").',
        'locked_line' => 'Purchasing has processed this item; it cannot be changed.',
        'cancel' => 'The request is cancelled together with its unprocessed items. A request with an ordered item cannot be cancelled.',
        'archive' => 'The request leaves the list; it is shown with the "Archive" filter and can be restored.',
        'restore' => 'The request appears in the list again.',
    ],

    'bulk' => [
        'items' => 'Materials / services from the list',
        'paste' => 'Paste',
    ],

    'lines' => [
        'no' => '#',
        'item' => 'Item',
        'quantity' => 'Quantity',
        'status' => 'Status',
        'quotation' => 'Quotation',
        'order' => 'Order',
        'processed' => 'Processed',
        'empty' => 'This request has no items.',
    ],

    'actions' => [
        'create' => 'Open purchase request',
        'edit' => 'Edit',
        'cancel' => 'Cancel request',
        'archive' => 'Archive',
        'restore' => 'Restore',
        'open' => 'Open request',
    ],

    'messages' => [
        'created' => 'Purchase request opened: :code',
        'cancelled' => 'Purchase request cancelled.',
        'archived' => 'Purchase request archived.',
        'restored' => 'Purchase request restored.',
        'bulk_added' => ':count items added.',
        'bulk_none' => 'No items to add were found.',
    ],

    'modals' => [
        'heading' => 'Purchase request',
        'description' => 'One request can hold several items. Each item reaches Operations - Purchasing › To be purchased; follow the request on the Purchase requests screen.',
    ],

    'filters' => [
        'archive' => 'Archive',
        'archive_active' => 'Active',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
        'urgent' => 'Urgent only',
    ],

    'values' => [
        'title_more' => ':items +:count more',
        'urgent' => 'Urgent',
        'normal' => 'Normal',
    ],

    'empty' => 'No purchase requests.',
];
