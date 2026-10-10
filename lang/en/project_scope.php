<?php

// Project types and the project's own measures (B48, D-174).
return [
    'sections' => [
        'ges' => 'Solar (GES)',
        'res' => 'Wind (RES)',
        'tm' => 'Substation (TM)',
        'hes' => 'Hydro (HES)',
        'bes' => 'BESS',
        'enh_eih' => 'Transmission line (ENH/EİH)',
        'automation' => 'Automation / Process',
    ],

    'fields' => [
        'capacity_mwp' => 'Installed power (MWp)',
        'capacity_mw' => 'Installed power (MW)',
        'power_mwe' => 'Power (MWe)',
        'energy_mwh' => 'Capacity (MWh)',
        'length_km' => 'Line length (km)',
        'unit_count' => 'Count',
        'contract_amount' => 'Contract amount',
        'budget_amount' => 'Budget',
        'note' => 'Note',
    ],

    'unit_count' => [
        'tm' => 'Feeder count',
        'hes' => 'Generator / turbine count',
        'res' => 'Turbine count',
    ],

    'empty' => 'No project type selected.',
];
