<?php

return [
    'label' => 'Project scope',
    'plural' => 'Project scopes',

    'sections' => [
        'ges' => 'GES scope',
        'res' => 'RES scope',
        'tm' => 'TM scope',
        'hes' => 'HES scope',
        'bes' => 'BESS scope',
        'enh_eih' => 'ENH/EIH scope',
        'automation' => 'Automation / Process scope',
    ],

    'labels' => [
        'tm' => [
            'total_cost' => 'Total cost',
            'total_sales' => 'Total sales',
            'unit_cost' => 'Cost/feeder',
        ],
        'bes' => [
            'power_mwe' => 'MWe',
            'energy_mwh' => 'MWh',
            'unit_cost' => 'BESS cost/MWh',
            'total_cost' => 'Total cost',
            'unit_sales' => 'BESS sales/MWh',
            'total_sales' => 'Total sales',
        ],
        'hes' => [
            'total_cost' => 'Cost',
            'total_sales' => 'Sales',
            'unit_cost' => 'Cost/generator-turbine',
        ],
        'ges' => [
            'capacity_mwp' => 'MWp',
            'unit_cost' => 'Solar cost/MWp',
            'total_cost' => 'Total cost',
            'unit_sales' => 'Solar sales/MWp',
            'total_sales' => 'Total sales amount',
        ],
        'enh_eih' => [
            'length_km' => 'Km',
            'unit_cost' => 'Line cost/km',
            'total_cost' => 'Total cost',
            'unit_sales' => 'Line sales/km',
            'total_sales' => 'Total sales',
        ],
        'res' => [
            'res_material_amount' => 'Respark material',
            'res_construction_amount' => 'Respark construction',
            'res_assembly_amount' => 'Respark assembly',
        ],
        'automation' => [
            'total_cost' => 'Total cost',
            'total_sales' => 'Total sales',
        ],
    ],

    'fields' => [
        'capacity_mw' => 'Installed capacity (MW)',
        'cost_amount' => 'Cost',
        'sales_amount' => 'Sales',
        'cost_per_mw' => 'Cost per MW',
        'sales_per_mw' => 'Sales per MW',
        'total_sales' => 'Total sales',
        'res_material_amount' => 'Respark material',
        'res_construction_amount' => 'Respark construction',
        'res_assembly_amount' => 'Respark assembly',
        'total' => 'Total',
        'tm_total_cost' => 'Total cost',
        'tm_total_sales' => 'Total sales',
        'tm_feeder_cost' => 'Cost per feeder',
        'hes_unit_cost' => 'Cost per generator/turbine',
        'scope_file' => 'Scope list (Excel)',
        'current_file' => 'Uploaded scope list',
        'scope_document' => 'Scope list',
        'cost_files' => 'Cost list',
        'current_cost_files' => 'Uploaded cost lists',
        'note' => 'Note',
    ],

    'help' => [
        'fields_later' => 'Fields for this type will be defined later; for now you can upload the scope list.',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'set_status' => 'Set status to ":status"',
        'select' => 'Mark as selected',
        'submit' => 'Submit for review',
        'review' => 'Record review decision',
        'publish' => 'Publish',
        'approve' => 'Approve',
        'change_focus' => 'Change focus',
        'waive' => 'Grant waiver',
        'add_evidence' => 'Add evidence',
        'accept' => 'Accept',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
