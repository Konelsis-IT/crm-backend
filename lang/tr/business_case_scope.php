<?php

return [
    'label' => 'Proje kapsamı',
    'plural' => 'Proje kapsamları',

    'sections' => [
        'ges' => 'GES kapsamı',
        'res' => 'RES kapsamı',
        'tm' => 'TM kapsamı',
        'hes' => 'HES kapsamı',
        'bes' => 'BESS kapsamı',
        'enh_eih' => 'ENH/EİH kapsamı',
        // D-177 (B50)
        'automation' => 'Otomasyon / Process kapsamı',
    ],

    // Teklif kapsami alan basliklari (B43, D-155; 5 Ekim 2026 kullanicinin yazdigi
    // gibi). Tip => alan => baslik.
    'labels' => [
        'tm' => [
            'total_cost' => 'Toplam Maliyet',
            'total_sales' => 'Toplam Satış',
            'unit_cost' => 'Maliyet/Fider',
        ],
        'bes' => [
            'power_mwe' => 'MWe',
            'energy_mwh' => 'MWh',
            'unit_cost' => 'Bess Maliyet/MWh',
            'total_cost' => 'Toplam Maliyet',
            'unit_sales' => 'Bess Satış/MWh',
            'total_sales' => 'Toplam Satış',
        ],
        'hes' => [
            'total_cost' => 'Maliyet',
            'total_sales' => 'Satış',
            'unit_cost' => 'Maliyet/Jeneratör-Türbin',
        ],
        'ges' => [
            'capacity_mwp' => 'MWp',
            'unit_cost' => 'GES Maliyet/MWp',
            'total_cost' => 'Toplam Maliyet',
            'unit_sales' => 'GES Satış/MWp',
            'total_sales' => 'Toplam Satış Tutarı',
        ],
        'enh_eih' => [
            'length_km' => 'Km',
            'unit_cost' => 'ENH Maliyet/Km',
            'total_cost' => 'Toplam Maliyet',
            'unit_sales' => 'ENH Satış/Km',
            'total_sales' => 'Toplam Satış',
        ],
        'res' => [
            'res_material_amount' => 'Respark malzeme',
            'res_construction_amount' => 'Respark inşaat',
            'res_assembly_amount' => 'Respark montaj',
        ],
        // D-177: "Toplam Maliyet - Toplam Satis seklinde 2 input".
        'automation' => [
            'total_cost' => 'Toplam Maliyet',
            'total_sales' => 'Toplam Satış',
        ],
    ],

    'fields' => [
        'capacity_mw' => 'Kurulu güç (MW)',
        'cost_amount' => 'Maliyet',
        'sales_amount' => 'Satış',
        'cost_per_mw' => 'MW başı maliyet',
        'sales_per_mw' => 'MW başı satış',
        'total_sales' => 'Toplam satış',
        'res_material_amount' => 'Respark malzeme',
        'res_construction_amount' => 'Respark inşaat',
        'res_assembly_amount' => 'Respark montaj',
        'total' => 'Toplam',
        'tm_total_cost' => 'Toplam maliyet',
        'tm_total_sales' => 'Toplam satış',
        'tm_feeder_cost' => 'Fider başı maliyet',
        'hes_unit_cost' => 'Jeneratör/Türbin başı maliyet',
        'scope_file' => 'Kapsam listesi (Excel)',
        'current_file' => 'Yüklü kapsam listesi',
        'scope_document' => 'Kapsam listesi',
        'cost_files' => 'Maliyet listesi',
        'current_cost_files' => 'Yüklü maliyet listeleri',
        'note' => 'Not',
    ],

    'help' => [
        'fields_later' => 'Bu tip için alanlar daha sonra tanımlanacak; şimdilik kapsam listesini yükleyebilirsiniz.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu ":status" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
