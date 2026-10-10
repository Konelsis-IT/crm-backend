<?php

// Proje tipleri ve projenin kendi ölçüleri (B48, D-174). Tip adları ve
// simgeleri potansiyel iş / teklifle ortaktır (ProjectScopeType).
return [
    'sections' => [
        'ges' => 'GES',
        'res' => 'RES',
        'tm' => 'TM',
        'hes' => 'HES',
        'bes' => 'BESS',
        'enh_eih' => 'ENH/EİH',
        'automation' => 'Otomasyon / Process',
    ],

    'fields' => [
        'capacity_mwp' => 'Kurulu güç (MWp)',
        'capacity_mw' => 'Kurulu güç (MW)',
        'power_mwe' => 'Güç (MWe)',
        'energy_mwh' => 'Kapasite (MWh)',
        'length_km' => 'Hat uzunluğu (km)',
        'unit_count' => 'Adet',
        'contract_amount' => 'Sözleşme tutarı',
        'budget_amount' => 'Bütçe',
        'note' => 'Not',
    ],

    // Adet alanının tipe göre adı.
    'unit_count' => [
        'tm' => 'Fider sayısı',
        'hes' => 'Jeneratör / türbin sayısı',
        'res' => 'Türbin sayısı',
    ],

    'empty' => 'Proje tipi seçilmemiş.',
];
