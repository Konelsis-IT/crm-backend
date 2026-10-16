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

    // D-196: the project page reads the purchasing records (ProjectPurchasing).
    'purchasing' => [
        'needs_title' => 'Purchasing',
        'needs_empty' => 'No purchase requests for this project.',
        'orders_title' => 'Logistics',
        'orders_empty' => 'This project has no orders.',
        'software_title' => 'Software',
        'software_empty' => 'No software items for this project.',
    ],

    'legacy' => [
        'title' => 'Earlier supply records',
        'description' => 'Records entered before the purchasing module; view only.',
    ],
];
