<?php

return [
    'label' => 'Project scope',
    'plural' => 'Project scopes',

    'sections' => [
        'ges' => 'GES scope',
        'res' => 'RES scope',
        'tm' => 'TM scope',
        'hes' => 'HES scope',
        'bes' => 'BES scope',
        'enh_eih' => 'ENH/EIH scope',
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
        'note' => 'Note',
    ],

    'help' => [
        'scope_file' => 'The scope list prepared for this project type (.xlsx/.xls/.csv). A new upload is added as a new version replacing the previous one.',
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
