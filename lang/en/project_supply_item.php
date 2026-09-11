<?php

return [
    'label' => 'Supply item',
    'plural' => 'Supply items',

    'sections' => [
        'main' => 'Item details',
        'commercial' => 'Quantity and cost',
        'dates' => 'Dates and status',
    ],

    'fields' => [
        'project' => 'Project',
        'workstream' => 'Step (department)',
        'item_kind' => 'Kind',
        'item_code' => 'Item code',
        'name' => 'Name',
        'specification' => 'Specification / description',
        'quantity' => 'Quantity',
        'uom' => 'Unit',
        'unit_cost' => 'Unit cost',
        'currency' => 'Currency',
        'total_cost' => 'Total cost',
        'supplier' => 'Supplier',
        'wbs_node' => 'WBS node',
        'needed_on' => 'Needed on',
        'ordered_on' => 'Ordered on',
        'expected_delivery_on' => 'Expected delivery',
        'delivered_on' => 'Delivered on',
        'status' => 'Status',
        'note' => 'Note',
        'created_at' => 'Created at',
        'reason' => 'Reason',
    ],

    'help' => [
        'item_code' => 'Free-form code until the catalogue arrives (e.g. PV-550, INV-1500).',
        'currency' => 'Defaults to the project currency when left empty.',
    ],

    'relation' => [
        'title' => 'Supply items',
        'empty' => 'No supply items yet.',
        'logistics_title' => 'Shipping and delivery',
        'software_title' => 'Software / automation items',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'set_status' => 'Set status to \":status\"',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
    ],

    'validation' => [
        'duplicate' => 'This record already exists.',
    ],

    'filters' => [
        'my_step' => 'Projects currently focused on procurement',
    ],
];
